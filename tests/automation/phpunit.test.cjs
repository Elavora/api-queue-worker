'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const FILE = 'phpunit.xml';

/**
 * Parser mínimo de XML: devolve a árvore de elementos { name, attrs, children, text }.
 * Ignora declaração, comentários e CDATA; suficiente para o phpunit.xml deste repositório.
 */
function parseXml(text) {
    const root = { name: '#document', attrs: {}, children: [], text: '' };
    const stack = [root];
    const re = /<!--[\s\S]*?-->|<\?[\s\S]*?\?>|<!\[CDATA\[[\s\S]*?\]\]>|<(\/?)([A-Za-z_][\w.:-]*)([^>]*?)(\/?)>|([^<]+)/g;
    let m;
    while ((m = re.exec(text)) !== null) {
        const top = stack[stack.length - 1];
        if (m[5] !== undefined) {
            top.text += m[5];
            continue;
        }
        if (m[2] === undefined) continue;
        const [, closing, name, rawAttrs, selfClosing] = m;
        if (closing) {
            assert.equal(top.name, name, `fechamento </${name}> inesperado em ${FILE}`);
            stack.pop();
            continue;
        }
        const attrs = {};
        for (const a of rawAttrs.matchAll(/([\w.:-]+)\s*=\s*"([^"]*)"/g)) attrs[a[1]] = a[2];
        const el = { name, attrs, children: [], text: '' };
        top.children.push(el);
        if (!selfClosing) stack.push(el);
    }
    assert.equal(stack.length, 1, `${FILE} tem elementos sem fechamento`);
    return root;
}

function childrenNamed(el, name) {
    return el.children.filter((c) => c.name === name);
}

function phpunitRoot() {
    assert.ok(h.exists(FILE), `${FILE} deveria existir`);
    const doc = parseXml(h.read(FILE));
    assert.equal(doc.children.length, 1, `${FILE} deveria ter um único elemento raiz`);
    const root = doc.children[0];
    assert.equal(root.name, 'phpunit', 'o elemento raiz deveria ser <phpunit>');
    return root;
}

/** Conteúdo exato documentado; o nome da suíte é o do próprio pacote e não muda na adoção. */
function expectedXml(suiteName) {
    return h.lines([
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<phpunit bootstrap="tests/bootstrap.php"',
        '        cacheDirectory=".phpunit.cache"',
        '        colors="true"',
        '        failOnWarning="true">',
        '    <testsuites>',
        `        <testsuite name="${suiteName}">`,
        '            <directory>tests</directory>',
        '        </testsuite>',
        '    </testsuites>',
        '    <source>',
        '        <include>',
        '            <directory>src</directory>',
        '        </include>',
        '    </source>',
        '</phpunit>',
    ]);
}

test.describe('phpunit.xml', () => {
    test('tem o conteúdo exato documentado', () => {
        assert.ok(h.exists(FILE), `${FILE} deveria existir`);
        const actual = h.read(FILE);
        const suite = actual.match(/<testsuite name="([^"]+)">/);
        assert.ok(suite, 'a suíte nomeada deveria existir');
        assert.equal(actual, expectedXml(suite[1]), `${FILE} difere do conteúdo exato documentado`);
    });

    test('tem <source>/<include>/<directory>src</directory> como filho direto de <phpunit>', () => {
        const root = phpunitRoot();
        const sources = childrenNamed(root, 'source');
        assert.equal(sources.length, 1, '<phpunit> deveria ter exatamente um <source> filho direto');
        const includes = childrenNamed(sources[0], 'include');
        assert.equal(includes.length, 1, '<source> deveria ter exatamente um <include>');
        const dirs = childrenNamed(includes[0], 'directory').map((d) => d.text.trim());
        assert.deepEqual(dirs, ['src'], '<include> deveria ter só <directory>src</directory>');
    });

    test('o bloco <source> aparece como documentado, com 4 espaços por nível', () => {
        const expected = h.lines([
            '    <source>',
            '        <include>',
            '            <directory>src</directory>',
            '        </include>',
            '    </source>',
        ]);
        assert.ok(h.read(FILE).includes(expected), `${FILE} deveria conter o bloco:\n${expected}`);
    });

    test('mantém bootstrap, cacheDirectory, colors e failOnWarning="true"', () => {
        const { attrs } = phpunitRoot();
        assert.equal(attrs.bootstrap, 'tests/bootstrap.php');
        assert.equal(attrs.cacheDirectory, '.phpunit.cache');
        assert.equal(attrs.colors, 'true');
        assert.equal(attrs.failOnWarning, 'true');
    });

    test('mantém a suíte que roda a pasta tests', () => {
        const root = phpunitRoot();
        const suites = childrenNamed(root, 'testsuites');
        assert.equal(suites.length, 1, '<phpunit> deveria ter um <testsuites>');
        const dirs = childrenNamed(suites[0], 'testsuite')
            .flatMap((s) => childrenNamed(s, 'directory'))
            .map((d) => d.text.trim());
        assert.ok(dirs.includes('tests'), 'alguma <testsuite> deveria ter <directory>tests</directory>');
    });
});
