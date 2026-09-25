<?php

declare(strict_types=1);

namespace Migration\Console;

use Migration\CreateProviderDirectory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande de création du dossier d'un provider
 */
class ProviderCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $this
            ->setName('provider')
            ->setDescription("Crée le dossier de migration d'un provider (ne touche pas la base)")
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Provider : mysql, sqlite, postgres ou postgresql',
                null,
                ['mysql', 'sqlite', 'postgres', 'postgresql']
            )
            ->addConfigOption()
            ->setHelp(<<<'TXT'
<comment>Effet :</comment>
  Crée le dossier <migration_directory>/<name>/ qui contiendra les fichiers de migration
  de ce provider. "postgresql" est un alias de "postgres" (dossier "postgres").

<comment>Préconditions :</comment>
  - Le fichier de configuration existe et est valide.
  - Le dossier migration_directory existe.
  La base de données n'est pas contactée.

<comment>Idempotence :</comment>
  Oui : si le dossier existe déjà, la commande réussit sans rien modifier.

<comment>Sortie :</comment>
  "Le dossier <chemin> a été créé avec succes."

<comment>Erreurs fréquentes :</comment>
  - "Le provider 'x' n'est pas connu" : utiliser mysql, sqlite, postgres ou postgresql.
  - "Le dossier 'migration_directory' n'est pas un dossier valide" : créer ce dossier.
  - "Impossible de trouver le fichier de configuration" : lancer d'abord <info>migrate init</info>.

<comment>Exemples :</comment>
  <info>%command.full_name% sqlite</info>
  <info>%command.full_name% postgres --config=config/migration.json</info>
TXT . self::HELP_CONVENTIONS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providerDirectory = new CreateProviderDirectory($this->loadConfig($input));
        $providerDirectory->setProvider((string)$input->getArgument('name'));
        $providerDirectory->run();
        return self::SUCCESS;
    }
}
