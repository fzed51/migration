<?php

namespace Migration;

/**
 * Class Migration
 * @package Migration
 */
class MigrationInit
{
    /** dossier de migration écrit dans le modèle et créé par init */
    public const MIGRATION_DIRECTORY = './db/migration';

    /**
     * config_file
     * @var string
     */
    private $config_file;

    /**
     * MigrationInit constructor.
     * @param string $config_file
     * @throws \Exception
     */
    public function __construct(string $config_file)
    {
        if (empty($config_file)) {
            throw new \RuntimeException("Impossible d'initialiser le fichier de configuration.");
        }
        $this->config_file = $config_file;
    }

    /**
     * Lance la commande
     */
    public function run(): void
    {
        if (!is_file($this->config_file)) {
            $this->createMigrationDirectory();
            $structure = [
                'migration_directory' => self::MIGRATION_DIRECTORY,
                'config_extern' => [
                    'file' => '',
                    'array_path' => '',
                    'provider' => 'provider',
                    'host' => 'host',
                    'port' => 'port',
                    'name' => 'name',
                    'user' => 'user',
                    'pass' => 'pass'
                ],
                'config_intern' => [
                    'provider' => '',
                    'host' => '',
                    'port' => 0,
                    'name' => '',
                    'user' => '',
                    'pass' => ''
                ]
            ];
            $tmp = $this->config_file . '.tmp';
            if (file_put_contents($tmp, json_encode($structure, JSON_PRETTY_PRINT)) === false) {
                throw new \RuntimeException("Impossible d'écrire le fichier de configuration temporaire.");
            }
            if (!rename($tmp, $this->config_file)) {
                unlink($tmp);
                throw new \RuntimeException("Impossible de créer le fichier de configuration.");
            }
        } else {
            throw new \RuntimeException(
                "Impossible d'initialiser le fichier de configuration car ce fichier existe déjà."
            );
        }
    }

    /**
     * crée le dossier de migration par défaut (relatif au répertoire courant) s'il n'existe pas
     */
    private function createMigrationDirectory(): void
    {
        $directory = self::MIGRATION_DIRECTORY;
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Impossible de créer le dossier de migration '$directory'.");
        }
    }
}
