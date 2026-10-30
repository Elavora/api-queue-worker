'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const STUB =
    "<?php\n" +
    "\n" +
    "require_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';\n" +
    "\n" +
    "return Elavora\\CodeStandards\\PhpCsFixer::config(__DIR__);\n";

const GITIGNORE = h.lines([
    '.phpstan.cache/',
    '.phpunit.cache/',
    'composer.lock',
    'coverage/',
    'vendor/',
    '/.elavora/',
    '/.php-cs-fixer.php',
]);

const GITATTRIBUTES = h.lines([
    '* text=auto eol=lf',
    '',
    '*.bat text eol=crlf',
    '*.cmd text eol=crlf',
    '*.gif binary',
    '*.ico binary',
    '*.jpeg binary',
    '*.jpg binary',
    '*.pdf binary',
    '*.png binary',
    '*.webp binary',
    '',
    '*.sh text eol=lf',
    '*.yml text eol=lf',
    '*.yaml text eol=lf',
]);

function composer() {
    return h.readJson('composer.json');
}

/**
 * Restrição aceita para elavora/code-standards: major 1 e minor 1 ou maior (a 1.0.0 não tem o
 * subcomando lint), com patch opcional. O valor exato não é conferido.
 */
const CODE_STANDARDS = /^\^1\.[1-9]\d*(?:\.\d+)?$/;

test.describe('restrição de elavora/code-standards (exemplos da doc)', () => {
    for (const [c, ok] of [
        ['^1.1', true],
        ['^1.2', true],
        ['^1.1.3', true],
        ['^1.10', true],
        ['^1.0', false],
        ['^1.0.5', false],
        ['^2.0', false],
        ['^1.1 || ^2.0', false],
        ['~1.1', false],
        ['^1', false],
        ['1.1.0', false],
        ['^1.1.2.3', false],
    ]) {
        test(`${c} ${ok ? 'passa' : 'falha'}`, () => {
            assert.equal(CODE_STANDARDS.test(c), ok);
        });
    }
});

