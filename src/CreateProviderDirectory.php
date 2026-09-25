<?php

namespace Migration;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

class CreateProviderDirectory
{
    private const AUTORIZED_PROVIDER = [
        "postgres",
        "postgresql",
        "mysql",
        "sqlite",
    ];
    private MigrationConfig $config;
    private ?string $provider = null;

    /**
     * @param \Migration\MigrationConfig $config
     */
    public function __construct(MigrationConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $provider
     * @param-stan  "postgres"|"postgresql"|"mysql"|"sqlite" $provider
     * @return void
     */
    public function setProvider(string $provider): void
    {
        $provider = strtolower($provider);
        if (!in_array($provider, self::AUTORIZED_PROVIDER)) {
            $providers = implode(", ", self::AUTORIZED_PROVIDER);
            throw new InvalidArgumentException("Le provider '$provider' n'est pas connu. Utiliser : $providers.");
        }
        $this->provider = $provider;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        if ($this->provider === null) {
            throw new LogicException("le nom du provider n'a pas été donné");
        }
        $migrationDirectory = $this->config->migration_directory;
        if (!is_dir($migrationDirectory)) {
            throw new LogicException(
                "impossible de créer le dossier du provider, le dossier de migration ($migrationDirectory) n'existe pas"
            );
        }
        switch ($this->provider) {
            case 'mysql':
            case 'sqlite':
                $this->createDirectory($migrationDirectory, $this->provider);
                break;
            case 'postgres':
            case 'postgresql':
                $this->createDirectory($migrationDirectory, 'postgres');
                break;
            default:
                throw new RuntimeException("Le provider {$this->provider} est inconnue!");
        }
    }

    /**
     * @param string $migrationDirectory
     * @param string|null $provider
     * @return void
     */
    private function createDirectory(string $migrationDirectory, ?string $provider)
    {
        if ($provider !== null) {
            // migration_directory se termine déjà par un séparateur (cf. MigrationConfig)
            $concurrentDirectory = rtrim($migrationDirectory, '/\\') . '/' . $provider;
            if (is_dir($concurrentDirectory)) {
                echo "Le dossier $concurrentDirectory existe déjà." . PHP_EOL;
                return;
            }
            if (!mkdir($concurrentDirectory) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException(
                    "Impossible de créer le dossier du provider '$provider' dans '$migrationDirectory'."
                );
            }
            echo "Le dossier $concurrentDirectory a été créé avec succès." . PHP_EOL;
        }
    }
}
