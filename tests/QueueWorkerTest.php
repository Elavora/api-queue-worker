<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Processamento de uma mensagem por chamada.
 *
 * @see docs/USO.md#queueworker
 */
final class QueueWorkerTest extends TestCase
{
    /** @see docs/USO.md#workonce */
    public function testProcessesSerializedTaskPayload(): void
    {
        $queue = new FakeQueue();
        $handledPayloads = [];
        $registry = (new TaskRegistry())->add('emails.send', static function (TaskPayload $payload) use (&$handledPayloads): void {
            $handledPayloads[] = $payload->data();
        });
        $worker = new QueueWorker($queue, $registry);

        $queue->push('default', (new TaskPayload('emails.send', ['email' => 'user@example.com']))->toArray());
        $result = $worker->workOnce('default');

        self::assertTrue($result->isProcessed());
        self::assertSame(['email' => 'user@example.com'], $result->payload()?->data());
        self::assertNull($result->error());
        self::assertSame([['email' => 'user@example.com']], $handledPayloads);
        self::assertNull($queue->pop('default'));
    }

    /** @see docs/USO.md#workonce */
    public function testRetriesFailedTaskUntilMaxAttempts(): void
    {
        $queue = new FakeQueue();
        $handler = new FailingTaskHandler();
        $registry = (new TaskRegistry())->add('always.fail', $handler);
        $worker = new QueueWorker($queue, $registry);

        $queue->push('default', (new TaskPayload('always.fail', maxAttempts: 2))->toArray());

        $firstResult = $worker->workOnce('default');
        self::assertTrue($firstResult->isRetried());
        self::assertSame(1, $firstResult->payload()?->attempts());
        self::assertSame($handler->lastError, $firstResult->error());
        $messages = $queue->messages('default');
        self::assertCount(1, $messages);
        self::assertSame(1, $messages[0]['attempts']);

        $secondResult = $worker->workOnce('default');
        self::assertTrue($secondResult->isFailed());
        self::assertSame(2, $secondResult->payload()?->attempts());
        self::assertSame($handler->lastError, $secondResult->error());
        self::assertNull($queue->pop('default'));
    }

    /** @see docs/USO.md#workonce */
    public function testHandlerRunsAtMostMaxAttemptsTimes(): void
    {
        $queue = new FakeQueue();
        $handler = new FailingTaskHandler();
        $worker = new QueueWorker($queue, (new TaskRegistry())->add('always.fail', $handler));
        $queue->push('default', (new TaskPayload('always.fail'))->toArray());

        $first = $worker->workOnce('default');
        $second = $worker->workOnce('default');
        $third = $worker->workOnce('default');

        self::assertTrue($first->isRetried());
        self::assertSame(1, $first->payload()?->attempts());
        self::assertTrue($second->isRetried());
        self::assertSame(2, $second->payload()?->attempts());
        self::assertTrue($third->isFailed());
        self::assertSame(3, $third->payload()?->attempts());
        self::assertSame(3, $handler->calls);
        self::assertTrue($worker->workOnce('default')->isIdle());
    }

    /** @see docs/USO.md#workonce */
    public function testRetriedMessageGoesToEndOfSameQueue(): void
    {
        $queue = new FakeQueue();
        $registry = (new TaskRegistry())
            ->add('always.fail', new FailingTaskHandler())
            ->add('ok', static function (TaskPayload $payload): void {
            });
        $worker = new QueueWorker($queue, $registry);
        $queue->push('emails', (new TaskPayload('always.fail'))->toArray());
        $queue->push('emails', (new TaskPayload('ok'))->toArray());

        $worker->workOnce('emails');

        $messages = $queue->messages('emails');
        self::assertSame(['ok', 'always.fail'], array_column($messages, 'task'));
        self::assertSame([], $queue->messages('default'));
    }

    /** @see docs/USO.md#workonce */
    public function testReturnsIdleWhenQueueIsEmpty(): void
    {
        $queue = new FakeQueue();
        $worker = new QueueWorker($queue, new TaskRegistry());

        $result = $worker->workOnce('default');

        self::assertTrue($result->isIdle());
        self::assertNull($result->payload());
        self::assertNull($result->error());
        self::assertSame([], $queue->pushes);
        self::assertSame(['default'], $queue->pops);
    }

    /** @see docs/USO.md#workonce */
    public function testMissingHandlerFailsDefinitivelyWithMaxAttemptsOne(): void
    {
        $queue = new FakeQueue();
        $worker = new QueueWorker($queue, new TaskRegistry());
        $queue->push('default', (new TaskPayload('emails.send', maxAttempts: 1))->toArray());

        $result = $worker->workOnce('default');

        self::assertTrue($result->isFailed());
        self::assertInstanceOf(RuntimeException::class, $result->error());
        self::assertSame('Nenhum handler registrado para a tarefa emails.send.', $result->error()->getMessage());
        self::assertSame([], $queue->messages('default'));
    }

    /** @see docs/USO.md#workonce */
    public function testInvalidPayloadPropagatesAndIsRemovedFromQueue(): void
    {
        $queue = new FakeQueue();
        $queue->seed('default', ['task' => 10]);
        $worker = new QueueWorker($queue, new TaskRegistry());

        try {
            $worker->workOnce('default');
            self::fail('Esperava InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Payload de tarefa deve conter task como string.', $exception->getMessage());
        }

        self::assertSame([], $queue->messages('default'));
        self::assertSame([], $queue->pushes);
    }

    /** @see docs/USO.md#queueworker */
    public function testAcceptsNamedArguments(): void
    {
        $worker = new QueueWorker(queue: new FakeQueue(), tasks: new TaskRegistry());

        self::assertTrue($worker->workOnce('default')->isIdle());
    }
}
