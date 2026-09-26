<?php

namespace Migration;

use Helper\PDOFactory;
use PDO;

/**
 * Class Migration
 * @package Migration
 */
class Migration extends MigrationCore
{
    /**
     * Attributs PDO de la connexion : noms de colonnes en majuscules
     * (fzed51/pdo-helper 3 utilise CASE_LOWER par défaut)
     */
    private const PDO_ATTRIBUTES = [PDO::ATTR_CASE => PDO::CASE_UPPER];

    /**
     * propriété contenant la config
     * @var MigrationConfig
     */
    protected $config;

    /**
     * Migration constructor.
     * @param MigrationConfig $config
     */
    public function __construct(MigrationConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Exécute le process de migration
     * @throws \Exception
     */
    public function run(): void
    {
        # connexion
        $this->connexion();
        # setup & migrate
        parent::run();
    }

    /**
     * Initialise la connexion
     * @throws \Exception
     */
    private function connexion(): void
    {
        $this->setMigrationDirectory($this->config->migration_directory);
        switch ($this->config->provider) {
            case 'mysql':
                $this->setProvider($this->config->provider);
                $this->setPdo(PDOFactory::mysql(
                    $this->config->host,
                    $this->config->name,
                    $this->config->user,
                    $this->config->pass,
                    attributes: self::PDO_ATTRIBUTES
                ));
                break;
            case 'sqlite':
                $this->setProvider($this->config->provider);
                $this->setPdo(PDOFactory::sqlite($this->config->name, self::PDO_ATTRIBUTES));
                break;
            case 'postgres':
            case 'postgresql':
                $this->setProvider('postgres');
                $this->setPdo(PDOFactory::pgsql(
                    $this->config->name,
                    $this->config->host,
                    $this->config->user,
                    $this->config->pass,
                    $this->config->port,
                    self::PDO_ATTRIBUTES
                ));
                break;
            default:
                throw new \RuntimeException("Le provider {$this->config->provider} est inconnue!");
        }
    }
}
