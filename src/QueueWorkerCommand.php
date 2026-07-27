<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\QueueWorker;

use Closure;

final class QueueWorkerCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILED = 1;
    public const EXIT_INVALID = 2;

    /** @var Closure(string): void */
    private readonly Closure $output;

    /** @var Closure(string): void */
    private readonly Closure $errorOutput;

    /** @var Closure(int): void */
    private readonly Closure $sleeper;

    /**
     * @param QueueWorker $worker Worker responsavel pelo processamento.
     * @param int $defaultSleepSeconds Pausa padrao quando a fila estiver vazia.
     * @param (Closure(string): void)|null $output Saida normal, substituivel em testes.
     * @param (Closure(string): void)|null $errorOutput Saida de erro, substituivel em testes.
     * @param (Closure(int): void)|null $sleeper Pausa, substituivel em testes.
     */
    public function __construct(
        private readonly QueueWorker $worker,
        private readonly int $defaultSleepSeconds = 1,
        ?Closure $output = null,
        ?Closure $errorOutput = null,
        ?Closure $sleeper = null
    ) {
        $this->output = $output ?? static function (string $message): void {
            fwrite(STDOUT, $message);
        };
        $this->errorOutput = $errorOutput ?? static function (string $message): void {
            fwrite(STDERR, $message);
        };
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * Executa o loop de consumo da fila.
     *
     * @param list<string> $argv Argumentos de linha de comando.
     * @return int Codigo de saida do processo.
     */
    public function run(array $argv): int
    {
        $options = $this->options($argv);
        $queue = $options['queue'];
        $sleepSeconds = $options['sleep'];
        $maxJobs = $options['max_jobs'];
        $once = $options['once'];
        $processedJobs = 0;

        while (true) {
            $result = $this->worker->workOnce($queue);

            if ($result->isInvalid()) {
                ($this->errorOutput)(sprintf("Mensagem invalida na fila %s.\n", $queue));

                return self::EXIT_INVALID;
            }

            if ($result->isFailed()) {
                ($this->errorOutput)(sprintf("Tarefa falhou definitivamente na fila %s.\n", $queue));

                return self::EXIT_FAILED;
            }

            if ($result->isProcessed()) {
                ($this->output)(sprintf("Tarefa processada na fila %s.\n", $queue));
                $processedJobs++;
            }

            if ($result->isRetried()) {
                ($this->output)(sprintf("Tarefa reenfileirada na fila %s.\n", $queue));
                $processedJobs++;
            }

            if ($once || ($maxJobs > 0 && $processedJobs >= $maxJobs)) {
                if ($result->isIdle()) {
                    ($this->output)(sprintf("Fila %s vazia.\n", $queue));
                }

                return self::EXIT_SUCCESS;
            }

            if ($result->isIdle()) {
                ($this->sleeper)($sleepSeconds);
            }
        }
    }

    /**
     * @param list<string> $argv
     * @return array{queue: string, sleep: int, max_jobs: int, once: bool}
     */
    private function options(array $argv): array
    {
        $options = [
            'queue' => getenv('QUEUE_NAME') ?: 'default',
            'sleep' => $this->defaultSleepSeconds,
            'max_jobs' => 0,
            'once' => false,
        ];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--once') {
                $options['once'] = true;
                continue;
            }

            if (str_starts_with($argument, '--queue=')) {
                $options['queue'] = substr($argument, 8);
                continue;
            }

            if (str_starts_with($argument, '--sleep=')) {
                $options['sleep'] = max(0, (int) substr($argument, 8));
                continue;
            }

            if (str_starts_with($argument, '--max-jobs=')) {
                $options['max_jobs'] = max(0, (int) substr($argument, 11));
            }
        }

        return $options;
    }
}
