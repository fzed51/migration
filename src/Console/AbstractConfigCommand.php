<?php

declare(strict_types=1);

namespace Migration\Console;

use Migration\MigrationConfigFile;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base des commandes qui s'appuient sur le fichier de configuration
 */
abstract class AbstractConfigCommand extends Command
{
    /** chemin par défaut du fichier de configuration */
    public const DEFAULT_CONFIG = './migration-config.json';

    /**
     * section d'aide commune à toutes les commandes (sortie et codes de retour)
     */
    protected const HELP_CONVENTIONS = <<<'TXT'


<comment>Conventions :</comment>
  - Aucune question interactive : la commande peut être lancée par un script ou un agent.
  - La progression est écrite sur stdout, les erreurs sur stderr.
  - Code de retour 0 = succès, différent de 0 = échec (le message d'erreur explique la cause).
  - <info>--config</info> est résolu depuis le répertoire courant.
TXT;

    /**
     * déclare l'option --config commune
     */
    protected function addConfigOption(): static
    {
        return $this->addOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Chemin du fichier de configuration JSON',
            self::DEFAULT_CONFIG
        );
    }

    /**
     * retourne le chemin du fichier de configuration donné en option
     */
    protected function getConfigPath(InputInterface $input): string
    {
        $configPath = $input->getOption('config');
        if (!is_string($configPath) || $configPath === '') {
            throw new RuntimeException("L'option --config doit contenir un chemin de fichier.");
        }
        return $configPath;
    }

    /**
     * charge le fichier de configuration donné en option
     */
    protected function loadConfig(InputInterface $input): MigrationConfigFile
    {
        $configPath = $this->getConfigPath($input);
        $configFilename = realpath($configPath);
        if ($configFilename === false) {
            throw new RuntimeException(
                'Impossible de trouver le fichier de configuration ' . $configPath
                . ". Créez-le avec la commande 'init'."
            );
        }
        return new MigrationConfigFile($configFilename);
    }
}
