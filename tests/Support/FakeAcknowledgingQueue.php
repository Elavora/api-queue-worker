<?php

declare(strict_types=1);

use Elavora\Api\Framework\Contracts\AcknowledgesJobs;
use Elavora\Api\Framework\Contracts\Queue;
use Elavora\Api\Framework\Queue\ReservedJob;

/**
 * Fila falsa em memória com reserva e ack, usada nos cenários de confirmação da doc.
 *
 * @see docs/USO.md#confirmação-ack-e-filas-com-prioridade
 */
final class FakeAcknowledgingQueue implements Queue, AcknowledgesJobs
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $items = [];

    /** @var array<string, ReservedJob> token => reserva ainda sem ack */
    public array $processing = [];

    /** @var list<string> Eventos na ordem: push:<fila>, reserve:<fila>, ack:<fila>, extend:<fila>:<segundos> */
    public array $events = [];

    /** @var list<list<string>> */
    public array $reserveCalls = [];

    private int $sequence = 0;

    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $queue, array $payload): void
    {
        $this->events[] = 'push:' . $queue;
        $this->items[$queue][] = $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function seed(string $queue, array $payload): void
    {
        $this->items[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        return ($this->items[$queue] ?? []) === [] ? null : array_shift($this->items[$queue]);
    }

    public function reserve(array $queues): ?ReservedJob
    {
        $this->reserveCalls[] = $queues;

        foreach ($queues as $queue) {
            if (($this->items[$queue] ?? []) === []) {
                continue;
            }

            $job = new ReservedJob($queue, array_shift($this->items[$queue]), 'token-' . (++$this->sequence));
            $this->processing[$job->token()] = $job;
            $this->events[] = 'reserve:' . $queue;

            return $job;
        }

        return null;
    }

    public function acknowledge(ReservedJob $job): void
    {
        $this->events[] = 'ack:' . $job->queue();
        unset($this->processing[$job->token()]);
    }

    public function extend(ReservedJob $job, ?int $seconds = null): void
    {
        $this->events[] = 'extend:' . $job->queue() . ':' . ($seconds ?? 'default');
    }

    public function recoverExpired(string $queue): int
    {
        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(string $queue): array
    {
        return $this->items[$queue] ?? [];
    }
}
