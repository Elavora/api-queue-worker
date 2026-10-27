<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskHandler;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Framework\Contracts\Queue;

/**
 * Fila falsa em memória, usada nas tabelas de cenários da doc.
 *
 * @see docs/USO.md#o-que-muda-com-o-framework-2
 */
final class FakeQueue implements Queue
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $items = [];

    /** @var array<string, int> */
    private array $idleBefore = [];

    /** @var list<array{string, array<string, mixed>}> */
    public array $pushes = [];

    /** @var list<string> */
    public array $pops = [];

    public function push(string $queue, array $payload): void
    {
        $this->pushes[] = [$queue, $payload];
        $this->items[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        $this->pops[] = $queue;

        if (($this->idleBefore[$queue] ?? 0) > 0) {
            $this->idleBefore[$queue]--;

            return null;
        }

        if (($this->items[$queue] ?? []) === []) {
            return null;
        }

        return array_shift($this->items[$queue]);
    }

    /**
     * Faz os próximos `$times` pops da fila devolverem `null` antes de servir mensagens.
     */
    public function idleBefore(string $queue, int $times): void
    {
        $this->idleBefore[$queue] = $times;
    }

    /**
     * Mensagens que ainda estão na fila, sem retirá-las.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(string $queue): array
    {
        return $this->items[$queue] ?? [];
    }

    /**
     * Coloca uma mensagem crua na fila sem registrar como push do worker.
     *
     * @param array<string, mixed> $payload
     */
    public function seed(string $queue, array $payload): void
    {
        $this->items[$queue][] = $payload;
    }
}

final class FailingTaskHandler implements TaskHandler
{
    public ?RuntimeException $lastError = null;

    public int $calls = 0;

    public function handle(TaskPayload $payload): void
    {
        $this->calls++;
        $this->lastError = new RuntimeException('Falha intencional para teste.');

        throw $this->lastError;
    }
}

final class RecordingTaskHandler implements TaskHandler
{
    /** @var list<string> */
    public array $calls = [];

    public function handle(TaskPayload $payload): void
    {
        $this->calls[] = 'handle:' . $payload->task();
    }
}

final class InvokableTaskHandler implements TaskHandler
{
    /** @var list<string> */
    public array $calls = [];

    public function handle(TaskPayload $payload): void
    {
        $this->calls[] = 'handle';
    }

    public function __invoke(TaskPayload $payload): void
    {
        $this->calls[] = 'invoke';
    }
}

final class MethodCallableTarget
{
    /** @var list<string> */
    public array $calls = [];

    public function run(TaskPayload $payload): string
    {
        $this->calls[] = $payload->task();

        return 'retorno ignorado';
    }
}

final class FunctionHandlerLog
{
    /** @var list<string> */
    public static array $calls = [];
}

function elavora_queue_worker_test_handler(TaskPayload $payload): int
{
    FunctionHandlerLog::$calls[] = $payload->task();

    return 42;
}
