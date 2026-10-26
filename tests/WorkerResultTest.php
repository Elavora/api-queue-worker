<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\WorkerResult;
use PHPUnit\Framework\TestCase;

/**
 * Fábricas e estados do WorkerResult.
 *
 * @see docs/USO.md#workerresult
 */
final class WorkerResultTest extends TestCase
{
    /** @see docs/USO.md#workerresult */
    public function testConstructorIsPrivate(): void
    {
        self::assertTrue((new ReflectionMethod(WorkerResult::class, '__construct'))->isPrivate());
    }

    /** @see docs/USO.md#workerresult */
    public function testIdle(): void
    {
        $result = WorkerResult::idle();

        self::assertSame([true, false, false, false], $this->flags($result));
        self::assertNull($result->payload());
        self::assertNull($result->error());
    }

    /** @see docs/USO.md#workerresult */
    public function testProcessed(): void
    {
        $payload = new TaskPayload('a');
        $result = WorkerResult::processed($payload);

        self::assertSame([false, true, false, false], $this->flags($result));
        self::assertSame($payload, $result->payload());
        self::assertNull($result->error());
    }

    /** @see docs/USO.md#workerresult */
    public function testRetried(): void
    {
        $payload = new TaskPayload('a');
        $error = new RuntimeException('x');
        $result = WorkerResult::retried($payload, $error);

        self::assertSame([false, false, true, false], $this->flags($result));
        self::assertSame($payload, $result->payload());
        self::assertSame($error, $result->error());
    }

    /** @see docs/USO.md#workerresult */
    public function testFailed(): void
    {
        $payload = new TaskPayload('a');
        $error = new LogicException('y');
        $result = WorkerResult::failed($payload, $error);

        self::assertSame([false, false, false, true], $this->flags($result));
        self::assertSame($payload, $result->payload());
        self::assertSame($error, $result->error());
    }

    /**
     * @return array{bool, bool, bool, bool}
     */
    private function flags(WorkerResult $result): array
    {
        return [$result->isIdle(), $result->isProcessed(), $result->isRetried(), $result->isFailed()];
    }
}
