# Automação do repositório

[README](../README.md) · Veja também: [Desenvolvimento e testes](DESENVOLVIMENTO.md) · [Guia de uso](USO.md)

Esta página é a especificação dos arquivos de automação, qualidade e release do repositório. Tudo o que um teste da
automação precisa conferir está aqui.

Nesta página, `<repo>` é o nome do repositório. Neste repositório, `<repo>` é `api-queue-worker`.

## Visão geral

Quase toda a automação vem do repositório central [`Elavora/.github`](https://github.com/Elavora/.github). Aqui ficam
só arquivos pequenos, os **chamadores**, que dizem qual versão da automação central usar. Uma correção na automação
central vale para todos os pacotes `api-*`, e cada repositório escolhe quando atualizar.

| Arquivo | Para que serve |
| --- | --- |
| `.github/workflows/quality.yml` | pipeline de qualidade do PR: formatação, lint, PHPStan, testes, cobertura e comentário no PR |
| `.github/workflows/release.yml` | versão, tag, GitHub Release com notas em português e aviso ao Packagist a cada merge |
| `.github/workflows/sync-labels.yml` | deixa as labels do repositório iguais ao catálogo central |
| `.github/workflows/codeql.yml` | análise de segurança dos workflows |
| `.github/workflows/dependency-review.yml` | aponta dependências novas com vulnerabilidade alta |
| `.github/workflows/automation.yml` | testes da automação (`tests/automation`) e actionlint |
| `.github/dependabot.yml` | PRs semanais de atualização do Composer e das actions |
| `.devcontainer/devcontainer.json` | ambiente de desenvolvimento ([como usar](DESENVOLVIMENTO.md)) |
| `.php-cs-fixer.dist.php` e `.editorconfig` | regras de estilo, vindas do pacote `elavora/code-standards` |
| `phpstan.neon` | configuração do PHPStan |
| `phpunit.xml` | configuração do PHPUnit, com a pasta medida pela cobertura |
| `.gitattributes` e `.gitignore` | fim de linha LF e arquivos que não vão para o Git |
| `LICENSE`, `SECURITY.md`, `.github/CODEOWNERS`, `.github/ISSUE_TEMPLATE/*` | arquivos de comunidade |

### Qual versão do `.github`

Todos os chamadores usam a **mesma** versão do `Elavora/.github`: a tag **`1.3.2`**, fixada pelo SHA completo do
commit da tag, com o número da versão em comentário:

```yaml
uses: Elavora/.github/.github/workflows/release.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2
```

- **Por que SHA:** uma tag movida por engano mudaria a automação sem nenhum PR. Com o SHA, toda mudança passa por
  revisão.
- **Por que o comentário `# 1.3.2`:** é por ele que uma pessoa sabe qual versão está em uso, e o Dependabot atualiza o
  SHA e o comentário juntos quando sai uma versão nova.
- **A versão aparece uma vez só.** Os chamadores não passam `automation_ref`: o workflow central descobre a própria
  versão pelo token OIDC do GitHub. Por isso cada job que chama o central concede `id-token: write`. Detalhes em
  [Versão da automação](https://github.com/Elavora/.github/blob/1.3.2/docs/versao-da-automacao.md).

### Nenhum check trava o merge

Nenhum check é obrigatório na proteção da `main`. Todos os workflows rodam em cada PR e mostram o resultado, mas quem
revisa decide o merge.

| Check no PR | Workflow |
| --- | --- |
| `quality / Qualidade PHP`, mais um comentário fixo no PR | `quality.yml` |
| `CodeQL` | `codeql.yml` |
| `Dependency Review` | `dependency-review.yml` |
| `Automação` | `automation.yml` |

A proteção da `main` é uma configuração do GitHub, fora dos arquivos do repositório. A retirada dos checks
obrigatórios antigos é feita pelo orquestrador antes do merge do PR de adoção. Sem isso, o PR ficaria esperando um
check que não roda mais. As outras regras da proteção (histórico linear, conversas resolvidas, sem force push) não
mudam.

### O que não é feito por arquivo

Estas ações acontecem no GitHub e não têm teste:

- o secret `PACKAGIST_WEBHOOK_URL` (da organização) precisa estar disponível para o repositório;
- a proteção da `main` perde os checks obrigatórios (acima);
- depois do merge, o `sync-labels` pode ser disparado uma vez à mão (`workflow_dispatch`) para o repositório já ficar
  com as 17 labels do [catálogo](https://github.com/Elavora/.github/blob/1.3.2/docs/labels.md).

---

## Referência dos arquivos

Nesta seção:

- **imagem** é `ghcr.io/elavora/php-dev:8.3-latest`, a tag que aponta sempre para a versão estável mais recente da
  imagem PHP 8.3 (só muda quando sai uma release estável do `Elavora/devcontainers`);
- os SHAs e as versões dos exemplos são os da adoção. O Dependabot troca esses valores, e os testes aceitam a troca
  (ver [Atualizações do Dependabot não quebram os testes](#atualizações-do-dependabot-não-quebram-os-testes)).

### Regras gerais

- Todo `uses:` que chama `Elavora/.github/.github/workflows/...` tem uma **referência central**
  ([formato](#formato-das-referências)). Nenhum usa nome de branch, `main` ou tag sem SHA.
- Nenhum chamador passa as entradas `automation_ref` ou `image`, e nenhum usa `secrets: inherit`.
- Todo job que chama um workflow de `Elavora/.github` concede `id-token: write`.
- Toda action de terceiros (`actions/*`, `github/*`) tem uma **referência de action**.
- Os workflows passam no actionlint 1.7.12 sem avisos.
- Nenhum workflow local usa `shivammathur/setup-php`: o PHP vem da imagem.
- Todo arquivo versionado usa fim de linha LF e termina com uma quebra de linha.

### Como os testes comparam os workflows

Cada workflow abaixo tem um bloco **Conteúdo esperado**. O teste compara o arquivo com o bloco, linha a linha, depois
de normalizar as referências.

A única parte livre é o fim de cada linha `uses:`: o `@`, o SHA, o espaço e o comentário de versão. Nessa parte o
teste confere o formato, e não o valor. Todo o resto precisa ser igual: gatilhos, permissões, jobs, passos, ordem dos
passos, entradas `with`, comandos `run` e o caminho de cada action ou workflow (o texto antes do `@`).

| Linha no arquivo | Resultado | Por quê |
| --- | --- | --- |
| `- uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4` | passa | igual ao bloco |
| mesma linha com outro SHA de 40 caracteres e `# v5.0.0` | passa | outro valor, formato válido |
| `- uses: actions/dependency-review-action@v4` | falha | tag em vez de SHA |
| `- uses: actions/some-other-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4` | falha | outra action |
| `fail-on-severity: critical` no lugar de `high` | falha | outra entrada `with` |
| `pull-requests: write` acrescentado às permissões | falha | outra permissão |

Os arquivos com **Conteúdo exato** (`devcontainer.json`, `dependabot.yml`, `.php-cs-fixer.dist.php`, `.gitattributes`
e `phpstan.neon`) são comparados por inteiro, sem parte livre.

### Formato das referências

- **Referência central** (`uses:` que chama o `Elavora/.github`): `@` + SHA + espaço + `# ` + versão `X.Y.Z`.
- **Referência de action** (`uses:` de uma action de terceiros): `@` + SHA + espaço + `# v` + versão com uma, duas ou
  três partes (`v4`, `v4.39`, `v4.39.0`).

Nos dois casos, o SHA tem exatamente 40 caracteres de `0` a `9` ou `a` a `f` (minúsculos), e o comentário é o último
texto da linha.

| Referência | Tipo | Resultado | Por quê |
| --- | --- | --- | --- |
| `@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2` | central | passa | |
| `@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0` | central | passa | outro SHA e outra versão |
| `@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # v1.3.2` | central | falha | a versão central não tem `v` |
| `@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3` | central | falha | a versão central tem três partes |
| `@1.3.2` | central | falha | tag em vez de SHA |
| `@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e` | central | falha | sem comentário de versão |
| `@2a6cd14 # 1.3.2` | central | falha | SHA curto |
| `@2A6CD142F0CD3F1AAC93B98D19F561D4F91E169E # 1.3.2` | central | falha | SHA com maiúsculas |
| `@2a6cd142f0cd3f1aac93b98d19f561d4f91e169g # 1.3.2` | central | falha | `g` não é hexadecimal |
| `@main # 1.3.2` | central | falha | nome de branch |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1` | action | passa | |
| `@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4` | action | passa | uma parte |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # v4.39` | action | passa | duas partes |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # 7.0.1` | action | falha | falta o `v` |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1.2` | action | falha | quatro partes |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # latest` | action | falha | não é versão |
| `@v7.0.1` | action | falha | tag em vez de SHA |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1` | action | falha | sem comentário de versão |

### `.github/workflows/quality.yml`

Conteúdo esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Qualidade

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  quality:
    permissions:
      contents: write
      pull-requests: write
      id-token: write
    uses: Elavora/.github/.github/workflows/php-quality.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2
```

- O arquivo termina na linha do `uses:`. Não existe bloco `with`: nada de `image`, cobertura, `composer audit` ou
  dependências mínimas. Os padrões do central valem: cobertura mínima de 80% (só avisa), `composer audit` ligado e
  testes com as dependências nas versões mínimas (`lowest`).
- O id do job é `quality`, e o check aparece no PR como **`quality / Qualidade PHP`**.
- `contents: write` permite o commit de formatação na branch do PR, e `pull-requests: write`, o comentário fixo.
- Não existe job local de PHP, matriz de versões nem bloco `concurrency`.

### `.github/workflows/release.yml`

```yaml
name: Release

on:
  pull_request:
    types: [closed]
    branches: [main]

permissions:
  contents: read

jobs:
  release:
    if: github.event.pull_request.merged == true
    permissions:
      contents: write
      pull-requests: read
      id-token: write
    uses: Elavora/.github/.github/workflows/release.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2
    secrets:
      PACKAGIST_WEBHOOK_URL: ${{ secrets.PACKAGIST_WEBHOOK_URL }}
```

- Sem bloco `with`. O título da release vem do nome do repositório (`Elavora/<repo> 1.0.1`). `packagist: false` é
  proibido: o Packagist é avisado a cada release.
- O único secret repassado é `PACKAGIST_WEBHOOK_URL`.

### `.github/workflows/sync-labels.yml`

```yaml
name: Sincronizar labels

on:
  schedule:
    - cron: '17 3 * * 1'
  workflow_dispatch:

permissions:
  contents: read

jobs:
  sync:
    permissions:
      contents: read
      issues: write
      id-token: write
    uses: Elavora/.github/.github/workflows/sync-labels.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2
```

Sem bloco `with` e sem bloco `secrets`. A sincronização apaga as labels que não estão no catálogo central.

### `.github/workflows/codeql.yml`

```yaml
name: CodeQL

on:
  pull_request:
    branches: [main]
    types: [opened, synchronize, reopened, ready_for_review]
  merge_group:
  schedule:
    - cron: "0 9 * * 1"
  workflow_dispatch:

permissions:
  contents: read
  security-events: write

jobs:
  analyze:
    name: CodeQL
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - uses: github/codeql-action/init@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4
        with:
          languages: actions
      - uses: github/codeql-action/analyze@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4
```

Os testes não exigem que `init` e `analyze` tenham o mesmo SHA. Quem garante isso é o grupo `codeql-action` do
Dependabot ([abaixo](#githubdependabotyml)).

### `.github/workflows/dependency-review.yml`

```yaml
name: Dependency Review

on:
  pull_request:
    branches: [main]

permissions:
  contents: read

jobs:
  dependency-review:
    name: Dependency Review
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4
        with:
          fail-on-severity: high
```

### `.github/workflows/automation.yml`

```yaml
name: Automação

on:
  pull_request:
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  tests:
    name: Automação
    runs-on: ubuntu-latest
    timeout-minutes: 10
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false

      - name: Testes da automação
        run: docker run --rm -v "$PWD:/w:ro" -w /w node:22-alpine node --test "tests/automation/*.test.cjs"

      - name: actionlint
        run: docker run --rm -v "$PWD:/w:ro" -w /w rhysd/actionlint:1.7.12 -no-color
```

### `.github/dependabot.yml`

Conteúdo exato:

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "chore"
      prefix-development: "chore"
      include: "scope"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "ci"
      include: "scope"
    groups:
      codeql-action:
        patterns:
          - "github/codeql-action*"
```

- `composer` atualiza `phpstan/phpstan`, `phpunit/phpunit` e `elavora/code-standards`. `github-actions` atualiza as
  actions de terceiros e os workflows do `Elavora/.github` (o SHA e o comentário de versão).
- Só existem essas duas entradas. Não há entrada para imagem: o devcontainer usa a `8.3-latest`, e o CI usa o padrão
  do pipeline central, que muda junto com o SHA do `.github`.
- `labels: ["dependencies"]` evita que o Dependabot crie as labels `php` e `github_actions`, que a sincronização
  apagaria toda semana. `dependencies` está no catálogo e vale patch.
- O grupo `codeql-action` junta `init` e `analyze`, que precisam estar na mesma versão, num PR só. Ele tem
  exatamente um padrão e nenhuma outra chave (`applies-to`, `dependency-type`, `update-types`, `exclude-patterns`). A
  entrada `composer` não tem grupos.
- Os PRs saem como `chore(deps): ...`, `chore(deps-dev): ...` e `ci(deps): ...`. O texto depois do prefixo é gerado
  em inglês pelo Dependabot e não dá para traduzir.

### `.devcontainer/devcontainer.json`

Conteúdo exato:

```json
{
  "name": "Elavora PHP 8.3",
  "image": "ghcr.io/elavora/php-dev:8.3-latest",
  "remoteUser": "vscode",
  "postCreateCommand": "git config --global --add safe.directory \"$(pwd)\" && composer install --no-interaction --prefer-dist",
  "customizations": {
    "vscode": {
      "extensions": [
        "xdebug.php-debug",
        "bmewburn.vscode-intelephense-client",
        "junstyle.php-cs-fixer",
        "EditorConfig.EditorConfig"
      ],
      "settings": {
        "php.validate.executablePath": "/usr/local/bin/php",
        "php-cs-fixer.executablePath": "/usr/local/bin/php-cs-fixer",
        "php-cs-fixer.config": ".php-cs-fixer.php;.php-cs-fixer.dist.php",
        "php-cs-fixer.rules": "@PER-CS",
        "php-cs-fixer.onsave": false,
        "[php]": {
          "editor.defaultFormatter": "junstyle.php-cs-fixer",
          "editor.formatOnSave": true
        }
      }
    }
  }
}
```

- `image` é exatamente `ghcr.io/elavora/php-dev:8.3-latest`: nunca uma versão fixa (`8.3-X.Y.Z`), outra tag móvel
  (`8.3`, `latest`) nem o nome local `elavora/php-dev:8.3`.
- O arquivo é JSON válido, sem comentários. O código PHP é formatado ao salvar.

### `composer.json`

O arquivo é formatado com **2 espaços** de indentação (regra do `.editorconfig` para `*.json`), sem espaços duplos
depois de `:` e sem sequências `\uXXXX` (por exemplo, `">=8.3"`, e não `">=8.3"`). Depois do PR, ele tem:

```json
{
  "name": "elavora/<repo>",
  "require": {
    "php": ">=8.3"
  },
  "require-dev": {
    "elavora/code-standards": "^1.1",
    "phpstan/phpstan": "^2.2",
    "phpunit/phpunit": "^10.5 || ^11.0 || ^12.0"
  },
  "scripts": {
    "lint": "elavora-standards lint",
    "test": "phpunit -c phpunit.xml",
    "analyse": "phpstan analyse --no-progress --memory-limit=512M",
    "check": [
      "@lint",
      "@test",
      "@analyse"
    ],
    "post-install-cmd": ["Elavora\\CodeStandards\\Composer::sync"],
    "post-update-cmd": ["Elavora\\CodeStandards\\Composer::sync"]
  },
  "config": {
    "sort-packages": true
  }
}
```

O exemplo mostra só as chaves que esta adoção cria ou muda. As demais (`description`, `type`, `license`, as outras
dependências de `require`, `autoload`, `extra.branch-alias`) continuam como estão, na ordem atual.

- `elavora/code-standards` fica em `require-dev`, nunca em `require`. A restrição é `^1.` seguido do minor (1 ou
  maior) e, opcionalmente, do patch. O minor mínimo é 1 porque o subcomando `lint` só existe a partir da 1.1.0, e o CI
  roda com as dependências nas versões mínimas.

  | Restrição | Resultado | Por quê |
  | --- | --- | --- |
  | `^1.1`, `^1.2`, `^1.1.3`, `^1.10` | passa | |
  | `^1.0`, `^1.0.5` | falha | minor 0 |
  | `^2.0` | falha | outro major |
  | `^1.1 \|\| ^2.0` | falha | aceita outro major |
  | `~1.1` | falha | outro operador |

- `phpstan/phpstan` e `phpunit/phpunit` estão em `require-dev`. A restrição deles não é conferida pelos testes da
  automação, para o Dependabot poder subi-la. A do PHPUnit hoje é `^10.5 || ^11.0 || ^12.0`.
- `friendsofphp/php-cs-fixer` não entra em nenhuma seção: o binário vem da imagem.
- Os scripts `lint`, `test`, `analyse` e `check` existem exatamente como no exemplo. `lint` não tem argumentos.
  `test` chama o PHPUnit direto, porque o pipeline acrescenta argumentos de cobertura. `check` roda `lint`, `test` e
  `analyse`, nessa ordem.
- Os dois scripts `post-*-cmd` chamam o método PHP da sincronização, e não um binário de `vendor/bin`: com
  `composer install --no-dev` o pacote não existe, e o Composer só avisa
  `Class Elavora\CodeStandards\Composer is not autoloadable` e segue com sucesso. Esse aviso é esperado.
- `composer validate --strict --no-check-publish` passa sem erros.
- Não existe a chave `repositories`.

### `phpstan.neon`

Conteúdo exato:

```neon
parameters:
    level: 8
    paths:
        - src
    tmpDir: .phpstan.cache
```

- `paths` lista as pastas de código PHP do pacote. Neste repositório, só `src`. Os testes ficam de fora.
- `composer analyse` sai com código 0 sem nenhum erro no nível 8. Não existe `baseline` nem `ignoreErrors`.

### `phpunit.xml`

O pipeline central roda `XDEBUG_MODE=coverage composer test -- --coverage-clover=<arquivo>`. Para isso, o
`phpunit.xml` ganha o atributo `cacheDirectory` e o bloco `<source>`. Conteúdo exato (a suíte e o `bootstrap` não
mudam):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
        cacheDirectory=".phpunit.cache"
        colors="true"
        failOnWarning="true">
    <testsuites>
        <testsuite name="Elavora API Queue Worker">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

Sem o bloco `<source>`, o PHPUnit avisa `No filter is configured, code coverage will not be processed`. Como o
arquivo tem `failOnWarning="true"`, o aviso faz o `composer test` sair com código 1, mesmo com todos os testes
passando. Com o bloco, o comando de cobertura cumpre três condições:

- sai com código 0 quando todos os testes passam;
- não mostra o aviso `No filter is configured`;
- gera o arquivo de cobertura, com os arquivos de `src/`.

### Estilo de código (`elavora/code-standards`)

As regras (PHP-CS-Fixer com PER-CS e o `.editorconfig` base) vêm do pacote
[`elavora/code-standards`](https://packagist.org/packages/elavora/code-standards), a partir da 1.1.0. O contrato do
pacote está no
[guia de adoção](https://github.com/Elavora/code-standards/blob/1.1.0/docs/adocao.md).

**`.php-cs-fixer.dist.php`** (raiz, versionado). Conteúdo exato, com LF e uma quebra de linha no final:

```php
<?php

require_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';

return Elavora\CodeStandards\PhpCsFixer::config(__DIR__);
```

Não acrescente regras nele. Para desligar uma regra só na sua máquina, use um `.php-cs-fixer.php` (está no
`.gitignore`).

**`.editorconfig`** (raiz, versionado). É a cópia gerada pela sincronização do pacote e não tem seções próprias. A
primeira linha é `# >>> elavora/code-standards >>>` e a última é `# <<< elavora/code-standards <<<`. O conteúdo
entre elas é o que o pacote escreve; o teste confere só os dois marcadores, porque uma versão nova do pacote pode
mudar o bloco. Na adoção, o arquivo é criado pelo primeiro `composer install` depois de acrescentar o pacote, e é
esse resultado que se commita. Na versão 1.1.0 do pacote, o bloco é:

```ini
# >>> elavora/code-standards >>>
# Bloco gerado pelo elavora/code-standards. Não edite: ele é reescrito a cada composer install e composer update.
# Seções próprias do projeto vão depois da linha "# <<< elavora/code-standards <<<".
root = true

[*]
charset = utf-8
end_of_line = lf
indent_style = space
indent_size = 4
insert_final_newline = true
trim_trailing_whitespace = true

[*.{yml,yaml,json,neon}]
indent_size = 2

[*.md]
indent_size = unset
trim_trailing_whitespace = false

[Makefile]
indent_style = tab
# <<< elavora/code-standards <<<
```

**`.gitignore`**, conteúdo exato (ordem incluída):

```
.phpstan.cache/
.phpunit.cache/
composer.lock
coverage/
vendor/
/.elavora/
/.php-cs-fixer.php
```

`/.elavora/` é a pasta onde o CI baixa os scripts centrais. `composer.lock` não é versionado: pacotes não travam
dependências.

**`.gitattributes`**, conteúdo exato:

```
* text=auto eol=lf

*.bat text eol=crlf
*.cmd text eol=crlf
*.gif binary
*.ico binary
*.jpeg binary
*.jpg binary
*.pdf binary
*.png binary
*.webp binary

*.sh text eol=lf
*.yml text eol=lf
*.yaml text eol=lf
```

Sem ele, uma máquina com `core.autocrlf=true` (Windows) grava CRLF, e o editorconfig-checker reprova o PR.

### Lint de sintaxe

`composer lint` roda `elavora-standards lint`, do `elavora/code-standards`: `php -l` em todo `.php` do projeto, menos
`vendor/` e arquivos ou pastas cujo nome começa com ponto. Não é análise estática (isso é o PHPStan) nem formatação.
Sem erro, escreve `elavora/code-standards: lint ok: N arquivo(s) sem erro de sintaxe`. Com erro, lista os arquivos e
sai com código 1. Depois de `composer install --no-dev` o comando não existe, e isso é esperado.

| Arquivo | Conferido? | Por quê |
| --- | --- | --- |
| `src/<qualquer>.php` | sim | |
| `tests/<qualquer>.php`, inclusive `tests/fixtures` e `tests/Support` | sim | |
| `.php-cs-fixer.dist.php` | não | o nome começa com ponto |
| `vendor/autoload.php` | não | pasta `vendor` |
| `tests/automation/workflows.test.cjs` | não | não é `.php` |

### Primeira formatação

A adoção do PER-CS reformata os arquivos PHP que já existem (`src/` e `tests/`). Isso entra no mesmo PR, num commit
**separado e só de formatação**, com a mensagem `style: aplica a formatação PER-CS`.

- É feito com o PHP-CS-Fixer da imagem e a configuração do repositório (`php-cs-fixer fix`, na raiz, depois do
  `composer install`), para o pipeline do PR já encontrar o código formatado.
- Não muda comportamento: os testes passam antes e depois dele.
- Não mistura outra mudança: nada de `composer.json`, workflows ou documentação nesse commit.
- Se sobrar algo, o pipeline completa com o commit automático `style: organiza o código`.

O mesmo vale para o `editorconfig-checker`: depois do PR, `git ls-files -z | xargs -0 editorconfig-checker` sai com
código 0.

### Arquivos de comunidade

Os arquivos abaixo são iguais nos pacotes `api-*`. O teste confere que existem, e que o conteúdo é o documentado.

**`LICENSE`:** licença MIT, em inglês, com a linha `Copyright (c) 2026 Elavora`. O texto é o MIT padrão:

```
MIT License

Copyright (c) 2026 Elavora

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

**`SECURITY.md`:**

```markdown
# Security Policy

## Reporting a Vulnerability

Please do not open a public issue for security vulnerabilities.

Use GitHub Security Advisories or private vulnerability reporting for this repository when available.

## Scope

This policy applies to this package and the public `elavora/api-*` package family.

## Supported Versions

Security fixes are provided for the latest stable release published on Packagist.
```

**`.github/CODEOWNERS`**, conteúdo exato:

```
# Owners padrao do repositorio.
* @ScriptPlayerAgent @Felipe-Cavalca
```

**`.github/ISSUE_TEMPLATE/config.yml`**:

```yaml
blank_issues_enabled: false
contact_links:
  - name: Vulnerabilidade de seguranca
    url: https://github.com/Elavora/<repo>/security/policy
    about: Reporte vulnerabilidades conforme a politica de seguranca do repositorio.
```

**`.github/ISSUE_TEMPLATE/bug_report.yml`**:

```yaml
name: Bug report
description: Reporte um comportamento incorreto
title: "fix: "
labels: ["bug"]
body:
  - type: textarea
    id: summary
    attributes:
      label: Resumo
      description: Descreva o problema de forma objetiva.
    validations:
      required: true
  - type: textarea
    id: reproduce
    attributes:
      label: Como reproduzir
      description: Liste os passos para reproduzir o problema.
      placeholder: |
        1.
        2.
        3.
    validations:
      required: true
  - type: textarea
    id: expected
    attributes:
      label: Comportamento esperado
    validations:
      required: true
  - type: textarea
    id: context
    attributes:
      label: Contexto adicional
      description: Informe versao do pacote, PHP, dependencias ou logs relevantes.
```

**`.github/ISSUE_TEMPLATE/documentation.yml`**:

```yaml
name: Documentation
description: Sugira ajuste na documentacao
title: "docs: "
labels: ["documentation"]
body:
  - type: textarea
    id: location
    attributes:
      label: Onde esta o problema
      description: Informe arquivo, secao ou link.
    validations:
      required: true
  - type: textarea
    id: change
    attributes:
      label: Mudanca sugerida
    validations:
      required: true
```

**`.github/ISSUE_TEMPLATE/feature_request.yml`**:

```yaml
name: Feature request
description: Sugira uma melhoria ou novo comportamento
title: "feat: "
labels: ["enhancement"]
body:
  - type: textarea
    id: problem
    attributes:
      label: Problema ou necessidade
      description: Qual problema essa mudanca resolveria?
    validations:
      required: true
  - type: textarea
    id: proposal
    attributes:
      label: Proposta
      description: Descreva a solucao esperada.
    validations:
      required: true
  - type: textarea
    id: alternatives
    attributes:
      label: Alternativas consideradas
```

O repositório **não tem** template de PR próprio: vale o
[template da organização](https://github.com/Elavora/.github/blob/1.3.2/.github/PULL_REQUEST_TEMPLATE.md), com as
seções `Objetivo`, `Principais mudanças`, `Testes executados`, `Riscos` e `Notas de release`.

### Arquivos removidos

Depois do PR, estes arquivos **não existem**:

- `.github/workflows/tag_on_merge.yml`, `.github/workflows/publish-composer.yml`,
  `.github/workflows/update-composer-on-release.yml` e `.github/workflows/sync_labels.yml` (substituídos por
  `release.yml` e `sync-labels.yml`; `publish-composer` e `update-composer-on-release` eram a publicação no
  Packagist, que agora o `release.yml` central faz);
- `.github/release-drafter.yml` e `.github/labels.yml` (as notas e as labels vêm do `Elavora/.github`);
- qualquer `.github/pull_request_template.md` ou `PULL_REQUEST_TEMPLATE.md`, em qualquer caixa, em `.github/`, na raiz
  ou em `docs/`;
- `CONTRIBUTING.md` na raiz, em `.github/` ou em `docs/` (o fluxo de contribuição está no
  [README](../README.md#como-contribuir) e em [Desenvolvimento e testes](DESENVOLVIMENTO.md));
- `.github/workflows/packagist-sync.yml`.

Em `.github/workflows/` existem exatamente seis arquivos: `automation.yml`, `codeql.yml`, `dependency-review.yml`,
`quality.yml`, `release.yml` e `sync-labels.yml`.

---

## Versões e release

Ninguém cria versão à mão. Cada merge na `main` gera uma tag e uma GitHub Release, e o número sai das labels do PR.
As regras completas estão no `Elavora/.github`: [versionamento](https://github.com/Elavora/.github/blob/1.3.2/docs/versionamento.md),
[release automática](https://github.com/Elavora/.github/blob/1.3.2/docs/release.md) e
[labels](https://github.com/Elavora/.github/blob/1.3.2/docs/labels.md).

| Label no PR | Efeito |
| --- | --- |
| `upgrade` | major (`1.0.0` para `2.0.0`) |
| `feature`, `enhancement` ou `improvement` | minor (`1.0.0` para `1.1.0`) |
| `bug`, `documentation`, `tests`, `refactor`, `dependencies`, outra ou nenhuma | patch (`1.0.0` para `1.0.1`) |
| `release`, junto com as outras | publica a estável em vez da beta |

- Sem `release`, a versão sai como beta (`1.0.1-beta.1`). Com `release`, sai a estável (`1.0.1`).
- Com várias labels, vale a maior. Todo merge gera versão: não existe label que pule a release.
- As tags novas não têm o prefixo `v`. As tags legadas `vX.Y.Z` contam como histórico, então a numeração continua do
  maior número existente. O workflow nunca apaga nem move tag.
- Só o último PR de uma série de mudanças leva `release`.

**Neste repositório:** a última tag é a legada `v1.0.0`, que conta como a estável `1.0.0`. O PR de adoção do pipeline
não leva `release` nem label de versão, então o merge gera `1.0.1-beta.1`. O `release.yml` novo roda no merge desse
mesmo PR, e o `tag_on_merge.yml` antigo já não existe nele, então sai uma release só.

O Packagist é avisado pelo `release.yml` a cada release, com o secret `PACKAGIST_WEBHOOK_URL`. O pacote só fica
disponível na versão nova depois dessa notificação.

## Testes da automação

Ficam em `tests/automation/*.test.cjs`, usam só o `node:test` (sem dependências) e leem os arquivos do repositório
como texto ou JSON. Os arquivos `.cjs` não são PHP: o PHPUnit e o `composer lint` os ignoram.

| Arquivo | O que confere |
| --- | --- |
| `workflows.test.cjs` | os seis workflows contra os blocos desta página, o formato das referências e as regras gerais |
| `dependabot.test.cjs` | o `dependabot.yml` exato, as labels, o prefixo dos commits e o grupo do CodeQL |
| `devcontainer.test.cjs` | o `devcontainer.json` exato e a imagem `8.3-latest` |
| `estilo.test.cjs` | o `composer.json` (pacote em `require-dev`, restrição, scripts), o stub do PHP-CS-Fixer, os marcadores do `.editorconfig`, o `.gitignore` e o `.gitattributes` |
| `phpunit.test.cjs` | o `phpunit.xml` exato (bloco `<source>`, `cacheDirectory`, `failOnWarning`) |
| `removidos.test.cjs` | os arquivos que não podem existir |
| `_helpers.cjs` | funções comuns (leitura de arquivos, normalização das referências) |

Os testes conferem também o `phpstan.neon` e a existência dos arquivos de comunidade.

### Atualizações do Dependabot não quebram os testes

O Dependabot abre PRs que mudam referências fixas, e esses PRs precisam passar no check **Automação** sem ninguém
editar os testes. Por isso os testes conferem o **formato** do que ele muda, e nunca o **valor**:

| O que o Dependabot muda | O que o teste confere | O que o teste não confere |
| --- | --- | --- |
| SHA e comentário do `Elavora/.github` | a [referência central](#formato-das-referências) | qual SHA e qual versão |
| SHA e comentário das actions de terceiros | a referência de action | qual SHA e qual versão |
| Restrição de `elavora/code-standards` | `^1.` + minor 1 ou maior | qual minor e qual patch |
| Restrições de `phpstan/phpstan` e `phpunit/phpunit` | o pacote está em `require-dev` | a restrição |
| Bloco do `.editorconfig` | os marcadores de início e fim | o conteúdo do bloco |
| `composer.lock` | nada | o conteúdo |

A estrutura continua conferida: um PR que muda um job, um passo, uma permissão, uma entrada `with` ou a action usada
falha no teste. Exemplos que passam:

- `actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4` trocado por outro SHA com `# v5.0.0`;
- `github/codeql-action/analyze` com um SHA novo e `init` ainda no antigo (o grupo do Dependabot cuida disso);
- `Elavora/.github/.github/workflows/release.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2` trocado por outro
  SHA com `# 1.4.0`;
- `"elavora/code-standards": "^1.1"` trocado por `"^1.2"`;
- `"phpunit/phpunit": "^10.5 || ^11.0 || ^12.0"` trocado por `"^10.5 || ^11.0 || ^12.0 || ^13.0"`.

Exemplos que falham: `...release.yml@1.4.0` (tag em vez de SHA), um `uses:` sem o comentário de versão, SHA curto,
`"elavora/code-standards": "^2.0"` e `"elavora/code-standards": "^1.0"`.

Como rodar: veja [Desenvolvimento e testes](DESENVOLVIMENTO.md#testes-da-automação). No CI, rodam no workflow
**Automação**, em todo PR e em todo push na `main`.

---

[README](../README.md) · Veja também: [Desenvolvimento e testes](DESENVOLVIMENTO.md)
