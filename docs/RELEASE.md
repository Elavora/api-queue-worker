# Processo de release

Este repositorio usa releases automaticas a partir de merges na `main`.

## Fluxo esperado

1. Abra um PR para `main`.
2. Aplique as labels corretas no PR.
3. Aguarde os checks obrigatorios passarem.
4. Faca merge do PR.
5. O workflow cria uma nova tag e publica a GitHub Release.
6. O webhook do Packagist atualiza o pacote Composer.

## Checks obrigatorios

Antes do merge, a branch precisa passar por:

- `Composer Quality`
- `Analyze GitHub Actions`
- `CodeQL`

## Labels de versionamento

O workflow calcula a proxima versao usando as labels do PR:

- `upgrade`: incrementa a versao major.
- `enhancement` ou `dependencies`: incrementa a versao minor.
- demais PRs: incrementam a versao patch.
- `release`: publica a release como estavel.

Quando `release` nao estiver presente, a GitHub Release sera marcada como pre-release.

## Packagist

O pacote Composer e publicado pelo Packagist. Depois que a GitHub Release for criada, o webhook do Packagist deve rastrear a tag nova automaticamente.

Se a versao nao aparecer no Packagist:

1. confira se a tag existe no GitHub;
2. confira se a release aponta para a mesma tag;
3. confira o webhook do Packagist;
4. acione update manual no Packagist apenas se o webhook nao tiver executado.

## Imutabilidade

Releases devem permanecer imutaveis. Nao edite assets ou tags ja publicados. Para corrigir algo publicado, abra novo PR e gere uma nova versao.

## Hotfix

Para hotfix:

1. abra PR pequeno e focado;
2. use label `bug`;
3. aguarde checks;
4. faca merge;
5. valide a nova versao no GitHub e no Packagist.