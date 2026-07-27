# elavora/api-queue-worker

Worker opcional para consumir tarefas publicadas em qualquer implementacao do
contrato `Elavora\Api\Framework\Contracts\Queue`.

## Requisitos

- PHP `>=8.3`
- `elavora/api-framework` `^1.0`

```php
use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;

$payload = new TaskPayload('emails.send', ['email' => 'user@example.com']);
$queue->push('default', $payload->toArray());

$registry = (new TaskRegistry())->add(
    'emails.send',
    static function (TaskPayload $payload): void {
        // Execute o caso de uso da aplicacao.
    }
);
$worker = new QueueWorker($queue, $registry);
$result = $worker->workOnce('default');

if ($result->isProcessed()) {
    echo "Tarefa processada.\n";
}
```

Falhas recuperaveis sao reenfileiradas com `attempts` incrementado. Payloads
invalidos e tarefas que esgotaram `maxAttempts` produzem resultados terminais e
podem ser encaminhados por um `TerminalFailureHandler`, sem registrar o conteudo
bruto automaticamente.

Consulte [docs/USO.md](docs/USO.md) para o exemplo executavel, estados,
comando CLI e limites de entrega.
