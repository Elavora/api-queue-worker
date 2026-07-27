<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\QueueWorker;

/**
 * Recebe mensagens que nao podem mais seguir pelo fluxo normal do worker.
 */
interface TerminalFailureHandler
{
    /**
     * Persiste ou encaminha um payload invalido ou uma tarefa definitivamente falha.
     */
    public function handle(WorkerResult $result): void;
}
