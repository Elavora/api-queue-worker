'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const LICENSE = h.lines([
    'MIT License',
    '',
    'Copyright (c) 2026 Elavora',
    '',
    'Permission is hereby granted, free of charge, to any person obtaining a copy',
    'of this software and associated documentation files (the "Software"), to deal',
    'in the Software without restriction, including without limitation the rights',
    'to use, copy, modify, merge, publish, distribute, sublicense, and/or sell',
    'copies of the Software, and to permit persons to whom the Software is',
    'furnished to do so, subject to the following conditions:',
    '',
    'The above copyright notice and this permission notice shall be included in all',
    'copies or substantial portions of the Software.',
    '',
    'THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR',
    'IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,',
    'FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE',
    'AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER',
    'LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,',
    'OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE',
    'SOFTWARE.',
]);

const SECURITY = h.lines([
    '# Security Policy',
    '',
    '## Reporting a Vulnerability',
    '',
    'Please do not open a public issue for security vulnerabilities.',
    '',
    'Use GitHub Security Advisories or private vulnerability reporting for this repository when available.',
    '',
    '## Scope',
    '',
    'This policy applies to this package and the public `elavora/api-*` package family.',
    '',
    '## Supported Versions',
    '',
    'Security fixes are provided for the latest stable release published on Packagist.',
]);

const CODEOWNERS = h.lines([
    '# Owners padrao do repositorio.',
    '* @ScriptPlayerAgent @Felipe-Cavalca',
]);

const ISSUE_CONFIG = h.lines([
    'blank_issues_enabled: false',
    'contact_links:',
    '  - name: Vulnerabilidade de seguranca',
    `    url: https://github.com/Elavora/${h.REPO}/security/policy`,
    '    about: Reporte vulnerabilidades conforme a politica de seguranca do repositorio.',
]);

const BUG_REPORT = h.lines([
    'name: Bug report',
    'description: Reporte um comportamento incorreto',
    'title: "fix: "',
    'labels: ["bug"]',
    'body:',
    '  - type: textarea',
    '    id: summary',
    '    attributes:',
    '      label: Resumo',
    '      description: Descreva o problema de forma objetiva.',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: reproduce',
    '    attributes:',
    '      label: Como reproduzir',
    '      description: Liste os passos para reproduzir o problema.',
    '      placeholder: |',
    '        1.',
    '        2.',
    '        3.',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: expected',
    '    attributes:',
    '      label: Comportamento esperado',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: context',
    '    attributes:',
    '      label: Contexto adicional',
    '      description: Informe versao do pacote, PHP, dependencias ou logs relevantes.',
]);

const DOCUMENTATION = h.lines([
    'name: Documentation',
    'description: Sugira ajuste na documentacao',
    'title: "docs: "',
    'labels: ["documentation"]',
    'body:',
    '  - type: textarea',
    '    id: location',
    '    attributes:',
    '      label: Onde esta o problema',
    '      description: Informe arquivo, secao ou link.',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: change',
    '    attributes:',
    '      label: Mudanca sugerida',
    '    validations:',
    '      required: true',
]);

const FEATURE_REQUEST = h.lines([
    'name: Feature request',
    'description: Sugira uma melhoria ou novo comportamento',
    'title: "feat: "',
    'labels: ["enhancement"]',
    'body:',
    '  - type: textarea',
    '    id: problem',
    '    attributes:',
    '      label: Problema ou necessidade',
    '      description: Qual problema essa mudanca resolveria?',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: proposal',
    '    attributes:',
    '      label: Proposta',
    '      description: Descreva a solucao esperada.',
    '    validations:',
    '      required: true',
    '  - type: textarea',
    '    id: alternatives',
    '    attributes:',
    '      label: Alternativas consideradas',
]);

const FILES = {
    'LICENSE': LICENSE,
    'SECURITY.md': SECURITY,
    '.github/CODEOWNERS': CODEOWNERS,
    '.github/ISSUE_TEMPLATE/config.yml': ISSUE_CONFIG,
    '.github/ISSUE_TEMPLATE/bug_report.yml': BUG_REPORT,
    '.github/ISSUE_TEMPLATE/documentation.yml': DOCUMENTATION,
    '.github/ISSUE_TEMPLATE/feature_request.yml': FEATURE_REQUEST,
};

test.describe('arquivos de comunidade', () => {
    for (const [file, content] of Object.entries(FILES)) {
        test(`${file} existe e tem o conteúdo documentado`, () => {
            h.assertExactContent(assert, file, content);
        });
    }

    test('a LICENSE é a MIT com "Copyright (c) 2026 Elavora"', () => {
        assert.ok(h.exists('LICENSE'), 'LICENSE deveria existir');
        const text = h.read('LICENSE');
        assert.ok(text.startsWith('MIT License\n'));
        assert.ok(text.includes('Copyright (c) 2026 Elavora\n'));
    });

    test('o link de segurança do config.yml aponta para o repositório certo', () => {
        assert.ok(h.exists('.github/ISSUE_TEMPLATE/config.yml'));
        assert.ok(
            h.read('.github/ISSUE_TEMPLATE/config.yml').includes(`https://github.com/Elavora/${h.REPO}/security/policy`),
        );
    });
});
