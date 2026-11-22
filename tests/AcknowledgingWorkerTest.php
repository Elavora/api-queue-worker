<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Confirmação (ack), filas com prioridade e renovação do prazo de visibilidade.
 *
 * @see docs/USO.md#confirmação-ack-e-filas-com-prioridade
 */
final class AcknowledgingWorkerTest extends TestCase
{
    private FakeAcknowledgingQueue $queue;

    private string|false $previousQueueName;

    protected function setUp(): void
    {
        $this->queue = new FakeAcknowledgingQueue();
        $this->previousQueueName = getenv('QUEUE_NAME');
        putenv('QUEUE_NAME');
    }

    protected function tearDown(): void
    {
        putenv($this->previousQueueName === false ? 'QUEUE_NAME' : 'QUEUE_NAME=' . $this->previousQueueName);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function seedTask(string $queue, string $task, array $payload = []): void
    {
        $this->queue->seed($queue, (new TaskPayload($task, $payload, maxAttempts: 2))->toArray());
    }

    public function testProcessedJobIsAcknowledged(): void
    {
        $handled = [];
        $registry = (new TaskRegistry())->add('ok', static function (TaskPayload $p) use (&$handled): void {
            $handled[] = $p->task();
        });
        $this->seedTask('default', 'ok');

        $result = (new QueueWorker($this->queue, $registry))->workOnce('default');

        self::assertTrue($result->isProcessed());
        self::assertSame(['ok'], $handled);
        self::assertSame(['reserve:default', 'ack:default'], $this->queue->events);
        self::assertSame([['default']], $this->queue->reserveCalls);
        self::assertSame([], $this->queue->processing);
    }

    public function testIdleDoesNotAcknowledge(): void
    {
        $result = (new QueueWorker($this->queue, new TaskRegistry()))->workOnce('default');

        self::assertTrue($result->isIdle());
        self::assertSame([], $this->queue->events);
    }

    public function testRetriedJobIsPushedBeforeTheAck(): void
    {
        $registry = (new TaskRegistry())->add('boom', new FailingTaskHandler());
        $this->seedTask('default', 'boom');

        $result = (new QueueWorker($this->queue, $registry))->workOnce('default');

        self::assertTrue($result->isRetried());
        self::assertSame(['reserve:default', 'push:default', 'ack:default'], $this->queue->events);
        self::assertSame(1, $this->queue->messages('default')[0]['attempts']);
        self::assertSame([], $this->queue->processing);
    }

    public function testDefinitivelyFailedJobIsAcknowledgedWithoutPush(): void
    {
        $registry = (new TaskRegistry())->add('boom', new FailingTaskHandler());
        $this->queue->seed('default', (new TaskPayload('boom', maxAttempts: 1))->toArray());

        $result = (new QueueWorker($this->queue, $registry))->workOnce('default');

        self::assertTrue($result->isFailed());
        self::assertSame(['reserve:default', 'ack:default'], $this->queue->events);
        self::assertSame([], $this->queue->messages('default'));
    }

    public function testInvalidPayloadIsAcknowledgedBeforeTheExceptionRises(): void
    {
        $this->queue->seed('default', ['task' => 10]);

        try {
            (new QueueWorker($this->queue, new TaskRegistry()))->workOnce('default');
            self::fail('Era esperada InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame(['reserve:default', 'ack:default'], $this->queue->events);
            self::assertSame([], $this->queue->processing);
        }
    }

    public function testWorkOnceFromFollowsPriorityOrder(): void
    {
        $handled = [];
        $registry = (new TaskRegistry())
            ->add('conteudo', static function () use (&$handled): void {
                $handled[] = 'conteudo';
            })
            ->add('tags', static function () use (&$handled): void {
                $handled[] = 'tags';
            });
        $this->seedTask('tags', 'tags');
        $this->seedTask('conteudo', 'conteudo');
        $worker = new QueueWorker($this->queue, $registry);

        $worker->workOnceFrom(['conteudo', 'descricao', 'tags']);
        $worker->workOnceFrom(['conteudo', 'descricao', 'tags']);
        $idle = $worker->workOnceFrom(['conteudo', 'descricao', 'tags']);

        self::assertSame(['conteudo', 'tags'], $handled);
        self::assertTrue($idle->isIdle());
        self::assertSame(['conteudo', 'descricao', 'tags'], $this->queue->reserveCalls[0]);
    }

    public function testRetryGoesBackToTheQueueTheJobCameFrom(): void
    {
        $registry = (new TaskRegistry())->add('boom', new FailingTaskHandler());
        $this->seedTask('baixa', 'boom');

        (new QueueWorker($this->queue, $registry))->workOnceFrom(['alta', 'baixa']);

        self::assertSame(['reserve:baixa', 'push:baixa', 'ack:baixa'], $this->queue->events);
        self::assertSame([], $this->queue->messages('alta'));
        self::assertCount(1, $this->queue->messages('baixa'));
    }

    public function testWorkOnceFromRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A lista de filas nao pode ser vazia.');

        (new QueueWorker($this->queue, new TaskRegistry()))->workOnceFrom([]);
    }

    public function testWorkOnceFromWithPlainQueueTriesEachQueueInOrder(): void
    {
        $plain = new FakeQueue();
        $handled = [];
        $registry = (new TaskRegistry())->add('t', static function (TaskPayload $p) use (&$handled): void {
            $handled[] = $p->data()['de'];
        });
        $plain->seed('baixa', (new TaskPayload('t', ['de' => 'baixa']))->toArray());
        $plain->seed('media', (new TaskPayload('t', ['de' => 'media']))->toArray());
        $worker = new QueueWorker($plain, $registry);

        $worker->workOnceFrom(['alta', 'media', 'baixa']);
        $worker->workOnceFrom(['alta', 'media', 'baixa']);
        $idle = $worker->workOnceFrom(['alta', 'media', 'baixa']);

        self::assertSame(['media', 'baixa'], $handled);
        self::assertTrue($idle->isIdle());
    }

    public function testWorkOnceFromWithPlainQueueRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A lista de filas nao pode ser vazia.');

        (new QueueWorker(new FakeQueue(), new TaskRegistry()))->workOnceFrom([]);
    }

