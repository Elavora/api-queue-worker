# elavora/api-queue-worker

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-queue-worker.svg?style=flat-square)](https://packagist.org/packages/elavora/api-queue-worker)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-queue-worker.svg?style=flat-square)](https://packagist.org/packages/elavora/api-queue-worker)
[![Composer Quality](https://github.com/Elavora/api-queue-worker/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-queue-worker/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-queue-worker/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-queue-worker/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-queue-worker.svg?style=flat-square)](LICENSE)
Worker opcional para consumir tarefas publicadas em qualquer implementacao do
contrato `Elavora\Api\Framework\Contracts\Queue`.

```php
$payload = new TaskPayload('emails.send', ['email' => 'user@example.com']);
$queue->push('default', $payload->toArray());

$registry = (new TaskRegistry())->add('emails.send', new SendEmailTaskHandler());
$worker = new QueueWorker($queue, $registry);
$worker->workOnce('default');
```

Use `TaskPayload::fromArray()` para reconstruir payloads serializados pela fila.
O worker reencaminha tarefas com falha enquanto `maxAttempts` nao for atingido.
