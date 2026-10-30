# Guia de uso

Worker opcional para consumir tarefas publicadas em qualquer implementação do contrato
`Elavora\Api\Framework\Contracts\Queue` do framework Elavora.

Este guia é a especificação do pacote. Tudo o que um teste precisa saber está aqui.

## Versão e compatibilidade

| Item | Valor |
|---|---|
| Versão deste pacote | `1.0.0` (primeira versão compatível com o framework 2) |
| `elavora/api-framework` | `^2.0` |
| PHP | `>=8.3` |

A `1.0.0` já foi publicada (tag legada `v1.0.0`, marcada como pré-release no GitHub). As versões
seguintes saem do fluxo central de release: veja [Versões e release](AUTOMACAO.md#versões-e-release).

Não existe compatibilidade com o framework `0.x` nem `1.x`.

A `0.2.0` pedia `elavora/api-framework` `^0.3.1`, mas não funciona com ele: as tags `0.3.x` do
framework usam o namespace `Elavora\Framework\...`, e este pacote sempre usou
`Elavora\Api\Framework\Contracts\Queue`, que só existe no framework 2. Ou seja, a `1.0.0` é a
primeira versão instalável de fato.

### `composer.json`

O `composer.json` do pacote deve ter:

- `require.php` = `">=8.3"`
- `require.elavora/api-framework` = `"^2.0"`
- `extra.branch-alias.dev-main` = `"1.0-dev"`
- `autoload.psr-4` = `"Elavora\\Api\\Extension\\QueueWorker\\": "src/"` (sem mudança)
- `require-dev.phpunit/phpunit` = `"^10.5 || ^11.0 || ^12.0"` (sem mudança)
- `scripts.test` = `"phpunit -c phpunit.xml"` (sem mudança)
- `require-dev`, `scripts` e `config` de qualidade (`elavora/code-standards`, PHPStan, `lint`, `analyse`, `check`):
  veja [`composer.json` em Automação](AUTOMACAO.md#composerjson)
- nenhum `repositories` (nem `path`, nem `vcs`)
- `composer validate --strict --no-check-publish` sem erros.

### O que muda com o framework 2

Para este pacote, nada muda no código. Ele só usa um tipo do framework:

- `Elavora\Api\Framework\Contracts\Queue`, com `push(string $queue, array $payload): void`
  e `pop(string $queue): ?array`. `pop()` devolve `null` quando a fila está vazia.

A mudança real é de versão: o framework 2 exige PHP 8.3. Por isso este pacote também passa a exigir PHP 8.3.

Este pacote não registra extensão no framework e não lê variáveis de ambiente, com uma exceção:
`QUEUE_NAME`, lida pelo `QueueWorkerCommand` (veja abaixo). A fila (`Queue`) vem de outra extensão,
por exemplo o `elavora/api-queue-redis`.

## Instalação

```bash
composer require elavora/api-queue-worker:^1.0
```

## Exemplo rápido

Publicar uma tarefa e processá-la uma vez:

```php
use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Framework\Contracts\Queue;

$queue = $application->container()->get(Queue::class);

$queue->push('default', (new TaskPayload('emails.send', ['email' => 'ana@exemplo.com']))->toArray());

$tasks = (new TaskRegistry())->add('emails.send', static function (TaskPayload $payload): void {
    // enviar o e-mail para $payload->data()['email']
});

$result = (new QueueWorker($queue, $tasks))->workOnce('default');
$result->isProcessed(); // true
```

Rodar o worker em loop num script de linha de comando (`worker.php` do projeto):

```php
use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Queue;

require __DIR__ . '/vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/core/bootstrap/app.php';

/** @var TaskRegistry $tasks */
$tasks = require __DIR__ . '/app/Worker/tasks.php';

$command = new QueueWorkerCommand(new QueueWorker(
    queue: $app->container()->get(Queue::class),
    tasks: $tasks,
));

exit($command->run($argv));
```

```bash
php worker.php --queue=emails --sleep=2 --max-jobs=100
```

## API pública

Namespace base: `Elavora\Api\Extension\QueueWorker`. Todas as classes são `final`.

### TaskPayload

Classe `TaskPayload`, implementa `JsonSerializable`. Imutável: os métodos `with...` devolvem
uma nova instância.

```php
public function __construct(string $task, array $data = [], int $attempts = 0, int $maxAttempts = 3);
public static function fromArray(array $payload): self;
public function task(): string;
public function data(): array;
public function attempts(): int;
public function maxAttempts(): int;
public function withRecordedFailure(): self;
public function canRetry(): bool;
public function toArray(): array;
public function jsonSerialize(): array;
```

O construtor aceita argumentos nomeados (`task:`, `data:`, `attempts:`, `maxAttempts:`).

#### Validação do construtor

Toda violação lança `InvalidArgumentException`, na ordem abaixo (a primeira que falhar vence):

| Regra | Mensagem |
|---|---|
| `task` é `''` | `O nome da tarefa nao pode ser vazio.` |
| `attempts` < 0 | `A quantidade de tentativas nao pode ser negativa.` |
| `maxAttempts` < 1 | `A quantidade maxima de tentativas deve ser maior que zero.` |
| algum valor de `data`, em qualquer nível, não é `null`, `bool`, `int`, `float`, `string` nem array | `Payload de tarefa aceita apenas valores escalares, nulos e arrays.` |

`task` com só espaços (`' '`) é aceito: só a string vazia é recusada.
As chaves de `data` não são validadas.

| Entrada | Resultado |
|---|---|
| `new TaskPayload('emails.send')` | válido; `data()` = `[]`, `attempts()` = `0`, `maxAttempts()` = `3` |
| `new TaskPayload('a', ['x' => ['y' => [1, 2.5, true, null, 'z']]])` | válido |
| `new TaskPayload('a', attempts: 3, maxAttempts: 3)` | válido (`attempts` pode ser igual ou maior que `maxAttempts`) |
| `new TaskPayload('')` | `InvalidArgumentException`, `O nome da tarefa nao pode ser vazio.` |
| `new TaskPayload('a', attempts: -1)` | `InvalidArgumentException`, `A quantidade de tentativas nao pode ser negativa.` |
| `new TaskPayload('a', maxAttempts: 0)` | `InvalidArgumentException`, `A quantidade maxima de tentativas deve ser maior que zero.` |
| `new TaskPayload('a', ['obj' => new stdClass()])` | `InvalidArgumentException`, `Payload de tarefa aceita apenas valores escalares, nulos e arrays.` |
| `new TaskPayload('a', ['lista' => [fopen('php://memory', 'r')]])` | mesma exceção e mensagem (recurso aninhado) |

#### fromArray

Reconstrói o payload lido da fila. Formato esperado: as chaves de `toArray()`.

| Chave | Ausente | Tipo exigido | Mensagem quando o tipo está errado |
|---|---|---|---|
| `task` | erro | `string` | `Payload de tarefa deve conter task como string.` |
| `data` | `[]` | `array` | `Payload de tarefa deve conter data como array.` |
| `attempts` | `0` | `int` | `Payload de tarefa deve conter attempts como inteiro.` |
| `max_attempts` | `3` | `int` | `Payload de tarefa deve conter max_attempts como inteiro.` |

- Todas as falhas lançam `InvalidArgumentException`.
- As chaves são checadas nesta ordem: `task`, `data`, `attempts`, `max_attempts`.
- Valor `null` conta como ausente (o código usa `??`): `task` `null` dá a mensagem de `task`;
  `data` `null` vira `[]`; `attempts` `null` vira `0`; `max_attempts` `null` vira `3`.
- Não há conversão de tipo: `'2'` em `attempts` é erro, `2.0` também.
- Depois dos tipos, valem as regras do construtor (`task` vazio, `attempts` negativo etc.).
- Chaves extras são ignoradas.

| Entrada | Resultado |
|---|---|
| `['task' => 'a']` | `task()` = `'a'`, `data()` = `[]`, `attempts()` = `0`, `maxAttempts()` = `3` |
| `['task' => 'a', 'data' => ['k' => 1], 'attempts' => 2, 'max_attempts' => 5, 'extra' => true]` | tudo preservado; `extra` ignorada |
| `[]` | `InvalidArgumentException`, `Payload de tarefa deve conter task como string.` |
| `['task' => 10]` | mesma mensagem de `task` |
| `['task' => 'a', 'data' => 'x']` | `Payload de tarefa deve conter data como array.` |
| `['task' => 'a', 'attempts' => '1']` | `Payload de tarefa deve conter attempts como inteiro.` |
| `['task' => 'a', 'max_attempts' => 2.0]` | `Payload de tarefa deve conter max_attempts como inteiro.` |
| `['task' => 'a', 'data' => null, 'attempts' => null, 'max_attempts' => null]` | válido; `data()` = `[]`, `attempts()` = `0`, `maxAttempts()` = `3` |
| `['task' => '']` | `O nome da tarefa nao pode ser vazio.` |
| `['task' => 'a', 'max_attempts' => 0]` | `A quantidade maxima de tentativas deve ser maior que zero.` |

`TaskPayload::fromArray($p->toArray())` devolve um payload com os mesmos quatro valores de `$p`.

#### Tentativas

- `withRecordedFailure()` devolve uma nova instância com `attempts + 1`. O resto não muda.
  A instância original continua igual.
- `canRetry()` é `attempts() < maxAttempts()`.

| Payload | `canRetry()` |
|---|---|
| `attempts: 0, maxAttempts: 3` | `true` |
| `attempts: 2, maxAttempts: 3` | `true` |
| `attempts: 3, maxAttempts: 3` | `false` |
| `attempts: 5, maxAttempts: 3` | `false` |

#### Serialização

`toArray()` e `jsonSerialize()` devolvem o mesmo array, com as chaves nesta ordem:

```php
['task' => 'emails.send', 'data' => ['email' => 'ana@exemplo.com'], 'attempts' => 0, 'max_attempts' => 3]
```

`json_encode(new TaskPayload('a', ['k' => 1]))` dá `{"task":"a","data":{"k":1},"attempts":0,"max_attempts":3}`.

### TaskHandler

Interface para handlers em classe.

```php
public function handle(TaskPayload $payload): void;
```

### TaskRegistry

Liga o nome da tarefa ao handler que a executa.

```php
public function add(string $task, TaskHandler|callable $handler): self;
public function handle(TaskPayload $payload): void;
```

#### add

- Devolve a própria instância (`$registry->add(...) === $registry`), para encadear.
- `task` igual a `''` lança `RuntimeException` com a mensagem `O nome da tarefa nao pode ser vazio.`
  (atenção: aqui é `RuntimeException`, não `InvalidArgumentException` como no `TaskPayload`).
- Registrar de novo o mesmo nome substitui o handler anterior.

#### handle

- Procura o handler pelo `task()` do payload, com comparação exata (diferencia maiúsculas).
- Sem handler registrado: `RuntimeException` com a mensagem
  `Nenhum handler registrado para a tarefa <nome>.` Ex.: `Nenhum handler registrado para a tarefa emails.send.`
- Handler `TaskHandler`: chama `$handler->handle($payload)`. Se o objeto também tiver `__invoke`,
  vale o `handle()`.
- Handler callable (closure, `[objeto, 'metodo']`, nome de função): chama `$handler($payload)`.
  O retorno é ignorado.
- Exceção lançada pelo handler sobe sem ser tratada.

### QueueWorker

Processa uma mensagem por chamada.

```php
public function __construct(Queue $queue, TaskRegistry $tasks);
public function workOnce(string $queue): WorkerResult;
```

O construtor aceita argumentos nomeados (`queue:`, `tasks:`).

#### workOnce

1. Chama `pop($queue)` na fila uma vez.
2. `pop()` devolveu `null` → devolve `WorkerResult::idle()`. Nada mais acontece.
3. Converte com `TaskPayload::fromArray()`. Se o payload for inválido, a `InvalidArgumentException`
   sobe para quem chamou e a mensagem já foi retirada da fila (não volta).
4. Chama `TaskRegistry::handle()`.
   - Sem exceção → `WorkerResult::processed($payload)`, com o payload lido da fila.
   - Com qualquer `Throwable` (inclusive "nenhum handler registrado"), o worker registra a falha com
     `withRecordedFailure()`:
     - `canRetry()` verdadeiro → faz `push($queue, $falhou->toArray())` na **mesma** fila (vai para o
       fim dela) e devolve `WorkerResult::retried($falhou, $erro)`.
     - `canRetry()` falso → não faz `push` e devolve `WorkerResult::failed($falhou, $erro)`.
       A mensagem é descartada; não existe fila de falhas.

Na prática, o handler roda no máximo `maxAttempts` vezes. Com `maxAttempts = 3` e um handler que
sempre falha: 1ª execução → `retried` (attempts 1), 2ª → `retried` (attempts 2), 3ª → `failed` (attempts 3).

| Cenário (fila falsa em memória) | Resultado |
|---|---|
| fila vazia | `isIdle()`; nenhum `push` |
| tarefa `emails.send` com handler que funciona | `isProcessed()`; `payload()->data()` igual ao publicado; `error()` = `null`; fila vazia depois |
| `maxAttempts: 2`, handler que sempre falha, 1ª chamada | `isRetried()`; `payload()->attempts()` = `1`; `error()` é a exceção do handler; a fila tem a mensagem com `attempts` = `1` |
| mesmo cenário, 2ª chamada | `isFailed()`; `payload()->attempts()` = `2`; fila vazia |
| tarefa sem handler registrado, `maxAttempts: 1` | `isFailed()`; `error()` é `RuntimeException` `Nenhum handler registrado para a tarefa ...` |
| mensagem `['task' => 10]` na fila | `InvalidArgumentException` sobe; fila vazia depois |

### WorkerResult

Resultado de `workOnce()`. O construtor é privado; use as fábricas.

```php
public static function idle(): self;
public static function processed(TaskPayload $payload): self;
public static function retried(TaskPayload $payload, Throwable $error): self;
public static function failed(TaskPayload $payload, Throwable $error): self;
public function isIdle(): bool;
public function isProcessed(): bool;
public function isRetried(): bool;
public function isFailed(): bool;
public function payload(): ?TaskPayload;
public function error(): ?Throwable;
```

Só um dos `is...()` é verdadeiro em cada resultado.

| Fábrica | `payload()` | `error()` |
|---|---|---|
| `idle()` | `null` | `null` |
| `processed($p)` | `$p` | `null` |
| `retried($p, $e)` | `$p` | `$e` |
| `failed($p, $e)` | `$p` | `$e` |

### QueueWorkerCommand

Loop de consumo para scripts de linha de comando.

```php
public function __construct(QueueWorker $worker, int $defaultSleepSeconds = 1);
public function run(array $argv): int;
```

Valor negativo em `defaultSleepSeconds` fica fora desta especificação.

#### Opções

`run()` recebe o `$argv` do PHP: o primeiro item (nome do script) é ignorado.

| Opção | Padrão | Regra |
|---|---|---|
| `--queue=<nome>` | variável de ambiente `QUEUE_NAME`; se vazia ou ausente, `default` | o texto depois do `=`, sem validação (`--queue=` dá fila `''`) |
| `--sleep=<segundos>` | `defaultSleepSeconds` do construtor | convertido com `(int)`; negativo vira `0`; `abc` vira `0`; `2s` vira `2` |
| `--max-jobs=<n>` | `0` (sem limite) | convertido com `(int)`; negativo vira `0` |

- Argumentos desconhecidos são ignorados.
- Repetir uma opção: vale a última.
- A forma com espaço (`--queue emails`) não é reconhecida; só a forma com `=`.

#### Loop

A cada volta, chama `workOnce(<fila>)` e:

1. `failed` → escreve `Tarefa falhou definitivamente na fila <fila>.` e quebra de linha no STDERR e
   devolve `1`. O loop para na primeira falha definitiva.
2. `processed` ou `retried` → soma 1 ao contador de tarefas.
3. `max-jobs` > 0 e contador >= `max-jobs` → devolve `0`.
4. `idle` → `sleep(<segundos>)` e volta ao passo 1. Fila vazia não conta para o `max-jobs`.

Exceções que saem do `workOnce()` (payload inválido) não são tratadas e sobem para quem chamou `run()`.

Sem `max-jobs`, o loop só termina por falha definitiva ou exceção. Com `max-jobs` e a fila vazia,
ele continua dormindo e tentando de novo. Nos testes, publique tarefas suficientes e use `--sleep=0`.

| Cenário (fila falsa, `--sleep=0`) | Resultado |
|---|---|
| 2 tarefas que funcionam, `--max-jobs=2` | devolve `0`; 2 handlers executados |
| 3 tarefas que funcionam, `--max-jobs=2` | devolve `0`; sobra 1 tarefa na fila |
| 1 tarefa que sempre falha, `maxAttempts: 2`, `--max-jobs=1` | devolve `0` (o `retried` contou); a tarefa volta para a fila com `attempts` = `1` |
| 1 tarefa que sempre falha, `maxAttempts: 1` | devolve `1`; STDERR com `Tarefa falhou definitivamente na fila default.` |
| `QUEUE_NAME=emails`, sem `--queue`, 1 tarefa em `emails`, `--max-jobs=1` | consome da fila `emails` |
| `QUEUE_NAME=emails` e `--queue=sms` | consome da fila `sms` |

## Validação local

Como rodar os testes, o lint e o PHPStan, e como reproduzir o CI, está em
[Desenvolvimento e testes](DESENVOLVIMENTO.md). Não é preciso repositório `path`: o framework `2.0` já está no
Packagist.

## Observações

- Mantenha regras de produto fora deste pacote. Os handlers ficam no projeto.
- Prefira nomes de tarefa pesquisáveis e estáveis, como `emails.send` ou `documents.process`.
- O pacote não traz fila própria: instale uma extensão que registre `Queue` no container.
