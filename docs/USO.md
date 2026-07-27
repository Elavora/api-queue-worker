# Guia de uso

## Instalacao

```bash
composer require elavora/api-queue-worker:^1.0
```

Requisitos: PHP `>=8.3` e `elavora/api-framework` `^1.0`.

## Fluxo executavel

`QueueWorker` e um servico, nao uma extensao. Ele recebe uma implementacao de
`Queue` e um `TaskRegistry`.

```php
use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Framework\Contracts\Queue;

final class InMemoryQueue implements Queue
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $items = [];

    public function push(string $queue, array $payload): void
    {
        $this->items[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        if (($this->items[$queue] ?? []) === []) {
            return null;
        }

        return array_shift($this->items[$queue]);
    }
}

$queue = new InMemoryQueue();
$runs = 0;
$tasks = (new TaskRegistry())->add(
    'reports.generate',
    static function (TaskPayload $payload) use (&$runs): void {
        $runs++;
        if ($runs === 1) {
            throw new RuntimeException('Servico temporariamente indisponivel.');
        }
    }
);
$worker = new QueueWorker($queue, $tasks);

$queue->push(
    'reports',
    (new TaskPayload('reports.generate', ['report_id' => 42], maxAttempts: 3))->toArray()
);

$retry = $worker->workOnce('reports');
assert($retry->isRetried());
assert($retry->payload()?->attempts() === 1);

$success = $worker->workOnce('reports');
assert($success->isProcessed());

$idle = $worker->workOnce('reports');
assert($idle->isIdle());
```

Cada falha de execucao gera uma nova `TaskPayload` com `attempts + 1`. A tarefa
e reenfileirada enquanto `attempts < maxAttempts`. O TTL ou a conexao da fila,
quando existirem, pertencem ao adapter de `Queue`, nao ao worker.

## Resultados terminais

`workOnce()` sempre retorna um `WorkerResult`:

| Estado | Significado |
| --- | --- |
| `idle` | A fila estava vazia e nada foi removido |
| `processed` | O handler terminou com sucesso |
| `retried` | O handler falhou e a tarefa foi reenfileirada |
| `invalid` | O payload removido nao pode virar `TaskPayload` |
| `failed` | A tarefa atingiu `maxAttempts` |

Resultados `invalid` preservam a fila, a causa e o payload bruto para tratamento
explicito. Resultados `failed` preservam a tarefa, a ultima causa e a contagem
final em `payload()->attempts()`. O pacote nao registra automaticamente o
payload nem a excecao.

Use um handler terminal para persistir em dead-letter storage sem acoplar o
worker a Redis:

```php
use Elavora\Api\Extension\QueueWorker\TerminalFailureHandler;
use Elavora\Api\Extension\QueueWorker\WorkerResult;

final class ApplicationDeadLetterHandler implements TerminalFailureHandler
{
    public function handle(WorkerResult $result): void
    {
        // Persista apenas os campos permitidos pela politica da aplicacao.
    }
}

$worker = new QueueWorker($queue, $tasks, new ApplicationDeadLetterHandler());
```

O handler e chamado uma vez quando o worker produz `invalid` ou `failed`.
Excecoes lancadas pelo proprio handler terminal sao propagadas ao chamador.

## Comando

Um entrypoint `worker.php` pode terminar com:

```php
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;

exit((new QueueWorkerCommand($worker))->run($argv));
```

Opcoes:

- `--queue=reports`: fila; o padrao vem de `QUEUE_NAME` ou `default`.
- `--sleep=1`: pausa entre consultas vazias.
- `--max-jobs=100`: encerra apos processar ou reenfileirar 100 tarefas.
- `--once`: executa uma unica consulta, inclusive quando a fila estiver vazia.

Exemplo:

```bash
php worker.php --queue=reports --sleep=1 --max-jobs=100
php worker.php --queue=reports --once
```

Os codigos sao `0` para fila vazia, sucesso ou retry, `1` para falha definitiva
e `2` para payload invalido. A saida identifica estado e fila, sem imprimir o
payload.

## Garantia de entrega

O contrato atual usa `pop()`: a mensagem e removida antes da validacao e da
execucao. Retry e falhas terminais ficam trataveis durante o processo, mas uma
queda entre `pop()` e o reenvio/persistencia ainda pode perder a mensagem. Uma
garantia mais forte exige um adapter com reserva e confirmacao ou outra
infraestrutura de fila; ela nao pode ser implementada somente pelo worker.

Conexoes e credenciais devem vir da configuracao externa do adapter de `Queue`.

## Qualidade

```bash
composer validate --strict --no-check-publish
composer lint
composer analyse
composer test
composer check
```

`composer check` executa lint portatil, PHPStan nivel 8 e PHPUnit.
