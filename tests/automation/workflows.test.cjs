'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const RELEASE = h.lines([
    'name: Release',
    '',
    'on:',
    '  pull_request:',
    '    types: [closed]',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  release:',
    '    if: github.event.pull_request.merged == true',
    '    permissions:',
    '      contents: write',
    '      pull-requests: read',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/release.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2',
    '    secrets:',
    '      PACKAGIST_WEBHOOK_URL: ${{ secrets.PACKAGIST_WEBHOOK_URL }}',
]);

const SYNC_LABELS = h.lines([
    'name: Sincronizar labels',
    '',
    'on:',
    '  schedule:',
    "    - cron: '17 3 * * 1'",
    '  workflow_dispatch:',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  sync:',
    '    permissions:',
    '      contents: read',
    '      issues: write',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/sync-labels.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2',
]);

const QUALITY = h.lines([
    'name: Qualidade',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '  push:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  quality:',
    '    permissions:',
    '      contents: write',
    '      pull-requests: write',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/php-quality.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2',
]);

const CODEQL = h.lines([
    'name: CodeQL',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '    types: [opened, synchronize, reopened, ready_for_review]',
    '  merge_group:',
    '  schedule:',
    '    - cron: "0 9 * * 1"',
    '  workflow_dispatch:',
    '',
    'permissions:',
    '  contents: read',
    '  security-events: write',
    '',
    'jobs:',
    '  analyze:',
    '    name: CodeQL',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '      - uses: github/codeql-action/init@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4',
    '        with:',
    '          languages: actions',
    '      - uses: github/codeql-action/analyze@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4',
]);

const DEPENDENCY_REVIEW = h.lines([
    'name: Dependency Review',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  dependency-review:',
    '    name: Dependency Review',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '      - uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4',
    '        with:',
    '          fail-on-severity: high',
]);

const AUTOMATION = h.lines([
    'name: Automação',
    '',
    'on:',
    '  pull_request:',
    '  push:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  tests:',
    '    name: Automação',
    '    runs-on: ubuntu-latest',
    '    timeout-minutes: 10',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '',
    '      - name: Testes da automação',
    '        run: docker run --rm -v "$PWD:/w:ro" -w /w node:22-alpine node --test "tests/automation/*.test.cjs"',
    '',
    '      - name: actionlint',
    '        run: docker run --rm -v "$PWD:/w:ro" -w /w rhysd/actionlint:1.7.12 -no-color',
]);

const EXPECTED = {
    '.github/workflows/release.yml': RELEASE,
    '.github/workflows/sync-labels.yml': SYNC_LABELS,
    '.github/workflows/quality.yml': QUALITY,
    '.github/workflows/codeql.yml': CODEQL,
    '.github/workflows/dependency-review.yml': DEPENDENCY_REVIEW,
    '.github/workflows/automation.yml': AUTOMATION,
};

test.describe('conteúdo esperado dos workflows (referências conferidas pelo formato)', () => {
    for (const [file, content] of Object.entries(EXPECTED)) {
        test(`${file} tem o conteúdo documentado`, () => {
            assert.ok(h.exists(file), `${file} deveria existir`);
            h.assertWorkflowMatches(assert, h.read(file), content, file);
        });
    }
});

test.describe('formato das referências (tabela da doc)', () => {
    const central = [
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2', true],
        ['@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0', true],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # v1.3.2', false],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3', false],
        ['@1.3.2', false],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e', false],
        ['@2a6cd14 # 1.3.2', false],
        ['@2A6CD142F0CD3F1AAC93B98D19F561D4F91E169E # 1.3.2', false],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169g # 1.3.2', false],
        ['@main # 1.3.2', false],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2 extra', false],
        ['@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # -1.3.2', false],
    ];
    for (const [ref, ok] of central) {
        test(`central ${ref} ${ok ? 'passa' : 'falha'}`, () => {
            assert.equal(h.isCentralRef(ref), ok);
        });
    }

    const action = [
        ['@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4', true],
        ['@6f2ac81f5cd81fe351a197ba0b00d91d03cd2aee # v4.39', true],
        ['@6f2ac81f5cd81fe351a197ba0b00d91d03cd2aee # v4.39.0', true],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1', true],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # 7.0.1', false],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1.2', false],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # latest', false],
        ['@v7.0.1', false],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1', false],
        ['@3d3c42e # v7.0.1', false],
        ['@3D3C42E5AAC5BA805825DA76410C181273BA90B1 # v7.0.1', false],
        ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1 extra', false],
    ];
    for (const [ref, ok] of action) {
        test(`action ${ref} ${ok ? 'passa' : 'falha'}`, () => {
            assert.equal(h.isActionRef(ref), ok);
        });
    }
});

