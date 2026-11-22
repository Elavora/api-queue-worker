<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\QueueWorker;

use Elavora\Api\Framework\Contracts\AcknowledgesJobs;
use Elavora\Api\Framework\Contracts\Queue;
use Elavora\Api\Framework\Queue\ReservedJob;
use InvalidArgumentException;
use Throwable;

final class QueueWorker
{
    /** Reserva em processamento (so com filas AcknowledgesJobs). */
    private ?ReservedJob $current = null;

    /**
     * @param Queue $queue Fila usada para buscar e reenfileirar tarefas.
     * @param TaskRegistry $tasks Registro de handlers disponiveis.
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly TaskRegistry $tasks,
    ) {}

    /**
     * Processa uma unica mensagem da fila.
     *
     * @param string $queue Nome da fila consumida.
     */
    public function workOnce(string $queue): WorkerResult
    {
        return $this->workOnceFrom([$queue]);
    }

    /**
     * Processa uma mensagem da primeira fila, em ordem de prioridade, que tiver trabalho.
     *
     * @param list<string> $queues Filas em ordem de prioridade.
     */
    public function workOnceFrom(array $queues): WorkerResult
    {
        if ($queues === []) {
            throw new InvalidArgumentException('A lista de filas nao pode ser vazia.');
        }

        if ($this->queue instanceof AcknowledgesJobs) {
            $job = $this->queue->reserve($queues);

            if ($job === null) {
                return WorkerResult::idle();
            }

            try {
                $taskPayload = TaskPayload::fromArray($job->payload());
            } catch (Throwable $error) {
                // Payload invalido nunca vai funcionar: descarta para nao voltar a fila em loop.
                $this->queue->acknowledge($job);

                throw $error;
            }

            $this->current = $job;

            try {
                $result = $this->handle($job->queue(), $taskPayload);
            } finally {
                $this->current = null;
            }

            // So confirma depois do reenfileiramento: se o push falhar, a mensagem volta pelo prazo.
            $this->queue->acknowledge($job);

            return $result;
        }

        foreach ($queues as $queue) {
            $payload = $this->queue->pop($queue);

            if ($payload !== null) {
                return $this->handle($queue, TaskPayload::fromArray($payload));
            }
        }

        return WorkerResult::idle();
    }

    /**
     * Renova o prazo de visibilidade da mensagem em processamento (heartbeat para jobs longos).
     *
     * Nao faz nada sem mensagem em processamento ou com uma fila sem AcknowledgesJobs.
     */
    public function extendCurrent(?int $seconds = null): void
    {
        if ($this->current !== null && $this->queue instanceof AcknowledgesJobs) {
            $this->queue->extend($this->current, $seconds);
        }
    }

    private function handle(string $queue, TaskPayload $taskPayload): WorkerResult
    {
        try {
            $this->tasks->handle($taskPayload);

            return WorkerResult::processed($taskPayload);
        } catch (Throwable $error) {
            $failedPayload = $taskPayload->withRecordedFailure();

            if ($failedPayload->canRetry()) {
                $this->queue->push($queue, $failedPayload->toArray());

                return WorkerResult::retried($failedPayload, $error);
            }

            return WorkerResult::failed($failedPayload, $error);
        }
    }
}
