<?php
declare(strict_types=1);
/**
 * User: Fabien Sanchez
 * Date: 28/01/2019
 * Time: 10:00
 */

namespace Migration;

/**
 * Class CreateMigration
 * @package Migration
 */
class CreateMigration
{
    /**
     * liste des providers
     * @var string[]
     */
    private static $providers = [
        'mysql',
        'sqlite',
        'oci',
        'postgres'
    ];
    /**
     * config
     * @var MigrationConfig
     */
    private $config;
    /**
     * new_migration
     * @var string
     */
    private $new_migration;

    /**
     * CreateMigration constructor.
     * @param MigrationConfig $config
     */
    public function __construct(MigrationConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $providers = $this->getProviders();
        foreach ($providers as $provider => $path) {
            $filename = $this->createFile($path);
            echo " Création du fichier '$filename' pour $provider'" . PHP_EOL;
        }
    }

    /**
     * liste les providers et leur chemin de migration
     * @return array<string,string>
     */
    private function getProviders(): array
    {
        $list = [];
        $MigrationPath = realpath($this->config->migration_directory);
        foreach (self::$providers as $provider) {
            $providerPath = $MigrationPath . DIRECTORY_SEPARATOR . $provider;
            if (is_dir($providerPath)) {
                $list[$provider] = $providerPath;
            }
        }
        if (count($list) === 0) {
            echo " Attention aucun répertoire n'existe pour le provider "
                . $this->config->provider.
                " dans le repertoire "
                .$this->config->migration_directory;
        }
        return $list;
    }

    /**
     * @param string $path
     * @return string
     * @throws \Exception
     */
    private function createFile(string $path): string
    {
        $date = (new \DateTime())->format('Ymd');
        $index = 0;
        $notFound = false;
        do {
            $index++;
            $pattern = $path . DIRECTORY_SEPARATOR
                . $date . '-' . substr('00' . $index, -2) . '-*.sql';
            $notFound = (0 === count(glob($pattern)));
        } while (!$notFound);
        $filename = $date . '-' . substr('00' . $index, -2) . '-'
            . $this->new_migration . '.sql';
        touch($path . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    /**
     * @param string $new_migration
     * @return CreateMigration
     */
    public function setNewMigrationName(string $new_migration): CreateMigration
    {
        $this->new_migration = $this->cleanName($new_migration);
        return $this;
    }

    /**
     * netoie une chaine de caractère
     * @param string $str
     * @return string
     */
    private function cleanName(string $str): string
    {
        $charset = 'utf-8';
        $str = mb_strtolower($str);
        $str = htmlentities($str, ENT_NOQUOTES, $charset);
        $str = preg_replace(
            '/&([a-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);/',
            '\1',
            $str
        );
        $str = preg_replace('/&([a-z]{2})(?:lig);/', '\1', $str);
        $str = preg_replace('/&[^;]+;/', ' ', $str);
        $str = preg_replace('/(\s+)|([^a-z0-1]+)/', '_', $str);
        $str = trim($str, '_');
        return $str;
    }
}
