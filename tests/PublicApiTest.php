<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskHandler;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Extension\QueueWorker\WorkerResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Forma da API pública: classes final e TaskHandler como interface.
 *
 * @see docs/USO.md#api-pública
 */
final class PublicApiTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function classProvider(): iterable
    {
        yield 'TaskPayload' => [TaskPayload::class];
        yield 'TaskRegistry' => [TaskRegistry::class];
        yield 'QueueWorker' => [QueueWorker::class];
        yield 'WorkerResult' => [WorkerResult::class];
        yield 'QueueWorkerCommand' => [QueueWorkerCommand::class];
    }

    /**
     * @param class-string $class
     *
     * @see docs/USO.md#api-pública
     */
    #[DataProvider('classProvider')]
    public function testClassIsFinal(string $class): void
    {
        self::assertTrue((new ReflectionClass($class))->isFinal());
    }

    /** @see docs/USO.md#taskhandler */
    public function testTaskHandlerIsInterface(): void
    {
        self::assertTrue((new ReflectionClass(TaskHandler::class))->isInterface());
    }
}
