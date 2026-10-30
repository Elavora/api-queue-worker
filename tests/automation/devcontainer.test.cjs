'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const FILE = '.devcontainer/devcontainer.json';

const EXPECTED = {
    name: 'Elavora PHP 8.3',
    image: 'ghcr.io/elavora/php-dev:8.3-latest',
    remoteUser: 'vscode',
    postCreateCommand:
        'git config --global --add safe.directory "$(pwd)" && composer install --no-interaction --prefer-dist',
    customizations: {
        vscode: {
            extensions: [
                'xdebug.php-debug',
                'bmewburn.vscode-intelephense-client',
                'junstyle.php-cs-fixer',
                'EditorConfig.EditorConfig',
            ],
            settings: {
                'php.validate.executablePath': '/usr/local/bin/php',
                'php-cs-fixer.executablePath': '/usr/local/bin/php-cs-fixer',
                'php-cs-fixer.config': '.php-cs-fixer.php;.php-cs-fixer.dist.php',
                'php-cs-fixer.rules': '@PER-CS',
                'php-cs-fixer.onsave': false,
                '[php]': {
                    'editor.defaultFormatter': 'junstyle.php-cs-fixer',
                    'editor.formatOnSave': true,
                },
            },
        },
    },
};

function load() {
    assert.ok(h.exists(FILE), `${FILE} deveria existir`);
    return h.readJson(FILE); // JSON.parse falha se houver comentários
}

test.describe('.devcontainer/devcontainer.json', () => {
    test('é JSON válido e igual ao conteúdo documentado', () => {
        assert.deepEqual(load(), EXPECTED);
    });

    test('image é a tag 8.3-latest, nunca versão fixa, outra tag móvel nem nome local', () => {
        const { image } = load();
        assert.equal(image, h.IMAGE);
        assert.equal(image, 'ghcr.io/elavora/php-dev:8.3-latest');
        for (const bad of [
            'ghcr.io/elavora/php-dev:8.3-0.3.0',
            'ghcr.io/elavora/php-dev:8.3-0.2.0',
            'ghcr.io/elavora/php-dev:8.3',
            'ghcr.io/elavora/php-dev:latest',
            'elavora/php-dev:8.3',
        ]) {
            assert.notEqual(image, bad);
        }
        assert.doesNotMatch(image, /^ghcr\.io\/elavora\/php-dev:8\.3-\d+\.\d+\.\d+$/);
    });

    test('remoteUser vscode e postCreateCommand com composer install', () => {
        const cfg = load();
        assert.equal(cfg.remoteUser, 'vscode');
        assert.match(cfg.postCreateCommand, /composer install/);
    });

    test('extensões de estilo e formatação ao salvar em PHP', () => {
        const vs = load().customizations.vscode;
        assert.ok(vs.extensions.includes('junstyle.php-cs-fixer'));
        assert.ok(vs.extensions.includes('EditorConfig.EditorConfig'));
        assert.equal(vs.settings['[php]']['editor.formatOnSave'], true);
    });

    test('o quality.yml não passa a imagem: o CI usa o padrão do pipeline central', () => {
        const quality = h.read('.github/workflows/quality.yml');
        assert.doesNotMatch(quality, /^\s+image:/m, 'quality.yml não deveria passar a entrada image');
        assert.doesNotMatch(quality, /php-dev/, 'quality.yml não deveria citar a imagem');
    });
});
