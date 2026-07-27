<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Extension\QueueWorker\TerminalFailureHandler;
use Elavora\Api\Extension\QueueWorker\WorkerResult;
use Elavora\Api\Framework\Contracts\Queue;
use PHPUnit\Framework\TestCase;

final class QueueWorkerTerminalResultTest extends TestCase
{
    public function testPreservesInvalidPayloadAndNotifiesTerminalHandlerOnce(): void
    {
        $queue = new ControlledWorkerQueue();
        $terminal = new RecordingTerminalFailureHandler();
        $worker = new QueueWorker($queue, new TaskRegistry(), $terminal);
        $rawPayload = ['data' => ['secret' => 'sensitive-value']];
        $queue->push('critical', $rawPayload);

        $result = $worker->workOnce('critical');

        self::assertTrue($result->isInvalid());
        self::assertSame('invalid', $result->status());
        self::assertSame('critical', $result->queue());
        self::assertSame($rawPayload, $result->rawPayload());
        self::assertInstanceOf(InvalidArgumentException::class, $result->error());
        self::assertSame([$result], $terminal->results);
        self::assertNull($queue->pop('critical'));
    }

    public function testUnknownTaskRetriesAndPreservesFinalFailureContext(): void
    {
        $queue = new ControlledWorkerQueue();
        $terminal = new RecordingTerminalFailureHandler();
        $worker = new QueueWorker($queue, new TaskRegistry(), $terminal);
        $queue->push('default', (new TaskPayload('unknown.task', maxAttempts: 2))->toArray());

        $retry = $worker->workOnce('default');
        self::assertTrue($retry->isRetried());
        self::assertSame(1, $retry->payload()?->attempts());
        self::assertSame([], $terminal->results);

        $failure = $worker->workOnce('default');
        self::assertTrue($failure->isFailed());
        self::assertSame('default', $failure->queue());
        self::assertSame('unknown.task', $failure->payload()?->task());
        self::assertSame(2, $failure->payload()?->attempts());
        self::assertInstanceOf(RuntimeException::class, $failure->error());
        self::assertSame([$failure], $terminal->results);
        self::assertNull($queue->pop('default'));
    }

    public function testTaskCanSucceedAfterRecoverableFailure(): void
    {
        $queue = new ControlledWorkerQueue();
        $terminal = new RecordingTerminalFailureHandler();
        $runs = 0;
        $registry = (new TaskRegistry())->add(
            'eventually.succeeds',
            static function (TaskPayload $payload) use (&$runs): void {
                $runs++;
                if ($runs === 1) {
                    throw new RuntimeException('Falha temporaria.');
                }
            }
        );
        $worker = new QueueWorker($queue, $registry, $terminal);
        $queue->push('default', (new TaskPayload('eventually.succeeds'))->toArray());

        self::assertTrue($worker->workOnce('default')->isRetried());
        $success = $worker->workOnce('default');

        self::assertTrue($success->isProcessed());
        self::assertSame(1, $success->payload()?->attempts());
        self::assertSame(2, $runs);
        self::assertSame([], $terminal->results);
    }

    public function testCommandDistinguishesInvalidPayloadWithoutLoggingIt(): void
    {
        $queue = new ControlledWorkerQueue();
        $queue->push('critical', ['secret' => 'sensitive-value']);
        $errors = [];
        $command = new QueueWorkerCommand(
            worker: new QueueWorker($queue, new TaskRegistry()),
            errorOutput: static function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        $exitCode = $command->run(['worker.php', '--queue=critical', '--once']);

        self::assertSame(QueueWorkerCommand::EXIT_INVALID, $exitCode);
        self::assertSame(["Mensagem invalida na fila critical.\n"], $errors);
        self::assertStringNotContainsString('sensitive-value', implode('', $errors));
    }

    public function testCommandDistinguishesRetryAndFinalFailure(): void
    {
        $retryQueue = new ControlledWorkerQueue();
        $retryQueue->push('jobs', (new TaskPayload('always.fails', maxAttempts: 2))->toArray());
        $registry = (new TaskRegistry())->add(
            'always.fails',
            static function (TaskPayload $payload): void {
                throw new RuntimeException('Falha esperada.');
            }
        );
        $output = [];
        $retryCommand = new QueueWorkerCommand(
            worker: new QueueWorker($retryQueue, $registry),
            output: static function (string $message) use (&$output): void {
                $output[] = $message;
            }
        );

        self::assertSame(
            QueueWorkerCommand::EXIT_SUCCESS,
            $retryCommand->run(['worker.php', '--queue=jobs', '--once'])
        );
        self::assertSame(["Tarefa reenfileirada na fila jobs.\n"], $output);

        $errors = [];
        $failureCommand = new QueueWorkerCommand(
            worker: new QueueWorker($retryQueue, $registry),
            errorOutput: static function (string $message) use (&$errors): void {
                $errors[] = $message;
            }
        );

        self::assertSame(
            QueueWorkerCommand::EXIT_FAILED,
            $failureCommand->run(['worker.php', '--queue=jobs', '--once'])
        );
        self::assertSame(["Tarefa falhou definitivamente na fila jobs.\n"], $errors);
    }

    public function testCommandReportsIdleAndProcessedStatesInOnceMode(): void
    {
        $queue = new ControlledWorkerQueue();
        $output = [];
        $worker = new QueueWorker(
            $queue,
            (new TaskRegistry())->add('ping', static function (TaskPayload $payload): void {
            })
        );
        $command = new QueueWorkerCommand(
            worker: $worker,
            output: static function (string $message) use (&$output): void {
                $output[] = $message;
            }
        );

        self::assertSame(
            QueueWorkerCommand::EXIT_SUCCESS,
            $command->run(['worker.php', '--queue=jobs', '--once'])
        );
        self::assertSame(["Fila jobs vazia.\n"], $output);

        $queue->push('jobs', (new TaskPayload('ping'))->toArray());
        $output = [];
        self::assertSame(
            QueueWorkerCommand::EXIT_SUCCESS,
            $command->run(['worker.php', '--queue=jobs', '--once'])
        );
        self::assertSame(["Tarefa processada na fila jobs.\n"], $output);
    }
}

final class RecordingTerminalFailureHandler implements TerminalFailureHandler
{
    /** @var list<WorkerResult> */
    public array $results = [];

    public function handle(WorkerResult $result): void
    {
        $this->results[] = $result;
    }
}

final class ControlledWorkerQueue implements Queue
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $items = [];

    public function push(string $queue, array $payload): void
    {
        $this->items[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        if (($this->items[$queue] ?? []) === []) {
            return null;
        }

        return array_shift($this->items[$queue]);
    }
}