test.describe('comparação linha a linha (exemplos da doc)', () => {
    const DR = '      - uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4';
    const matches = (text, expected = DEPENDENCY_REVIEW) =>
        h.normalizeWorkflow(text) === h.normalizeWorkflow(expected);
    const swap = (from, to) => {
        assert.ok(DEPENDENCY_REVIEW.includes(from), `linha base ausente: ${from}`);
        return DEPENDENCY_REVIEW.replace(from, to);
    };

    test('o próprio bloco passa', () => {
        assert.ok(matches(DEPENDENCY_REVIEW));
    });

    const casos = [
        ['outro SHA passa', DR,
            '      - uses: actions/dependency-review-action@0123456789abcdef0123456789abcdef01234567 # v4', true],
        ['outro SHA e outra versão passa', DR,
            '      - uses: actions/dependency-review-action@0123456789abcdef0123456789abcdef01234567 # v5.0.0', true],
        ['checkout com outro SHA e # v7.1.0 passa',
            '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
            '      - uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.1.0', true],
        ['tag em vez de SHA falha', DR, '      - uses: actions/dependency-review-action@v4', false],
        ['outra action falha', DR,
            '      - uses: actions/some-other-action@0123456789abcdef0123456789abcdef01234567 # v4', false],
        ['SHA curto no checkout falha',
            '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
            '      - uses: actions/checkout@3d3c42e # v7.0.1', false],
        ['outra entrada with falha', '          fail-on-severity: high', '          fail-on-severity: critical', false],
        ['permissão acrescentada falha', '  contents: read\n', '  contents: read\n  pull-requests: write\n', false],
    ];
    for (const [nome, from, to, ok] of casos) {
        test(`dependency-review.yml: ${nome}`, () => {
            assert.equal(matches(swap(from, to)), ok);
        });
    }

    const REL = '    uses: Elavora/.github/.github/workflows/release.yml@2a6cd142f0cd3f1aac93b98d19f561d4f91e169e # 1.3.2';
    const centrais = [
        ['outro SHA e # 1.3.0 passa',
            '    uses: Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0', true],
        ['tag em vez de SHA falha', '    uses: Elavora/.github/.github/workflows/release.yml@1.3.0', false],
        ['sem comentário de versão falha',
            '    uses: Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487', false],
        ['versão com v falha',
            '    uses: Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # v1.3.0', false],
        ['outro workflow central falha',
            '    uses: Elavora/.github/.github/workflows/php-quality.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0', false],
    ];
    for (const [nome, to, ok] of centrais) {
        test(`release.yml: ${nome}`, () => {
            assert.ok(RELEASE.includes(REL));
            assert.equal(matches(RELEASE.replace(REL, to), RELEASE), ok);
        });
    }

    test('codeql.yml: só o analyze subir passa; init e analyze podem ter SHAs diferentes', () => {
        const from = '      - uses: github/codeql-action/analyze@9b3254085494b55a0f7c4c2b3d2d52cc712ce370 # v4';
        const to = '      - uses: github/codeql-action/analyze@6f2ac81f5cd81fe351a197ba0b00d91d03cd2aee # v4';
        assert.ok(CODEQL.includes(from));
        assert.ok(matches(CODEQL.replace(from, to), CODEQL));
    });
});

test.describe('release.yml', () => {
    test('não passa with (package_name, packagist) e repassa só PACKAGIST_WEBHOOK_URL', () => {
        const text = h.read('.github/workflows/release.yml');
        assert.doesNotMatch(text, /^\s*with:/m);
        assert.doesNotMatch(text, /package_name/);
        assert.doesNotMatch(text, /packagist:\s*false/);
        const secrets = text.split(/^\s*secrets:\s*$/m)[1] || '';
        const names = [...secrets.matchAll(/^\s+([A-Z0-9_]+):/gm)].map((m) => m[1]);
        assert.deepEqual(names, ['PACKAGIST_WEBHOOK_URL']);
    });
});

test.describe('sync-labels.yml', () => {
    test('sem with (prune) e sem secrets', () => {
        const text = h.read('.github/workflows/sync-labels.yml');
        assert.doesNotMatch(text, /^\s*with:/m);
        assert.doesNotMatch(text, /prune/);
        assert.doesNotMatch(text, /^\s*secrets:/m);
    });
});

test.describe('quality.yml', () => {
    test('job quality chama php-quality.yml sem with nem image', () => {
        const text = h.read('.github/workflows/quality.yml');
        const jobs = h.splitJobs(text);
        assert.deepEqual(Object.keys(jobs), ['quality']);
        assert.match(jobs.quality, /uses: Elavora\/\.github\/\.github\/workflows\/php-quality\.yml@/);
        assert.doesNotMatch(jobs.quality, /^\s*with:/m);
        assert.doesNotMatch(jobs.quality, /^\s*image:/m);
        const last = text.replace(/\n$/, '').split('\n').pop();
        assert.ok(text.endsWith('\n') && !text.endsWith('\n\n'), 'quality.yml deveria terminar com uma quebra de linha');
        assert.match(last, /^\s+uses:\s*Elavora\/\.github\/\.github\/workflows\/php-quality\.yml@/,
            'quality.yml deveria terminar na linha do uses:');
        assert.match(jobs.quality, /contents: write/);
        assert.match(jobs.quality, /pull-requests: write/);
    });

    test('não mexe em cobertura, audit ou dependências mínimas, nem tem job PHP local ou concurrency', () => {
        const text = h.read('.github/workflows/quality.yml');
        assert.doesNotMatch(text, /coverage|cobertura|audit|lowest|dependency-versions/i);
        assert.doesNotMatch(text, /setup-php|runs-on|matrix|^concurrency:/m);
    });
});