test.describe('composer.json', () => {
    test('é formatado com 2 espaços, sem espaços duplos depois de ":" e sem sequências \\uXXXX', () => {
        const raw = h.read('composer.json');
        assert.doesNotMatch(raw, /\\u[0-9a-fA-F]{4}/, 'sequência \\uXXXX encontrada (use ">=8.3")');
        assert.doesNotMatch(raw, /":\s{2,}/, 'espaços duplos depois de ":"');
        assert.ok(raw.includes('"php": ">=8.3"'), 'esperado "php": ">=8.3" escrito literalmente');
        for (const line of raw.split('\n')) {
            const indent = line.match(/^( *)/)[1].length;
            assert.equal(indent % 2, 0, `indentação que não é múltipla de 2: ${JSON.stringify(line)}`);
            assert.ok(!line.startsWith('\t'), 'tabulação na indentação');
        }
        assert.ok(raw.split('\n').some((l) => /^ {2}"[^"]+":/.test(l)), 'chaves de topo deveriam ter 2 espaços');
        assert.ok(!raw.split('\n').some((l) => /^ {4}"require"/.test(l)), 'indentação de 4 espaços encontrada');
    });

    test('require tem php >=8.3 (e mantém as outras dependências)', () => {
        const c = composer();
        assert.equal((c.require || {}).php, '>=8.3');
        assert.ok('elavora/api-framework' in (c.require || {}), 'dependência de runtime perdida');
    });

    test('elavora/code-standards ^1.N (N >= 1) em require-dev, fora do require', () => {
        const c = composer();
        const constraint = (c['require-dev'] || {})['elavora/code-standards'];
        assert.equal(typeof constraint, 'string', 'elavora/code-standards ausente em require-dev');
        assert.match(constraint, CODE_STANDARDS);
        assert.ok(!('elavora/code-standards' in (c.require || {})));
    });

    test('phpstan/phpstan e phpunit/phpunit estão em require-dev (a restrição não é conferida)', () => {
        const dev = composer()['require-dev'] || {};
        assert.equal(typeof dev['phpstan/phpstan'], 'string', 'phpstan/phpstan ausente em require-dev');
        assert.equal(typeof dev['phpunit/phpunit'], 'string', 'phpunit/phpunit ausente em require-dev');
        assert.ok(!('phpstan/phpstan' in (composer().require || {})));
        assert.ok(!('phpunit/phpunit' in (composer().require || {})));
    });

    test('friendsofphp/php-cs-fixer não entra em nenhuma seção', () => {
        const c = composer();
        for (const section of ['require', 'require-dev', 'suggest', 'conflict', 'replace', 'provide']) {
            assert.ok(!('friendsofphp/php-cs-fixer' in (c[section] || {})), section);
        }
    });

    test('lint é exatamente elavora-standards lint, sem argumentos', () => {
        assert.equal((composer().scripts || {}).lint, 'elavora-standards lint');
    });

    test('test chama o phpunit direto, com o phpunit.xml', () => {
        assert.equal((composer().scripts || {}).test, 'phpunit -c phpunit.xml');
    });

    test('analyse roda o phpstan sem progresso e com 512M', () => {
        assert.equal((composer().scripts || {}).analyse, 'phpstan analyse --no-progress --memory-limit=512M');
    });

    test('check roda lint, test e analyse, nessa ordem', () => {
        assert.deepEqual((composer().scripts || {}).check, ['@lint', '@test', '@analyse']);
    });

    test('post-install-cmd e post-update-cmd chamam Composer::sync (método PHP, não vendor/bin)', () => {
        const s = composer().scripts || {};
        assert.deepEqual(s['post-install-cmd'], ['Elavora\\CodeStandards\\Composer::sync']);
        assert.deepEqual(s['post-update-cmd'], ['Elavora\\CodeStandards\\Composer::sync']);
    });

    test('config.sort-packages é true', () => {
        assert.equal((composer().config || {})['sort-packages'], true);
    });

    test('não existe a chave repositories', () => {
        assert.ok(!('repositories' in composer()));
    });

    test('mantém o nome e o autoload do pacote', () => {
        const c = composer();
        assert.equal(c.name, `elavora/${h.REPO}`);
        assert.ok(c.autoload && c.autoload['psr-4'], 'autoload psr-4 perdido');
    });
});

test.describe('.php-cs-fixer.dist.php', () => {
    test('é o stub exato, em bytes (LF e uma quebra de linha final)', () => {
        assert.ok(h.exists('.php-cs-fixer.dist.php'), '.php-cs-fixer.dist.php deveria existir');
        assert.equal(h.readRaw('.php-cs-fixer.dist.php'), STUB);
    });
});

test.describe('.editorconfig', () => {
    test('contém só o bloco gerado pelo elavora/code-standards (confere só os marcadores)', () => {
        assert.ok(h.exists('.editorconfig'), '.editorconfig deveria existir');
        const lines = h.read('.editorconfig').replace(/\n+$/, '').split('\n');
        assert.equal(lines[0], '# >>> elavora/code-standards >>>');
        assert.equal(lines[lines.length - 1], '# <<< elavora/code-standards <<<');
        assert.ok(lines.length > 2, 'bloco vazio');
        assert.equal(lines.filter((l) => l === '# >>> elavora/code-standards >>>').length, 1);
        assert.equal(lines.filter((l) => l === '# <<< elavora/code-standards <<<').length, 1);
    });
});

test.describe('.gitignore', () => {
    test('tem o conteúdo exato documentado (ordem incluída)', () => {
        h.assertExactContent(assert, '.gitignore', GITIGNORE);
    });

    test('não ignora o stub .php-cs-fixer.dist.php nem o .editorconfig', () => {
        const l = h.read('.gitignore').split('\n').map((x) => x.trim());
        for (const bad of ['.php-cs-fixer.dist.php', '/.php-cs-fixer.dist.php', '.editorconfig', '/.editorconfig']) {
            assert.ok(!l.includes(bad), bad);
        }
    });
});

test.describe('.gitattributes', () => {
    test('tem o conteúdo exato documentado', () => {
        h.assertExactContent(assert, '.gitattributes', GITATTRIBUTES);
    });
});
