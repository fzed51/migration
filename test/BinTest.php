<?php

declare(strict_types=1);

namespace Migration;

use Migration\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Test des différentes commandes de la CLI
 */
class BinTest extends DbTestCase
{
    /** @var string */
    protected $cmd = __DIR__ . '/../bin/migrate';

    /**
     * Tentative de résolution de bug xdebug
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . " " . var_export($this, true);
    }

    /**
     * lance l'application avec les paramètres donnés
     * @param array<string,mixed> $input
     */
    protected function runMigrate(array $input = []): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        // terminal large : Symfony coupe les messages d'erreur à la largeur du terminal (80 en CI)
        $columns = getenv('COLUMNS');
        putenv('COLUMNS=1000');
        try {
            $tester->run($input, ['capture_stderr_separately' => true, 'decorated' => false]);
        } finally {
            putenv($columns === false ? 'COLUMNS' : "COLUMNS=$columns");
        }
        return $tester;
    }

    /**
     * sortie d'erreur sur une seule ligne : Symfony la coupe à la largeur du terminal
     */
    protected function errorOutput(ApplicationTester $tester): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $tester->getErrorOutput()));
    }

    /**
     * test de la commande init
     */
    public function testMigrateInitCommand(): void
    {
        $this->deleteConfigFile();
        $this->deleteDbFile();
        $createdDirectories = array_filter(
            ['./db', MigrationInit::MIGRATION_DIRECTORY],
            static fn (string $dir): bool => !is_dir($dir)
        );
        try {
            $tester = $this->runMigrate(['command' => 'init']);
            self::assertSame(0, $tester->getStatusCode());
            self::assertFileExists(self::CONFIGFILE);
            self::assertDirectoryExists(MigrationInit::MIGRATION_DIRECTORY);
        } finally {
            // supprime uniquement les dossiers créés par le test, du plus profond au plus haut
            foreach (array_reverse($createdDirectories) as $dir) {
                if (is_dir($dir)) {
                    rmdir($dir);
                }
            }
        }
    }

    /**
     * init échoue si le fichier de configuration existe déjà
     */
    public function testMigrateInitCommandFailsIfConfigExists(): void
    {
        $this->putMigrationConfigFile();
        $tester = $this->runMigrate(['command' => 'init']);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/existe déjà/', $this->errorOutput($tester));
    }

    /**
     * run sans fichier de configuration
     */
    public function testMigrateWithNothink(): void
    {
        $this->deleteConfigFile();
        $this->deleteDbFile();
        $tester = $this->runMigrate(['command' => 'run']);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression(
            "/Impossible de trouver le fichier de configuration \.\/migration-config\.json/",
            $this->errorOutput($tester)
        );
    }

    /**
     * run avec une configuration mais sans base
     */
    public function testMigrateWithConfig(): void
    {
        $this->putMigrationConfigFile();
        $this->deleteDbFile();
        $tester = $this->runMigrate(['command' => 'run']);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression(
            "/Le fichier \.\/data.sqlite n'a pas été trouvé!/",
            $this->errorOutput($tester)
        );
    }

    /**
     * test de la commande provider
     */
    public function testCreatProviderDirectory(): void
    {
        $this->expectOutputRegex("#Le dossier [^\\\\]*/migration/mysql a été créé avec succès\.#");
        $this->deleteConfigFile();
        $this->deleteDbFile();
        $this->putMigrationConfigFile();
        $tester = $this->runMigrate(['command' => 'provider', 'name' => 'mysql']);
        self::assertSame(0, $tester->getStatusCode());
        self::assertDirectoryExists(__DIR__ . "/migration/mysql");
        rmdir(__DIR__ . "/migration/mysql");
    }

    /**
     * provider sur un dossier existant réussit sans le recréer
     */
    public function testCreatProviderDirectoryAlreadyExists(): void
    {
        $this->expectOutputRegex("#Le dossier [^\\\\]*/migration/sqlite existe déjà\.#");
        $this->putMigrationConfigFile();
        $providerDirectory = __DIR__ . "/migration/sqlite";
        mkdir($providerDirectory);
        try {
            $tester = $this->runMigrate(['command' => 'provider', 'name' => 'sqlite']);
            self::assertSame(0, $tester->getStatusCode());
        } finally {
            rmdir($providerDirectory);
        }
    }

    /**
     * test de la commande new
     */
    public function testCreateNewMigration(): void
    {
        $this->expectOutputRegex("#^Création du fichier '.+[\\\\/]sqlite[\\\\/]\d{8}-01-creation_user\.sql' pour sqlite\.\r?\n#m");
        $this->putMigrationConfigFile();
        $providerDirectory = __DIR__ . "/migration/sqlite";
        if (!is_dir($providerDirectory)) {
            mkdir($providerDirectory);
        }
        try {
            $tester = $this->runMigrate(['command' => 'new', 'name' => 'Création User']);
            self::assertSame(0, $tester->getStatusCode());
            self::assertCount(1, glob($providerDirectory . '/????????-01-creation_user.sql') ?: []);
        } finally {
            array_map('unlink', glob($providerDirectory . '/*.sql') ?: []);
            rmdir($providerDirectory);
        }
    }

    /**
     * new échoue si aucun dossier provider n'existe
     */
    public function testCreateNewMigrationFailsWithoutProviderDirectory(): void
    {
        $this->putMigrationConfigFile();
        self::assertSame([], glob(__DIR__ . '/migration/*', GLOB_ONLYDIR) ?: []);
        $tester = $this->runMigrate(['command' => 'new', 'name' => 'create_user']);
        self::assertNotSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression("/Aucun dossier provider n'existe/", $this->errorOutput($tester));
        self::assertSame([], glob(__DIR__ . '/migration/*/*.sql') ?: []);
    }

    /**
     * test de la commande run
     */
    public function testMigrateWithConfigAndDbfile(): void
    {
        $this->expectOutputRegex("/migration : setup migration/");
        $this->putMigrationConfigFile();
        $this->createEmptyDbFile();
        $tester = $this->runMigrate(['command' => 'run']);
        self::assertSame(0, $tester->getStatusCode());
        $nbStory = $this->query()->countElement('migration_story');
        self::assertEquals(0, $nbStory);
    }

    /**
     * sans commande, la vue d'ensemble est affichée et la base n'est pas touchée
     */
    public function testWithoutCommandDisplaysOverviewWithoutTouchingDatabase(): void
    {
        $this->putMigrationConfigFile();
        $this->deleteDbFile();
        $this->createEmptyDbFile();
        $tester = $this->runMigrate([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Workflow :', $tester->getDisplay());
        self::assertStringContainsString('MODIFIE LA BASE', $tester->getDisplay());
        $stm = $this->getPdo()->query("select name from sqlite_master where name = 'migration_story'");
        self::assertNotFalse($stm);
        self::assertSame([], $stm->fetchAll());
    }

    /**
     * --help sans commande affiche la vue d'ensemble
     */
    public function testHelpWithoutCommandDisplaysOverview(): void
    {
        $tester = $this->runMigrate(['--help' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Workflow :', $tester->getDisplay());
        self::assertStringContainsString('Available commands:', $tester->getDisplay());
    }

    /**
     * --help sans commande respecte --format
     */
    public function testHelpWithoutCommandKeepsFormat(): void
    {
        $tester = $this->runMigrate(['--help' => true, '--format' => 'json']);
        self::assertSame(0, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame(Application::NAME, $data['application']['name'] ?? null);
        self::assertContains('run', array_column($data['commands'] ?? [], 'name'));
    }

    /**
     * chaque commande documente ses effets pour un humain ou un agent
     */
    public function testEachCommandHasStructuredHelp(): void
    {
        $application = new Application();
        foreach (['init', 'provider', 'new', 'run'] as $name) {
            $command = $application->find($name);
            self::assertNotSame('', $command->getDescription(), $name);
            foreach (['Effet', 'Préconditions', 'Idempotence', 'Sortie', 'Erreurs fréquentes', 'Exemples', 'Conventions'] as $section) {
                self::assertStringContainsString($section, $command->getHelp(), "$name : section $section");
            }
            self::assertTrue($command->getDefinition()->hasOption('config'), $name);
        }
    }

    /**
     * le binaire se lance et retourne un code de sortie
     */
    public function testBinaryReturnsExitCode(): void
    {
        [$exitCode, $stdout] = $this->runBinary(['--version', '--no-ansi']);
        self::assertSame(0, $exitCode);
        self::assertStringStartsWith('migrate ', $stdout);
    }

    /**
     * "--format json" (valeur séparée par un espace) n'est pas pris pour un nom de commande
     */
    public function testBinaryHelpWithSeparatedFormatValue(): void
    {
        [$exitCode, $stdout] = $this->runBinary(['--help', '--format', 'json']);
        self::assertSame(0, $exitCode);
        $data = json_decode($stdout, true);
        self::assertIsArray($data);
        self::assertContains('run', array_column($data['commands'] ?? [], 'name'));
    }

    /**
     * lance le vrai binaire
     * @param string[] $arguments
     * @return array{int, string} code de retour et stdout
     */
    protected function runBinary(array $arguments): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY, $this->cmd], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $stdout = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $stdout];
    }
}
