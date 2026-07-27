<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\QueueWorker;

use Throwable;

final class WorkerResult
{
    private const IDLE = 'idle';
    private const PROCESSED = 'processed';
    private const RETRIED = 'retried';
    private const INVALID = 'invalid';
    private const FAILED = 'failed';

    /**
     * @param 'idle'|'processed'|'retried'|'invalid'|'failed' $status
     * @param array<string, mixed>|null $rawPayload Payload original preservado para mensagens invalidas.
     */
    private function __construct(
        private readonly string $status,
        private readonly string $queue,
        private readonly ?TaskPayload $payload = null,
        private readonly ?Throwable $error = null,
        private readonly ?array $rawPayload = null
    ) {
    }

    /**
     * Cria resultado para fila vazia.
     */
    public static function idle(string $queue): self
    {
        return new self(self::IDLE, $queue);
    }

    /**
     * Cria resultado para tarefa processada com sucesso.
     */
    public static function processed(string $queue, TaskPayload $payload): self
    {
        return new self(self::PROCESSED, $queue, $payload);
    }

    /**
     * Cria resultado para tarefa reenfileirada apos falha.
     */
    public static function retried(string $queue, TaskPayload $payload, Throwable $error): self
    {
        return new self(self::RETRIED, $queue, $payload, $error);
    }

    /**
     * Cria resultado para mensagem que nao representa uma tarefa valida.
     *
     * @param array<string, mixed> $rawPayload
     */
    public static function invalid(string $queue, array $rawPayload, Throwable $error): self
    {
        return new self(self::INVALID, $queue, error: $error, rawPayload: $rawPayload);
    }

    /**
     * Cria resultado para falha definitiva.
     */
    public static function failed(string $queue, TaskPayload $payload, Throwable $error): self
    {
        return new self(self::FAILED, $queue, $payload, $error);
    }

    /**
     * Retorna o estado pesquisavel do resultado.
     *
     * @return 'idle'|'processed'|'retried'|'invalid'|'failed'
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Retorna a fila relacionada ao resultado.
     */
    public function queue(): string
    {
        return $this->queue;
    }

    /**
     * Indica que nenhuma tarefa foi encontrada.
     */
    public function isIdle(): bool
    {
        return $this->status === self::IDLE;
    }

    /**
     * Indica que a tarefa foi processada.
     */
    public function isProcessed(): bool
    {
        return $this->status === self::PROCESSED;
    }

    /**
     * Indica que a tarefa foi reenfileirada.
     */
    public function isRetried(): bool
    {
        return $this->status === self::RETRIED;
    }

    /**
     * Indica que a mensagem removida da fila nao representa uma tarefa valida.
     */
    public function isInvalid(): bool
    {
        return $this->status === self::INVALID;
    }

    /**
     * Indica que a tarefa falhou definitivamente.
     */
    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * Retorna o payload relacionado ao resultado, quando existir.
     */
    public function payload(): ?TaskPayload
    {
        return $this->payload;
    }

    /**
     * Retorna o erro relacionado ao resultado, quando existir.
     */
    public function error(): ?Throwable
    {
        return $this->error;
    }

    /**
     * Retorna o payload original somente quando a mensagem for invalida.
     *
     * @return array<string, mixed>|null
     */
    public function rawPayload(): ?array
    {
        return $this->rawPayload;
    }
}
