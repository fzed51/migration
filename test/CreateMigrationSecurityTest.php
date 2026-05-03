<?php
declare(strict_types=1);

namespace Migration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Tests de sécurité pour CreateMigration — SEC-05 (TOCTOU).
 */
class CreateMigrationSecurityTest extends PHPUnitTestCase
{
    private ?string $tmpDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/create_migration_sec_' . uniqid('', true);
        mkdir($this->tmpDir . '/sqlite', 0755, true);
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

    private function makeCreateMigration(string $name): CreateMigration
    {
        assert($this->tmpDir !== null);
        $config = new MigrationConfig($this->tmpDir, 'sqlite', 'localhost', 3306, 'db', 'user', 'pass');
        return (new CreateMigration($config))->setNewMigrationName($name);
    }

    private function runSilent(CreateMigration $cm): void
    {
        ob_start();
        try {
            $cm->run();
        } finally {
            ob_end_clean();
        }
    }

    // -------------------------------------------------------------------------
    // SEC-05 — Race condition TOCTOU
    // -------------------------------------------------------------------------

    public function testCreatesFileWithFirstIndex(): void
    {
        assert($this->tmpDir !== null);
        $this->runSilent($this->makeCreateMigration('test_migration'));
        $date = date('Ymd');
        $this->assertFileExists($this->tmpDir . '/sqlite/' . $date . '-01-test_migration.sql');
    }

    public function testSkipsIndexWhenAnyFileWithSameDateAndIndexExists(): void
    {
        assert($this->tmpDir !== null);
        $date = date('Ymd');
        touch($this->tmpDir . '/sqlite/' . $date . '-01-other_migration.sql');
        $this->runSilent($this->makeCreateMigration('test_migration'));
        $this->assertFileExists($this->tmpDir . '/sqlite/' . $date . '-02-test_migration.sql');
        $this->assertFileDoesNotExist($this->tmpDir . '/sqlite/' . $date . '-01-test_migration.sql');
    }

    public function testAtomicCreationDoesNotOverwriteExistingFile(): void
    {
        assert($this->tmpDir !== null);
        $date = date('Ymd');
        $existing = $this->tmpDir . '/sqlite/' . $date . '-01-test_migration.sql';
        file_put_contents($existing, 'contenu existant');

        $this->runSilent($this->makeCreateMigration('test_migration'));

        $this->assertStringEqualsFile($existing, 'contenu existant');
        $this->assertFileExists($this->tmpDir . '/sqlite/' . $date . '-02-test_migration.sql');
    }

    public function testCreatesFileAtNextIndexWhenMultipleFilesExist(): void
    {
        assert($this->tmpDir !== null);
        $date = date('Ymd');
        touch($this->tmpDir . '/sqlite/' . $date . '-01-first.sql');
        touch($this->tmpDir . '/sqlite/' . $date . '-02-second.sql');
        $this->runSilent($this->makeCreateMigration('third_migration'));
        $this->assertFileExists($this->tmpDir . '/sqlite/' . $date . '-03-third_migration.sql');
    }
}
