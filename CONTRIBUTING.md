# Contribuindo

Obrigado por contribuir com os pacotes Elavora.

## Fluxo de trabalho

1. Abra uma branch a partir da `main`.
2. Mantenha a mudanca pequena e focada.
3. Atualize documentacao quando o uso publico mudar.
4. Rode as validacoes aplicaveis antes de abrir o PR.
5. Abra o PR para `main` e aguarde os checks obrigatorios.

## Validacoes

Quando o pacote tiver os scripts abaixo, use:

```bash
composer validate --strict --no-check-publish
composer check
```

Se `composer check` nao existir, rode:

```bash
composer test
```

Os PRs tambem executam os checks obrigatorios do GitHub Actions:

- `Composer Quality`
- `Analyze GitHub Actions`
- `CodeQL`

## Padrao de codigo

- Use PHP 8.1 ou superior.
- Preserve `declare(strict_types=1);` nos arquivos PHP.
- Siga os namespaces e contratos existentes do pacote.
- Evite refatoracoes fora do escopo do PR.
- Adicione testes quando criar comportamento novo.

## Commits e PRs

Use mensagens de commit em portugues do Brasil com Conventional Commits:

```text
feat: adiciona suporte a novo adaptador
fix: corrige validacao de entrada
docs: atualiza exemplo de uso
test: adiciona cobertura para cache
```

O PR deve explicar:

- objetivo;
- principais mudancas;
- testes executados;
- riscos ou pontos de atencao.

## Releases

As releases sao geradas automaticamente a partir do merge na `main`, conforme as labels do PR. Use a label correta para indicar o tipo de versao esperado.

## Seguranca

Nao abra vulnerabilidades como issue publica. Use a politica descrita em `SECURITY.md`.