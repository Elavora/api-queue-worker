# Guia de uso

Worker opcional para consumir tarefas publicadas em qualquer implementacao do

## Instalacao

```bash
composer require elavora/api-queue-worker
```

## Quando usar

- Publicar ou consumir tarefas assincronas.
- Esconder detalhes do backend de fila atras dos contratos do framework.
- Reutilizar workers e handlers em ambientes diferentes.

## Exemplo rapido

```php
use Elavora\Api\Extension\QueueWorker\QueueWorker;

$application->extend(new QueueWorker([
    'prefix' => 'app:queue:',
]));
```

## Principais pontos de entrada

- `Elavora\Api\Extension\QueueWorker\QueueWorker`
- `Elavora\Api\Extension\QueueWorker\QueueWorkerCommand`
- `Elavora\Api\Extension\QueueWorker\TaskHandler`
- `Elavora\Api\Extension\QueueWorker\TaskPayload`
- `Elavora\Api\Extension\QueueWorker\TaskRegistry`

## Dependencias de runtime

- `elavora/api-framework` `^0.3.1`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-queue-worker" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-queue-worker" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.