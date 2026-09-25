<?php

declare(strict_types=1);

namespace Migration\Console;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Application console "migrate"
 */
class Application extends BaseApplication
{
    public const NAME = 'migrate';
    private const PACKAGE = 'fzed51/migration';

    public function __construct()
    {
        parent::__construct(self::NAME, self::detectVersion());
        $this->addCommand(new InitCommand());
        $this->addCommand(new ProviderCommand());
        $this->addCommand(new NewCommand());
        $this->addCommand(new RunCommand());
        // sans commande, on affiche la vue d'ensemble : lancer "migrate" seul ne touche jamais la base
        $this->setDefaultCommand('list');
    }

    /**
     * version du package installé
     */
    private static function detectVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
            return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev';
        }
        return 'dev';
    }

    /**
     * vue d'ensemble affichée par "migrate", "migrate list" et "migrate --help"
     */
    public function getHelp(): string
    {
        return $this->getLongVersion() . <<<'TXT'


Outil de migration de base de données (MySQL, SQLite, PostgreSQL) à partir de fichiers SQL.

<comment>Workflow :</comment>
  1. <info>migrate init</info>              crée ./migration-config.json et ./db/migration/
  2. éditer la configuration   connexion (config_intern)
  3. <info>migrate provider sqlite</info>   crée <migration_directory>/sqlite/
  4. <info>migrate new create_user</info>   crée YYYYMMDD-NN-create_user.sql
  5. écrire le SQL             requêtes séparées par une ligne "---"
  6. <info>migrate run</info>               applique les migrations en attente

<comment>Effets de bord :</comment>
  - list, help, init, provider, new : ne contactent pas la base.
  - run : SEULE commande qui MODIFIE LA BASE (sans transaction ni rollback).

<comment>Conventions :</comment>
  - Aucune question interactive ; code de retour 0 = succès, différent de 0 = échec.
  - Progression sur stdout, erreurs sur stderr.
  - Option commune : -c, --config=FILE (défaut ./migration-config.json).
  - Détail d'une commande : <info>migrate help <commande></info>
  - Description lisible par une machine : <info>migrate list --format=json</info>
    ou <info>migrate help <commande> --format=md</info>
TXT;
    }

    /**
     * "migrate --help" sans commande affiche la vue d'ensemble plutôt que l'aide de "list",
     * en respectant --format et --raw (ex. "migrate --help --format=json")
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $wantsHelp = $input->hasParameterOption(['--help', '-h'], true);
        $format = $input->getParameterOption('--format', false, true);
        $hasFormat = is_string($format) && $format !== '';
        $firstArgument = $input->getFirstArgument();
        // "--format json" : la valeur de l'option n'est pas un nom de commande
        $noCommand = $firstArgument === null || ($hasFormat && $firstArgument === $format);
        if ($noCommand && ($wantsHelp || $hasFormat)) {
            // conserve les options de rendu de "list" (ex. --format=json, --raw)
            $listInput = ['command' => 'list'];
            if ($hasFormat) {
                $listInput['--format'] = $format;
            }
            if ($input->hasParameterOption('--raw', true)) {
                $listInput['--raw'] = true;
            }
            $input = new ArrayInput($listInput);
        }
        return parent::doRun($input, $output);
    }
}
