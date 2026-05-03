<?php

namespace Migration;

use Helper\DbQuickUse;
use Helper\PDOFactory;
use PDO;

class DbTestCase extends TestCase
{
    protected const DBPROVIDER = "sqlite";
    protected const DBFILE = "./data.sqlite";
    /** @var \PDO|null : instance d'acces à la base */
    private ?PDO $pdo = null;

    /**
     * @return \Helper\DbQuickUse
     */
    public function query(): DbQuickUse
    {
        $pdo = $this->getPdo();
        return new DbQuickUse($pdo);
    }

    /**
     * @return \PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            if (!is_file(self::DBFILE)) {
                $this->createEmptyDbFile();
            }
            $this->pdo = PDOFactory::sqlite(self::DBFILE);
        }
        return $this->pdo;
    }

    protected function createEmptyDbFile(): void
    {
        file_put_contents(self::DBFILE, '');
    }

    /**
     *
     */
    protected function putMigrationConfigFile(): void
    {
        $config = [
            'migration_directory' => __DIR__ . "/migration",
            "config_intern" => [
                "provider" => self::DBPROVIDER,
                "name" => self::DBFILE
            ]
        ];
        if (!is_dir(__DIR__ . "/migration")) {
            if (!mkdir($concurrentDirectory = __DIR__ . "/migration") && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        try {
            file_put_contents(self::CONFIGFILE, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (\JsonException $e) {
            /** @noinspection ForgottenDebugOutputInspection */
            error_log($e->getMessage() . ", " . $e->getFile() . "(" . $e->getLine() . ")");
        }
    }

    protected function deleteDbFile(): void
    {
        if (file_exists(self::DBFILE)) {
            $this->pdo = null;
            $deleted = false;
            while (!$deleted) {
                try {
                    if (is_writable(self::DBFILE)) {
                        $deleted = unlink(self::DBFILE);
                    }
                } catch (\Throwable $t) {
                    $deleted = false;
                }
            }
        }
    }
}
