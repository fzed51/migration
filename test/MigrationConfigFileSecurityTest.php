<?php
declare(strict_types=1);

namespace Migration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RuntimeException;

/**
 * Tests de sécurité pour MigrationConfigFile — Faille #1 :
 * exécution arbitraire de PHP via config_extern.file sans restriction de chemin.
 *
 * Avant correctif : testRejectsExternPhpOutsideProjectDirectory et
 * testRejectsExternFileWithNonPhpExtension ÉCHOUENT (la faille est exploitable).
 * Après correctif : tous les tests PASSENT.
 */
class MigrationConfigFileSecurityTest extends PHPUnitTestCase
{
    /** @var string */
    private string $tempDir;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migration_sec_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'migrations');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $migrationsDir = $this->tempDir . DIRECTORY_SEPARATOR . 'migrations';
        if (is_dir($migrationsDir)) {
            rmdir($migrationsDir);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function writeTempFile(string $path, string $content): string
    {
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    /** @param array<string, mixed> $config */
    private function writeConfigJson(array $config): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'migration-config.json';
        return $this->writeTempFile($path, (string)json_encode($config, JSON_PRETTY_PRINT));
    }

    // -------------------------------------------------------------------------
    // Faille #1a — fichier PHP hors du répertoire du projet
    // -------------------------------------------------------------------------

    /**
     * AVANT correctif : ce test ÉCHOUE — aucune exception n'est levée,
     * le fichier PHP externe est silencieusement exécuté.
     * APRÈS correctif : RuntimeException levée → test PASSE.
     */
    public function testRejectsExternPhpOutsideProjectDirectory(): void
    {
        $evilFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evil_' . uniqid() . '.php';
        $this->writeTempFile($evilFile, '<?php return ["provider" => "sqlite", "name" => ":memory:", "host" => "", "port" => 0, "user" => "", "pass" => ""];');

        $configFile = $this->writeConfigJson([
            'migration_directory' => $this->tempDir . DIRECTORY_SEPARATOR . 'migrations',
            'config_extern' => ['file' => $evilFile],
        ]);

        $this->expectException(RuntimeException::class);
        new MigrationConfigFile($configFile);
    }

    // -------------------------------------------------------------------------
    // Faille #1b — extension non-PHP acceptée
    // -------------------------------------------------------------------------

    /**
     * AVANT correctif : ce test ÉCHOUE — aucune exception n'est levée
     * pour l'extension (une erreur peut survenir plus tard, mais pas de contrôle explicite).
     * APRÈS correctif : RuntimeException levée dès la vérification d'extension → test PASSE.
     */
    public function testRejectsExternFileWithNonPhpExtension(): void
    {
        $txtFile = $this->tempDir . DIRECTORY_SEPARATOR . 'config.txt';
        $this->writeTempFile($txtFile, 'not php');

        $configFile = $this->writeConfigJson([
            'migration_directory' => $this->tempDir . DIRECTORY_SEPARATOR . 'migrations',
            'config_extern' => ['file' => $txtFile],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/\.php/');
        new MigrationConfigFile($configFile);
    }

    // -------------------------------------------------------------------------
    // Cas légitime — régression
    // -------------------------------------------------------------------------

    /**
     * Un fichier PHP légitime dans le même répertoire que le config
     * doit continuer à fonctionner après le correctif.
     */
    public function testAcceptsLegitimateExternPhpInSameDirectory(): void
    {
        $phpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'db-config.php';
        $this->writeTempFile($phpFile, '<?php return ["provider" => "sqlite", "name" => ":memory:", "host" => "", "port" => 0, "user" => "", "pass" => ""];');

        $configFile = $this->writeConfigJson([
            'migration_directory' => $this->tempDir . DIRECTORY_SEPARATOR . 'migrations',
            'config_extern' => ['file' => $phpFile],
        ]);

        $config = new MigrationConfigFile($configFile);
        self::assertEquals('sqlite', $config->provider);
    }
}
