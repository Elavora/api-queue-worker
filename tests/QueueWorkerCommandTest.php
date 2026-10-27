<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Loop de consumo e opções do QueueWorkerCommand.
 *
 * Os cenários usam `--sleep=0` e publicam tarefas suficientes para o loop terminar.
 *
 * @see docs/USO.md#queueworkercommand
 */
final class QueueWorkerCommandTest extends TestCase
{
    private FakeQueue $queue;

    /** @var list<string> */
    private array $handled = [];

    private FailingTaskHandler $failing;

    private string|false $previousQueueName;

    protected function setUp(): void
    {
        $this->previousQueueName = getenv('QUEUE_NAME');
        putenv('QUEUE_NAME');
        $this->queue = new FakeQueue();
        $this->handled = [];
        $this->failing = new FailingTaskHandler();
    }

    protected function tearDown(): void
    {
        if ($this->previousQueueName === false) {
            putenv('QUEUE_NAME');
        } else {
            putenv('QUEUE_NAME=' . $this->previousQueueName);
        }
    }

    /** @see docs/USO.md#loop */
    public function testStopsAfterMaxJobs(): void
    {
        $this->publish('default', 2);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=2']));
        self::assertCount(2, $this->handled);
    }

    /** @see docs/USO.md#loop */
    public function testLeavesRemainingTasksInQueue(): void
    {
        $this->publish('default', 3);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=2']));
        self::assertCount(2, $this->handled);
        self::assertCount(1, $this->queue->messages('default'));
    }

    /** @see docs/USO.md#loop */
    public function testRetriedCountsForMaxJobs(): void
    {
        $this->queue->push('default', (new TaskPayload('always.fail', maxAttempts: 2))->toArray());

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']));
        $messages = $this->queue->messages('default');
        self::assertCount(1, $messages);
        self::assertSame(1, $messages[0]['attempts']);
    }

    /** @see docs/USO.md#loop */
    public function testDefinitiveFailureWritesToStderrAndReturnsOne(): void
    {
        $env = getenv();
        unset($env['QUEUE_NAME']);
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/comando-falha-definitiva.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(1, $exitCode, 'STDOUT: ' . $stdout . ' STDERR: ' . $stderr);
        self::assertSame('Tarefa falhou definitivamente na fila default.' . PHP_EOL, $stderr);
        self::assertSame('', $stdout);
    }

    /** @see docs/USO.md#loop */
    public function testDefinitiveFailureStopsLoopEvenWithoutMaxJobs(): void
    {
        $this->publish('default', 2);
        $this->queue->push('default', (new TaskPayload('always.fail', maxAttempts: 1))->toArray());
        $this->publish('default', 1);

        self::assertSame(1, $this->runUntilFailure(['worker.php', '--sleep=0']));
        self::assertCount(2, $this->handled);
        self::assertCount(1, $this->queue->messages('default'));
    }

