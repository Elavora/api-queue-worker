'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const FILE = '.github/dependabot.yml';

const DEPENDABOT = h.lines([
    'version: 2',
    'updates:',
    '  - package-ecosystem: "composer"',
    '    directory: "/"',
    '    schedule:',
    '      interval: "weekly"',
    '      day: "monday"',
    '      time: "09:00"',
    '      timezone: "America/Sao_Paulo"',
    '    open-pull-requests-limit: 5',
    '    labels: ["dependencies"]',
    '    commit-message:',
    '      prefix: "chore"',
    '      prefix-development: "chore"',
    '      include: "scope"',
    '',
    '  - package-ecosystem: "github-actions"',
    '    directory: "/"',
    '    schedule:',
    '      interval: "weekly"',
    '      day: "monday"',
    '      time: "09:00"',
    '      timezone: "America/Sao_Paulo"',
    '    open-pull-requests-limit: 5',
    '    labels: ["dependencies"]',
    '    commit-message:',
    '      prefix: "ci"',
    '      include: "scope"',
    '    groups:',
    '      codeql-action:',
    '        patterns:',
    '          - "github/codeql-action*"',
]);

/** Lê o bloco `groups:` de uma entrada: { nome: { keys: [...], patterns: [...] } }, ou null se não houver. */
function groupsOf(body) {
    const lines = body.split('\n');
    const start = lines.findIndex((l) => /^\s+groups:\s*$/.test(l));
    if (start < 0) return null;
    const baseIndent = lines[start].match(/^(\s*)/)[1].length;
    const groups = {};
    let groupIndent = null;
    let current = null;
    let inPatterns = false;
    for (const line of lines.slice(start + 1)) {
        if (line.trim() === '') continue;
        const indent = line.match(/^(\s*)/)[1].length;
        if (indent <= baseIndent) break;
        const key = line.match(/^\s*([A-Za-z0-9_-]+):\s*(.*)$/);
        if (groupIndent === null || indent === groupIndent) {
            groupIndent = indent;
            current = key ? key[1] : line.trim();
            groups[current] = { keys: [], patterns: [] };
            inPatterns = false;
            continue;
        }
        const item = line.match(/^\s*-\s+"?([^"]*)"?\s*$/);
        if (item && inPatterns) {
            groups[current].patterns.push(item[1]);
            continue;
        }
        if (key) {
            groups[current].keys.push(key[1]);
            inPatterns = key[1] === 'patterns';
        }
    }
    return groups;
}

/** Divide as entradas de `updates` pelo marcador "  - package-ecosystem:". */
function entries() {
    const text = h.read(FILE);
    return text
        .split(/^\s*-\s+package-ecosystem:/m)
        .slice(1)
        .map((chunk) => {
            const eco = chunk.match(/^\s*"?([^"\s]+)"?/)[1];
            return { eco, body: chunk };
        });
}

test.describe('.github/dependabot.yml', () => {
    test('tem o conteúdo exato documentado', () => {
        h.assertExactContent(assert, FILE, DEPENDABOT);
    });

    test('só existem as entradas composer e github-actions', () => {
        assert.deepEqual(entries().map((e) => e.eco), ['composer', 'github-actions']);
    });

    test('toda entrada tem labels só com "dependencies" (label do catálogo)', () => {
        for (const { eco, body } of entries()) {
            const m = body.match(/^\s+labels:\s*\[(.*)\]\s*$/m);
            assert.ok(m, `${eco} sem labels`);
            const labels = m[1].split(',').map((s) => s.trim().replace(/^"|"$/g, '')).filter(Boolean);
            assert.deepEqual(labels, ['dependencies'], eco);
        }
    });

    test('toda entrada tem commit-message com include: "scope" e o prefixo certo', () => {
        const expected = {
            composer: { prefix: 'chore', dev: 'chore' },
            'github-actions': { prefix: 'ci', dev: null },
        };
        for (const { eco, body } of entries()) {
            assert.match(body, /^\s+commit-message:\s*$/m, `${eco} sem commit-message`);
            assert.match(body, /^\s+include:\s*"scope"\s*$/m, eco);
            const prefix = body.match(/^\s+prefix:\s*"([^"]*)"\s*$/m);
            assert.equal(prefix && prefix[1], expected[eco].prefix, eco);
            const dev = body.match(/^\s+prefix-development:\s*"([^"]*)"\s*$/m);
            assert.equal(dev ? dev[1] : null, expected[eco].dev, eco);
        }
    });

    test('github-actions tem só o grupo codeql-action, com só o padrão github/codeql-action*', () => {
        const gha = entries().find((e) => e.eco === 'github-actions');
        assert.ok(gha, 'entrada github-actions ausente');
        const groups = groupsOf(gha.body);
        assert.ok(groups, 'github-actions sem groups');
        assert.deepEqual(Object.keys(groups), ['codeql-action']);
        assert.deepEqual(groups['codeql-action'].keys, ['patterns'], 'o grupo só pode ter a chave patterns');
        assert.deepEqual(groups['codeql-action'].patterns, ['github/codeql-action*']);
    });

    test('composer não tem grupos', () => {
        const composer = entries().find((e) => e.eco === 'composer');
        assert.ok(composer, 'entrada composer ausente');
        assert.equal(groupsOf(composer.body), null);
    });
});

test.describe('leitura do bloco groups (casos da doc)', () => {
    const base = [
        '    groups:',
        '      codeql-action:',
        '        patterns:',
        '          - "github/codeql-action*"',
    ];

    test('o bloco documentado é lido como um grupo com um padrão', () => {
        assert.deepEqual(groupsOf(base.join('\n')), {
            'codeql-action': { keys: ['patterns'], patterns: ['github/codeql-action*'] },
        });
    });

    test('chave extra no grupo (applies-to) aparece e reprova a regra', () => {
        const g = groupsOf([...base, '        applies-to: version-updates'].join('\n'));
        assert.notDeepEqual(g['codeql-action'].keys, ['patterns']);
    });

    test('padrão extra aparece e reprova a regra', () => {
        const g = groupsOf([...base, '          - "actions/*"'].join('\n'));
        assert.notDeepEqual(g['codeql-action'].patterns, ['github/codeql-action*']);
    });

    test('o padrão github/codeql-action* pega init e analyze, e não actions/checkout', () => {
        const re = /^github\/codeql-action.*$/;
        assert.ok(re.test('github/codeql-action/init'));
        assert.ok(re.test('github/codeql-action/analyze'));
        assert.ok(!re.test('actions/checkout'));
    });
});
