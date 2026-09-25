<?php

declare(strict_types=1);

namespace Migration\Console;

use Migration\CreateMigration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande de création d'un fichier de migration vide
 */
class NewCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Crée un fichier de migration SQL vide par provider (ne touche pas la base)')
            ->addArgument('name', InputArgument::REQUIRED, 'Nom de la migration, ex. "create_user"')
            ->addConfigOption()
            ->setHelp(<<<'TXT'
<comment>Effet :</comment>
  Crée un fichier vide <info>YYYYMMDD-NN-<name>.sql</info> dans CHAQUE dossier provider existant
  sous migration_directory (mysql, sqlite, oci, postgres).
    - YYYYMMDD : date du jour ;
    - NN       : numéro d'ordre du jour (01, 02, ...), incrémenté automatiquement ;
    - <name>   : normalisé (minuscules, accents retirés, tout caractère non alphanumérique
                 remplacé par "_"). "Création User" devient "creation_user".
  Écrire ensuite le SQL dans le(s) fichier(s) créé(s), puis lancer <info>migrate run</info>.

<comment>Préconditions :</comment>
  - Le fichier de configuration existe et est valide.
  - Au moins un dossier provider existe (voir <info>migrate provider</info>).
  La base de données n'est pas contactée.

<comment>Idempotence :</comment>
  Non : chaque appel crée un nouveau fichier avec le numéro NN suivant.

<comment>Sortie :</comment>
  Une ligne par fichier créé : " Création du fichier '<fichier>' pour <provider>'".

<comment>Erreurs fréquentes :</comment>
  - "Aucun dossier provider n'existe" : aucun fichier créé, code de retour différent de 0.
    Lancer <info>migrate provider <provider></info> puis relancer la commande.
  - "Impossible de trouver le fichier de configuration" : lancer d'abord <info>migrate init</info>.

<comment>Format d'un fichier de migration :</comment>
  - Les requêtes sont séparées par une ligne commençant par "---".
  - Les commentaires "--" sont ignorés, le ";" final est optionnel.

<comment>Exemples :</comment>
  <info>%command.full_name% create_user</info>
  <info>%command.full_name% "ajout index email" --config=config/migration.json</info>
TXT . self::HELP_CONVENTIONS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newMigration = new CreateMigration($this->loadConfig($input));
        $newMigration->setNewMigrationName((string)$input->getArgument('name'));
        $newMigration->run();
        return self::SUCCESS;
    }
}