    /** @see docs/USO.md#loop */
    public function testInvalidPayloadExceptionPropagatesFromRun(): void
    {
        $this->queue->seed('default', ['task' => 10]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload de tarefa deve conter task como string.');

        $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']);
    }

    /** @see docs/USO.md#loop */
    public function testIdleDoesNotCountForMaxJobs(): void
    {
        $this->queue->idleBefore('default', 2);
        $this->publish('default', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']));
        self::assertCount(1, $this->handled);
        self::assertSame(['default', 'default', 'default'], $this->queue->pops);
    }

    /** @see docs/USO.md#opções */
    public function testQueueNameFromEnvironment(): void
    {
        putenv('QUEUE_NAME=emails');
        $this->publish('emails', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']));
        self::assertCount(1, $this->handled);
        self::assertSame(['emails'], array_unique($this->queue->pops));
    }

    /** @see docs/USO.md#opções */
    public function testQueueOptionOverridesEnvironment(): void
    {
        putenv('QUEUE_NAME=emails');
        $this->publish('emails', 1);
        $this->publish('sms', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--queue=sms', '--sleep=0', '--max-jobs=1']));
        self::assertSame(['sms'], array_unique($this->queue->pops));
        self::assertCount(1, $this->queue->messages('emails'));
        self::assertSame([], $this->queue->messages('sms'));
    }

    /** @see docs/USO.md#opções */
    public function testEmptyQueueNameEnvironmentFallsBackToDefault(): void
    {
        putenv('QUEUE_NAME=');
        $this->publish('default', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']));
        self::assertSame(['default'], array_unique($this->queue->pops));
    }

    /** @see docs/USO.md#opções */
    public function testMissingQueueNameEnvironmentUsesDefault(): void
    {
        $this->publish('default', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--sleep=0', '--max-jobs=1']));
        self::assertSame(['default'], array_unique($this->queue->pops));
    }

    /** @see docs/USO.md#opções */
    public function testEmptyQueueOptionUsesEmptyQueueName(): void
    {
        $this->publish('', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--queue=', '--sleep=0', '--max-jobs=1']));
        self::assertSame([''], array_unique($this->queue->pops));
    }

    /** @see docs/USO.md#opções */
    public function testFirstArgvItemIsIgnored(): void
    {
        $this->publish('default', 1);

        self::assertSame(0, $this->command()->run(['--queue=sms', '--sleep=0', '--max-jobs=1']));
        self::assertSame(['default'], array_unique($this->queue->pops));
    }

    /** @see docs/USO.md#opções */
    public function testLastRepeatedOptionWins(): void
    {
        $this->publish('b', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--queue=a', '--queue=b', '--sleep=0', '--max-jobs=5', '--max-jobs=1']));
        self::assertSame(['b'], array_unique($this->queue->pops));
        self::assertCount(1, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testUnknownArgumentsAreIgnored(): void
    {
        $this->publish('default', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--foo=bar', 'solto', '-v', '--sleep=0', '--max-jobs=1']));
        self::assertCount(1, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testSpaceSeparatedFormIsNotRecognized(): void
    {
        $this->publish('default', 1);
        $this->publish('emails', 1);

        self::assertSame(0, $this->command()->run(['worker.php', '--queue', 'emails', '--sleep=0', '--max-jobs=1']));
        self::assertSame(['default'], array_unique($this->queue->pops));
        self::assertCount(1, $this->queue->messages('emails'));
    }

    /** @see docs/USO.md#opções */
    public function testNegativeMaxJobsMeansNoLimit(): void
    {
        $this->publish('default', 3);
        $this->queue->push('default', (new TaskPayload('always.fail', maxAttempts: 1))->toArray());

        self::assertSame(1, $this->runUntilFailure(['worker.php', '--sleep=0', '--max-jobs=-1']));
        self::assertCount(3, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testMaxJobsDefaultsToNoLimit(): void
    {
        $this->publish('default', 3);
        $this->queue->push('default', (new TaskPayload('always.fail', maxAttempts: 1))->toArray());

        self::assertSame(1, $this->runUntilFailure(['worker.php', '--sleep=0']));
        self::assertCount(3, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testNegativeSleepBecomesZero(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);

        $elapsed = $this->timedRun($this->command(5), ['worker.php', '--sleep=-3', '--max-jobs=1']);

        self::assertLessThan(0.9, $elapsed);
        self::assertCount(1, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testNonNumericSleepBecomesZero(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);

        $elapsed = $this->timedRun($this->command(5), ['worker.php', '--sleep=abc', '--max-jobs=1']);

        self::assertLessThan(0.9, $elapsed);
        self::assertCount(1, $this->handled);
    }

    /** @see docs/USO.md#opções */
    public function testSleepWithSuffixIsCastToInt(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);

        $elapsed = $this->timedRun($this->command(0), ['worker.php', '--sleep=1s', '--max-jobs=1']);

        self::assertGreaterThanOrEqual(0.9, $elapsed);
        self::assertLessThan(4.0, $elapsed);
    }

    /** @see docs/USO.md#opções */
    public function testSleepDefaultsToConstructorValue(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);

        $elapsed = $this->timedRun($this->command(1), ['worker.php', '--max-jobs=1']);

        self::assertGreaterThanOrEqual(0.9, $elapsed);
        self::assertLessThan(4.0, $elapsed);
    }

    /** @see docs/USO.md#queueworkercommand */
    public function testDefaultSleepSecondsIsOne(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);
        $command = new QueueWorkerCommand(new QueueWorker($this->queue, $this->registry()));

        $elapsed = $this->timedRun($command, ['worker.php', '--max-jobs=1']);

        self::assertGreaterThanOrEqual(0.9, $elapsed);
        self::assertLessThan(4.0, $elapsed);
    }

    /** @see docs/USO.md#queueworkercommand */
    public function testZeroSleepOptionOverridesConstructorValue(): void
    {
        $this->queue->idleBefore('default', 1);
        $this->publish('default', 1);

        $elapsed = $this->timedRun($this->command(5), ['worker.php', '--sleep=0', '--max-jobs=1']);

        self::assertLessThan(0.9, $elapsed);
    }

    private function registry(): TaskRegistry
    {
        return (new TaskRegistry())
            ->add('ok', function (TaskPayload $payload): void {
                $this->handled[] = (string) ($payload->data()['n'] ?? '');
            })
            ->add('always.fail', $this->failing);
    }

    private function command(int $defaultSleepSeconds = 0): QueueWorkerCommand
    {
        return new QueueWorkerCommand(new QueueWorker($this->queue, $this->registry()), $defaultSleepSeconds);
    }

    private function publish(string $queue, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->queue->push($queue, (new TaskPayload('ok', ['n' => $i]))->toArray());
        }
    }

    /**
     * @param list<string> $argv
     */
    private function timedRun(QueueWorkerCommand $command, array $argv): float
    {
        $start = microtime(true);
        self::assertSame(0, $command->run($argv));

        return microtime(true) - $start;
    }

    /**
     * Roda o comando num cenário que termina em falha definitiva (a mensagem vai para o STDERR do PHPUnit).
     *
     * @param list<string> $argv
     */
    private function runUntilFailure(array $argv): int
    {
        return $this->command()->run($argv);
    }
}
