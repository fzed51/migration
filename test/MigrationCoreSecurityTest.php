<?php
declare(strict_types=1);

namespace Migration;

use Helper\PDOFactory;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RuntimeException;

/**
 * Tests de sécurité pour MigrationCore — SEC-02 et SEC-03.
 */
class MigrationCoreSecurityTest extends PHPUnitTestCase
{
    private ?string $tmpDir = null;
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/migration_sec_test_' . uniqid('', true);
        mkdir($this->tmpDir . '/sqlite', 0755, true);
        $this->pdo = PDOFactory::sqlite();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->tmpDir !== null) {
            foreach (glob($this->tmpDir . '/sqlite/*') ?: [] as $f) {
                unlink((string)$f);
            }
            rmdir($this->tmpDir . '/sqlite');
            rmdir($this->tmpDir);
        }
    }

    private function makeCore(): MigrationCore
    {
        return new MigrationCore();
    }

    private function makeCoreWithDb(): MigrationCore
    {
        assert($this->pdo instanceof PDO);
        assert($this->tmpDir !== null);
        return (new MigrationCore())
            ->setPdo($this->pdo)
            ->setProvider('sqlite')
            ->setMigrationDirectory($this->tmpDir);
    }

    private function createSqlFile(string $content = 'SELECT 1'): string
    {
        $path = $this->tmpDir . '/sqlite/20240101-01-test.sql';
        file_put_contents($path, $content);
        return $path;
    }

    private function runSilent(MigrationCore $core): void
    {
        ob_start();
        try {
            $core->run();
        } finally {
            ob_end_clean();
        }
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

    // -------------------------------------------------------------------------
    // SEC-03 — Vérification d'intégrité checksum à la relecture
    // -------------------------------------------------------------------------

    public function testAppliedMigrationIsSkippedWhenChecksumMatches(): void
    {
        $this->createSqlFile('SELECT 1');
        $core = $this->makeCoreWithDb();
        $this->runSilent($core);
        // Deuxième run : checksum identique, aucune exception attendue
        $this->runSilent($core);
        $this->addToAssertionCount(1);
    }

    public function testTamperedMigrationThrowsRuntimeException(): void
    {
        $path = $this->createSqlFile('SELECT 1');
        $core = $this->makeCoreWithDb();
        $this->runSilent($core);

        file_put_contents($path, 'DROP TABLE users');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Intégrité compromise/');
        $core->run();
    }

    public function testUntamperedMigrationDoesNotRaiseException(): void
    {
        $this->createSqlFile('CREATE TABLE sec3_test (id INTEGER PRIMARY KEY)');
        $core = $this->makeCoreWithDb();
        $this->runSilent($core);
        $this->runSilent($core);
        $this->addToAssertionCount(1);
    }
}
