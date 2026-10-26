# elavora/api-queue-worker

Worker opcional para consumir tarefas publicadas em qualquer implementação do
contrato `Elavora\Api\Framework\Contracts\Queue`.

Versão `1.0`: exige `elavora/api-framework` `^2.0` e PHP `>=8.3`.
É a primeira versão que funciona de fato: a `0.2.0` pedia o framework `^0.3.1`,
que não tem o contrato `Queue` neste namespace.

```php
$payload = new TaskPayload('emails.send', ['email' => 'ana@exemplo.com']);
$queue->push('default', $payload->toArray());

$registry = (new TaskRegistry())->add('emails.send', new SendEmailTaskHandler());
$worker = new QueueWorker($queue, $registry);
$worker->workOnce('default');
```

O worker reenfileira a tarefa que falhou enquanto `maxAttempts` não for atingido.
Para rodar em loop num script, use `QueueWorkerCommand` com as opções `--queue=`,
`--sleep=` e `--max-jobs=`.

O pacote não traz fila própria: instale uma extensão que registre `Queue` no
container, como o `elavora/api-queue-redis`.

Especificação completa, exemplos e validação local: [docs/USO.md](docs/USO.md).
