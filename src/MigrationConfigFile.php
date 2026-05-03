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
        if (!is_file($config_filename)) {
            throw new RuntimeException("le fichier $config_filename n'a pas été trouvé.");
        }
        $this->configFilename = (string)realpath($config_filename);
        if (filesize($config_filename) > 1024 * 1024) {
            throw new RuntimeException("Le fichier de configuration est trop volumineux (> 1 Mo).");
        }
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
        if (isset($this->config['config_extern']) && is_file($this->config['config_extern']['file'] ?? '')) {
            try {
                [$provider, $host, $port, $name, $user, $pass] = $this->resolveExternPhp();
            } catch (Throwable $er) {
                throw new RuntimeException(
                    "Le fichier de configuration externe n'est pas exploitable. "
                    . $er->getMessage()
                );
            }
        } elseif (isset($this->config['config_intern'])) {
            [$provider, $host, $port, $name, $user, $pass] = $this->resolveIntern();
        } else {
            throw new RuntimeException("Le fichier de configuration n'est pas valide");
        }
        parent::__construct($migrationDirectory, $provider, $host, $port, $name, $user, $pass);
    }

    /**
     * Résout la config depuis un fichier PHP externe
     * @return array{string, string, int, string, string, string}
     */
    private function resolveExternPhp(): array
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
        return $this->validateTypes(
            $config[$configExtern['provider'] ?? 'provider'] ?? null,
            $config[$configExtern['host'] ?? 'host'] ?? null,
            $config[$configExtern['port'] ?? 'port'] ?? null,
            $config[$configExtern['name'] ?? 'name'] ?? null,
            $config[$configExtern['user'] ?? 'user'] ?? null,
            $config[$configExtern['pass'] ?? 'pass'] ?? null,
        );
    }

    /**
     * Résout la config depuis la section config_intern
     * @return array{string, string, int, string, string, string}
     */
    private function resolveIntern(): array
    {
        $c = $this->config['config_intern'];
        return $this->validateTypes(
            $c['provider'] ?? null,
            $c['host'] ?? null,
            $c['port'] ?? null,
            $c['name'] ?? null,
            $c['user'] ?? null,
            $c['pass'] ?? null,
        );
    }

    /**
     * Valide les types des valeurs de connexion extraites du JSON.
     * Une valeur absente (null) est acceptée et remplacée par le défaut ; une valeur
     * présente avec le mauvais type lève une RuntimeException.
     * @return array{string, string, int, string, string, string}
     */
    private function validateTypes(
        mixed $provider,
        mixed $host,
        mixed $port,
        mixed $name,
        mixed $user,
        mixed $pass,
    ): array {
        foreach (['provider' => $provider, 'host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass] as $key => $val) {
            if ($val !== null && !is_string($val)) {
                throw new RuntimeException("La configuration '$key' doit être une chaîne.");
            }
        }
        if ($port !== null && !is_int($port)) {
            throw new RuntimeException("La configuration 'port' doit être un entier.");
        }
        return [
            (string)($provider ?? ''),
            (string)($host ?? ''),
            (int)($port ?? 0),
            (string)($name ?? ''),
            (string)($user ?? ''),
            (string)($pass ?? ''),
        ];
    }
}