    public function testExtendCurrentRenewsTheJobBeingProcessed(): void
    {
        $worker = null;
        $registry = (new TaskRegistry())->add('longo', static function () use (&$worker): void {
            $worker->extendCurrent(3600);
            $worker->extendCurrent();
        });
        $worker = new QueueWorker($this->queue, $registry);
        $this->seedTask('default', 'longo');

        $worker->workOnce('default');

        self::assertSame(
            ['reserve:default', 'extend:default:3600', 'extend:default:default', 'ack:default'],
            $this->queue->events,
        );
    }

    public function testExtendCurrentOutsideProcessingDoesNothing(): void
    {
        $worker = new QueueWorker($this->queue, (new TaskRegistry())->add('ok', static function (): void {}));
        $this->seedTask('default', 'ok');

        $worker->extendCurrent(60);
        $worker->workOnce('default');
        $worker->extendCurrent(60);

        self::assertSame(['reserve:default', 'ack:default'], $this->queue->events);
    }

    public function testExtendCurrentWithPlainQueueDoesNothing(): void
    {
        $plain = new FakeQueue();
        $worker = null;
        $registry = (new TaskRegistry())->add('longo', static function () use (&$worker): void {
            $worker->extendCurrent(60);
        });
        $worker = new QueueWorker($plain, $registry);
        $plain->seed('default', (new TaskPayload('longo'))->toArray());

        self::assertTrue($worker->workOnce('default')->isProcessed());
    }

    public function testCommandConsumesQueuesInPriorityOrder(): void
    {
        $handled = [];
        $registry = (new TaskRegistry())->add('t', static function (TaskPayload $p) use (&$handled): void {
            $handled[] = $p->data()['de'];
        });
        $this->queue->seed('baixa', (new TaskPayload('t', ['de' => 'baixa']))->toArray());
        $this->queue->seed('alta', (new TaskPayload('t', ['de' => 'alta']))->toArray());
        $command = new QueueWorkerCommand(new QueueWorker($this->queue, $registry));

        $exit = $command->run(['worker.php', '--queue=alta,baixa', '--sleep=0', '--max-jobs=2']);

        self::assertSame(0, $exit);
        self::assertSame(['alta', 'baixa'], $handled);
        self::assertSame([], $this->queue->processing);
    }

    public function testCommandIgnoresBlankQueueNames(): void
    {
        $command = new QueueWorkerCommand(new QueueWorker($this->queue, (new TaskRegistry())->add('t', static function (): void {})));
        $this->queue->seed('baixa', (new TaskPayload('t'))->toArray());

        $command->run(['worker.php', '--queue=alta, ,baixa', '--sleep=0', '--max-jobs=1']);

        self::assertSame([['alta', 'baixa']], $this->queue->reserveCalls);
    }

    public function testCommandReadsSeveralQueuesFromQueueName(): void
    {
        putenv('QUEUE_NAME=alta,baixa');
        $this->queue->seed('baixa', (new TaskPayload('t'))->toArray());
        $command = new QueueWorkerCommand(new QueueWorker($this->queue, (new TaskRegistry())->add('t', static function (): void {})));

        $command->run(['worker.php', '--sleep=0', '--max-jobs=1']);

        self::assertSame([['alta', 'baixa']], $this->queue->reserveCalls);
    }

    public function testCommandKeepsEmptyQueueOptionAsBefore(): void
    {
        $command = new QueueWorkerCommand(new QueueWorker($this->queue, new TaskRegistry()));
        $this->queue->seed('', (new TaskPayload('t'))->toArray());

        $exit = $command->run(['worker.php', '--queue=', '--sleep=0', '--max-jobs=1']);

        self::assertSame(0, $exit);
        self::assertSame([['']], $this->queue->reserveCalls);
    }
}
