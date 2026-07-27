<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\QueueWorker;

use Elavora\Api\Framework\Contracts\Queue;
use Throwable;

final class QueueWorker
{
    /**
     * @param Queue $queue Fila usada para buscar e reenfileirar tarefas.
     * @param TaskRegistry $tasks Registro de handlers disponiveis.
     * @param TerminalFailureHandler|null $terminalFailures Destino opcional para falhas terminais.
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly TaskRegistry $tasks,
        private readonly ?TerminalFailureHandler $terminalFailures = null
    ) {
    }

    /**
     * Processa uma unica mensagem da fila.
     *
     * @param string $queue Nome da fila consumida.
     */
    public function workOnce(string $queue): WorkerResult
    {
        $payload = $this->queue->pop($queue);

        if ($payload === null) {
            return WorkerResult::idle($queue);
        }

        try {
            $taskPayload = TaskPayload::fromArray($payload);
        } catch (Throwable $error) {
            return $this->terminal(WorkerResult::invalid($queue, $payload, $error));
        }

        try {
            $this->tasks->handle($taskPayload);

            return WorkerResult::processed($queue, $taskPayload);
        } catch (Throwable $error) {
            $failedPayload = $taskPayload->withRecordedFailure();

            if ($failedPayload->canRetry()) {
                $this->queue->push($queue, $failedPayload->toArray());

                return WorkerResult::retried($queue, $failedPayload, $error);
            }

            return $this->terminal(WorkerResult::failed($queue, $failedPayload, $error));
        }
    }

    private function terminal(WorkerResult $result): WorkerResult
    {
        $this->terminalFailures?->handle($result);

        return $result;
    }
}
