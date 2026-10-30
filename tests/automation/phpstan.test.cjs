'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const FILE = 'phpstan.neon';

const EXPECTED = h.lines([
    'parameters:',
    '    level: 8',
    '    paths:',
    ...h.PHPSTAN_PATHS.map((p) => `        - ${p}`),
    '    tmpDir: .phpstan.cache',
]);

test.describe('phpstan.neon', () => {
    test('tem o conteúdo exato documentado (nível 8, só as pastas de código, tmpDir .phpstan.cache)', () => {
        h.assertExactContent(assert, FILE, EXPECTED);
    });

    test('não tem baseline nem ignoreErrors, e os testes ficam de fora', () => {
        assert.ok(h.exists(FILE), `${FILE} deveria existir`);
        const text = h.read(FILE);
        assert.doesNotMatch(text, /baseline|ignoreErrors|includes:/);
        assert.doesNotMatch(text, /^\s*-\s*tests\b/m);
    });

    test('não existe arquivo de baseline do PHPStan', () => {
        assert.deepEqual(h.namesIn('.').filter((n) => /baseline.*\.neon$/i.test(n)), []);
    });
});
