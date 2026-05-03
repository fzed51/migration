<?php
declare(strict_types=1);

namespace Migration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Tests de sécurité pour MigrationCore — SEC-02 :
 * path traversal via provider non validé dans setProvider().
 */
class MigrationCoreSecurityTest extends PHPUnitTestCase
{
    private function makeCore(): MigrationCore
    {
        return new MigrationCore();
    }

    // -------------------------------------------------------------------------
    // SEC-02 — Whitelist provider
    // -------------------------------------------------------------------------

    public function testRejectsPathTraversalProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeCore()->setProvider('../evil_dir');
    }

    public function testRejectsArbitraryStringProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeCore()->setProvider('oracle');
    }

    public function testRejectsEmptyProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeCore()->setProvider('');
    }

    /** @dataProvider validProviders */
    public function testAcceptsValidProvider(string $provider): void
    {
        $core = $this->makeCore()->setProvider($provider);
        self::assertInstanceOf(MigrationCore::class, $core);
    }

    /** @return array<string, array{string}> */
    public static function validProviders(): array
    {
        return [
            'mysql'    => ['mysql'],
            'sqlite'   => ['sqlite'],
            'postgres' => ['postgres'],
        ];
    }
}
