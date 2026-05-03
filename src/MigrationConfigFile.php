<?php

/**
 * User: fabien.sanchez
 * Date: 14/09/2018
 * Time: 09:35
 */

namespace Migration;

use DomainException;
use RuntimeException;
use Throwable;

/**
 * Class MigrationConfig
 * @package Migration
 */
class MigrationConfigFile extends MigrationConfig
{

    /**
     * config du fichier
     * @var array<string,mixed>
     */
    private $config;

    /** @var string chemin absolu réel du fichier de configuration */
    private string $configFilename;

    /**
     * MigrationConfigFile constructor.
     * @param string $config_filename
     */
    public function __construct(string $config_filename)
    {
        parent::__construct('', '', '', 0, '', '', '');
        if (!is_file($config_filename)) {
            throw new RuntimeException("le fichier $config_filename n'a pas été trouvé.");
        }
        $this->configFilename = (string)realpath($config_filename);
        $this->config = json_decode(file_get_contents($config_filename), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            throw new RuntimeException(
                "Le fichier de configuration externe n'est pas au format json ($errorMsg)."
            );
        }
        if (!isset($this->config['migration_directory'])) {
            throw new RuntimeException(
                "Le dossier 'migration_directory' n'est pas initialisé."
            );
        }
        $migrationDirectory = $this->config['migration_directory'];
        if (!is_dir($migrationDirectory)) {
            throw new RuntimeException(
                "Le dossier 'migration_directory' n'est pas un dossier valide."
            );
        }
        $this->migration_directory = $migrationDirectory;
        if (isset($this->config['config_extern']) && is_file($this->config['config_extern']['file'] ?? '')) {
            try {
                $this->initExternPhp();
            } catch (Throwable $er) {
                throw new RuntimeException(
                    "Le fichier de configuration externe n'est pas exploitable. "
                    . $er->getMessage()
                );
            }
        } elseif (isset($this->config['config_intern'])) {
            $this->initIntern();
        } else {
            throw new RuntimeException("Le fichier de configuration n'est pas valide");
        }
    }

    /**
     * Initialise la config avec un fichier PHP extern
     */
    private function initExternPhp(): void
    {
        $configExtern = $this->config['config_extern'];
        $file = (string)($configExtern['file'] ?? '');

        if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
            throw new RuntimeException(
                "Le fichier de configuration externe doit avoir l'extension .php."
            );
        }

        $configDir = dirname($this->configFilename);
        $resolvedFile = realpath($file);

        if ($resolvedFile === false) {
            throw new RuntimeException("Le fichier externe '{$file}' est introuvable.");
        }

        if (!str_starts_with($resolvedFile, $configDir . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                "Le fichier externe doit être dans le répertoire du projet ({$configDir})."
            );
        }

        try {
            /** @noinspection PhpIncludeInspection */
            $config = require $resolvedFile;
        } catch (Throwable $t) {
            throw new RuntimeException("le fichier externe n'est pas interprétable");
        }
        if (!is_array($config)) {
            throw new RuntimeException("le fichier externe doit être un tableau");
        }
        if (!empty($configExtern['array_path'] ?? '')) {
            $arrayPath = explode('/', $configExtern['array_path']);
            foreach ($arrayPath as $path) {
                if (isset($config[$path])) {
                    $config = $config[$path];
                } else {
                    throw new DomainException(
                        'La structure ne correspond pas au chemin indiqué dans array_path.'
                    );
                }
            }
        }
        $this->provider = $config[$configExtern['provider'] ?? 'provider'] ?? '';
        $this->host = $config[$configExtern['host'] ?? 'host'] ?? '';
        $this->port = $config[$configExtern['port'] ?? 'port'] ?? 0;
        $this->name = $config[$configExtern['name'] ?? 'name'] ?? '';
        $this->user = $config[$configExtern['user'] ?? 'user'] ?? '';
        $this->pass = $config[$configExtern['pass'] ?? 'pass'] ?? '';
    }

    /**
     * Initialise la config avec un le fichier de config
     */
    private function initIntern(): void
    {
        $configIntern = $this->config['config_intern'];
        $this->provider = $configIntern['provider'] ?? '';
        $this->host = $configIntern['host'] ?? '';
        $this->port = $configIntern['port'] ?? 0;
        $this->name = $configIntern['name'] ?? '';
        $this->user = $configIntern['user'] ?? '';
        $this->pass = $configIntern['pass'] ?? '';
    }
}
