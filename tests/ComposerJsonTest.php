<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Campos obrigatórios do composer.json do pacote.
 *
 * @see docs/USO.md#composerjson
 */
final class ComposerJsonTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $composer;

    protected function setUp(): void
    {
        $this->composer = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @see docs/USO.md#composerjson */
    public function testRequiresPhp83(): void
    {
        self::assertSame('>=8.3', $this->composer['require']['php'] ?? null);
    }

    /** @see docs/USO.md#composerjson */
    public function testRequiresFramework2(): void
    {
        self::assertSame('^2.0', $this->composer['require']['elavora/api-framework'] ?? null);
    }

    /** @see docs/USO.md#composerjson */
    public function testBranchAliasIs10Dev(): void
    {
        self::assertSame('1.0-dev', $this->composer['extra']['branch-alias']['dev-main'] ?? null);
    }

    /** @see docs/USO.md#composerjson */
    public function testAutoloadIsUnchanged(): void
    {
        self::assertSame(
            ['Elavora\\Api\\Extension\\QueueWorker\\' => 'src/'],
            $this->composer['autoload']['psr-4'] ?? null,
        );
    }

    /** @see docs/USO.md#composerjson */
    public function testPhpunitConstraintIsUnchanged(): void
    {
        self::assertSame('^10.5 || ^11.0 || ^12.0', $this->composer['require-dev']['phpunit/phpunit'] ?? null);
    }

    /** @see docs/USO.md#composerjson */
    public function testTestScriptIsUnchanged(): void
    {
        self::assertSame('phpunit -c phpunit.xml', $this->composer['scripts']['test'] ?? null);
    }

    /** @see docs/USO.md#composerjson */
    public function testHasNoRepositories(): void
    {
        self::assertArrayNotHasKey('repositories', $this->composer);
    }
}