test.describe('regras gerais dos workflows', () => {
    test('os seis workflows documentados existem', () => {
        for (const file of Object.keys(EXPECTED)) {
            assert.ok(h.exists(file), `${file} deveria existir`);
        }
    });

    test('em .github/workflows existem exatamente os seis arquivos documentados', () => {
        assert.deepEqual(
            h.namesIn('.github/workflows').sort(),
            ['automation.yml', 'codeql.yml', 'dependency-review.yml', 'quality.yml', 'release.yml', 'sync-labels.yml'],
        );
    });

    test('todo arquivo versionado usa fim de linha LF e termina com uma quebra de linha', () => {
        const binary = /\.(?:gif|ico|jpe?g|pdf|png|webp)$/i;
        for (const file of h.versionedFiles()) {
            if (binary.test(file)) continue;
            const text = h.readRaw(file);
            if (text === '') continue;
            assert.ok(!text.includes('\r'), `${file} tem CR (fim de linha CRLF)`);
            assert.ok(text.endsWith('\n'), `${file} não termina com uma quebra de linha`);
        }
    });

    test('todo uses central tem uma referência central (@SHA # X.Y.Z)', () => {
        let found = 0;
        for (const file of h.workflowFiles()) {
            for (const uses of h.usesLines(h.read(file))) {
                if (!uses.startsWith('Elavora/.github/')) continue;
                found++;
                assert.match(uses, /^Elavora\/\.github\/\.github\/workflows\/[A-Za-z0-9_.-]+\.ya?ml@/, `${file}: ${uses}`);
                assert.equal(uses.split('@').length, 2, `${file}: ${uses}`);
                assert.ok(h.isCentralRef(h.splitUses(uses).ref), `${file}: ${uses} sem referência central válida`);
            }
        }
        assert.ok(found >= 3, 'esperados pelo menos três chamadores centrais (release, sync-labels, quality)');
    });

    test('nenhum chamador passa automation_ref nem usa secrets: inherit', () => {
        for (const file of h.workflowFiles()) {
            const text = h.read(file);
            assert.doesNotMatch(text, /automation_ref/, file);
            assert.doesNotMatch(text, /secrets:\s*inherit/, file);
        }
    });

    test('nenhum chamador de Elavora/.github passa image nem automation_ref', () => {
        let found = 0;
        for (const file of h.workflowFiles()) {
            const jobs = h.splitJobs(h.read(file));
            for (const [id, body] of Object.entries(jobs)) {
                if (!/uses:\s*Elavora\/\.github\//.test(body)) continue;
                found++;
                assert.doesNotMatch(body, /^\s*image:/m, `${file}: job ${id} passa a entrada image`);
                assert.doesNotMatch(body, /automation_ref/, `${file}: job ${id} passa automation_ref`);
            }
        }
        assert.ok(found >= 3, 'esperados pelo menos três chamadores centrais (release, sync-labels, quality)');
    });

    test('todo job que chama Elavora/.github concede id-token: write', () => {
        for (const file of h.workflowFiles()) {
            const jobs = h.splitJobs(h.read(file));
            for (const [id, body] of Object.entries(jobs)) {
                if (!/uses:\s*Elavora\/\.github\//.test(body)) continue;
                assert.match(body, /^\s+id-token:\s*write\s*$/m, `${file}: job ${id} sem id-token: write`);
            }
        }
    });

    test('actions de terceiros têm uma referência de action (@SHA # vN[.N[.N]])', () => {
        for (const file of h.workflowFiles()) {
            for (const uses of h.usesLines(h.read(file))) {
                if (uses.startsWith('Elavora/.github/') || uses.startsWith('./')) continue;
                assert.match(uses, /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_./-]+@/, `${file}: ${uses}`);
                assert.equal(uses.split('@').length, 2, `${file}: ${uses}`);
                assert.ok(h.isActionRef(h.splitUses(uses).ref), `${file}: ${uses} sem referência de action válida`);
            }
        }
    });

    test('nenhum workflow local usa shivammathur/setup-php e não existe packagist-sync.yml', () => {
        for (const file of h.workflowFiles()) {
            assert.doesNotMatch(h.read(file), /shivammathur\/setup-php/, file);
        }
        assert.ok(!h.exists('.github/workflows/packagist-sync.yml'));
        assert.ok(!h.exists('.github/workflows/packagist-sync.yaml'));
    });
});
