<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Registro de handlers por nome de tarefa.
 *
 * @see docs/USO.md#taskregistry
 */
final class TaskRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FunctionHandlerLog::$calls = [];
    }

    /** @see docs/USO.md#add */
    public function testAddReturnsSameInstance(): void
    {
        $registry = new TaskRegistry();

        self::assertSame($registry, $registry->add('a', static function (TaskPayload $payload): void {}));
    }

    /** @see docs/USO.md#add */
    public function testAddRejectsEmptyTaskWithRuntimeException(): void
    {
        try {
            (new TaskRegistry())->add('', static function (TaskPayload $payload): void {});
            self::fail('Esperava RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(InvalidArgumentException::class, $exception);
            self::assertSame('O nome da tarefa nao pode ser vazio.', $exception->getMessage());
        }
    }

    /** @see docs/USO.md#add */
    public function testAddingSameNameReplacesHandler(): void
    {
        $calls = [];
        $registry = (new TaskRegistry())
            ->add('a', static function (TaskPayload $payload) use (&$calls): void {
                $calls[] = 'primeiro';
            })
            ->add('a', static function (TaskPayload $payload) use (&$calls): void {
                $calls[] = 'segundo';
            });

        $registry->handle(new TaskPayload('a'));

        self::assertSame(['segundo'], $calls);
    }

    /** @see docs/USO.md#handle */
    public function testHandlePassesPayloadToClosure(): void
    {
        $received = null;
        $payload = new TaskPayload('a', ['k' => 1]);
        (new TaskRegistry())
            ->add('a', static function (TaskPayload $p) use (&$received): void {
                $received = $p;
            })
            ->handle($payload);

        self::assertSame($payload, $received);
    }

    /** @see docs/USO.md#handle */
    public function testHandleWithoutHandlerThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nenhum handler registrado para a tarefa emails.send.');

        (new TaskRegistry())->handle(new TaskPayload('emails.send'));
    }

    /** @see docs/USO.md#handle */
    public function testHandleLookupIsCaseSensitive(): void
    {
        $registry = (new TaskRegistry())->add('emails.send', static function (TaskPayload $payload): void {});

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nenhum handler registrado para a tarefa Emails.Send.');

        $registry->handle(new TaskPayload('Emails.Send'));
    }

    /** @see docs/USO.md#handle */
    public function testHandleCallsTaskHandler(): void
    {
        $handler = new RecordingTaskHandler();
        (new TaskRegistry())->add('a', $handler)->handle(new TaskPayload('a'));

        self::assertSame(['handle:a'], $handler->calls);
    }

    /** @see docs/USO.md#handle */
    public function testTaskHandlerWithInvokeUsesHandle(): void
    {
        $handler = new InvokableTaskHandler();
        (new TaskRegistry())->add('a', $handler)->handle(new TaskPayload('a'));

        self::assertSame(['handle'], $handler->calls);
    }

    /** @see docs/USO.md#handle */
    public function testHandleCallsArrayCallableAndIgnoresReturn(): void
    {
        $target = new MethodCallableTarget();
        (new TaskRegistry())->add('a', [$target, 'run'])->handle(new TaskPayload('a'));

        self::assertSame(['a'], $target->calls);
    }

    /** @see docs/USO.md#handle */
    public function testHandleCallsFunctionName(): void
    {
        (new TaskRegistry())->add('a', 'elavora_queue_worker_test_handler')->handle(new TaskPayload('a'));

        self::assertSame(['a'], FunctionHandlerLog::$calls);
    }

    /** @see docs/USO.md#handle */
    public function testHandlerExceptionPropagates(): void
    {
        $handler = new FailingTaskHandler();
        $registry = (new TaskRegistry())->add('a', $handler);

        try {
            $registry->handle(new TaskPayload('a'));
            self::fail('Esperava a exceção do handler.');
        } catch (RuntimeException $exception) {
            self::assertSame($handler->lastError, $exception);
        }
    }
}
