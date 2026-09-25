<?php

declare(strict_types=1);

namespace Migration\Console;

use Migration\MigrationInit;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande de création du fichier de configuration
 */
class InitCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Crée le fichier de configuration JSON (ne touche pas la base)')
            ->addConfigOption()
            ->setHelp(<<<'TXT'
<comment>Effet :</comment>
  Écrit un modèle de fichier de configuration JSON au chemin donné par <info>--config</info>
  (défaut : ./migration-config.json). Le modèle contient :
    - migration_directory : "./db/migration"
    - config_intern : connexion décrite directement dans le JSON
    - config_extern : connexion lue depuis un fichier PHP du projet
  Crée aussi le dossier ./db/migration (relatif au répertoire courant) s'il n'existe pas.

<comment>Préconditions :</comment>
  Aucune. La base de données n'est pas contactée.

<comment>Idempotence :</comment>
  Non : la commande échoue si le fichier existe déjà (il n'est jamais écrasé, et le
  dossier de migration n'est alors pas créé).

<comment>Sortie :</comment>
  Deux lignes : le chemin du fichier de configuration et celui du dossier de migration.

<comment>Erreurs fréquentes :</comment>
  - "ce fichier existe déjà" : la configuration est déjà initialisée, éditez-la.
  - "Impossible de créer le dossier de migration" : droits d'écriture insuffisants.

<comment>Étapes suivantes :</comment>
  1. Renseigner config_intern (provider, name, ...) dans le fichier.
  2. <info>migrate provider <provider></info>

<comment>Exemples :</comment>
  <info>%command.full_name%</info>
  <info>%command.full_name% --config=config/migration.json</info>
TXT . self::HELP_CONVENTIONS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->getConfigPath($input);
        (new MigrationInit($configPath))->run();
        $output->writeln("Fichier de configuration '$configPath' créé.");
        $output->writeln("Dossier de migration '" . MigrationInit::MIGRATION_DIRECTORY . "' prêt.");
        return self::SUCCESS;
    }
}
