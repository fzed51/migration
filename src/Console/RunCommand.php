<?php

declare(strict_types=1);

namespace Migration\Console;

use Migration\Migration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande d'application des migrations en attente
 */
class RunCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Applique les migrations en attente (MODIFIE LA BASE)')
            ->addConfigOption()
            ->setHelp(<<<'TXT'
<comment>Effet (MODIFIE LA BASE DE DONNÉES) :</comment>
  1. Se connecte à la base décrite dans la configuration.
  2. Crée la table d'historique <info>migration_story</info> si elle n'existe pas
     (affiche "migration : setup migration").
  3. Exécute, par ordre alphabétique, chaque fichier
     <migration_directory>/<provider>/YYYYMMDD-NN-*.sql absent de l'historique,
     puis l'enregistre dans migration_story avec son checksum SHA1.

<comment>Préconditions :</comment>
  - Le fichier de configuration existe et est valide.
  - La base est joignable. Pour SQLite, le fichier de base doit déjà exister.
  - Le dossier <migration_directory>/<provider> existe.

<comment>Idempotence :</comment>
  Oui : un fichier déjà présent dans l'historique n'est jamais rejoué. Sans migration
  en attente, la commande ne modifie rien.

<comment>Sortie :</comment>
  Une ligne "migration : <provider>/<fichier>" par fichier appliqué.

<comment>Points d'attention :</comment>
  - Pas de transaction : si une requête échoue, les requêtes précédentes du même fichier
    restent appliquées et le fichier n'est PAS enregistré. Corriger la base à la main
    ou adapter le fichier avant de relancer.
  - Pas de rollback : pour annuler une migration, en écrire une nouvelle.
  - Ne jamais modifier un fichier déjà appliqué : son checksum ne correspondrait plus
    et la commande échouerait ("Intégrité compromise"). Créer un nouveau fichier avec
    <info>migrate new</info>.

<comment>Erreurs fréquentes :</comment>
  - "Impossible de trouver le fichier de configuration" : lancer <info>migrate init</info>.
  - "Impossible d'executer la requete <fichier>(<lignes>)" : erreur SQL, voir le détail.
  - "Intégrité compromise" : un fichier appliqué a été modifié, restaurer son contenu.

<comment>Exemples :</comment>
  <info>%command.full_name%</info>
  <info>%command.full_name% --config=config/migration.json --no-interaction --no-ansi</info>
TXT . self::HELP_CONVENTIONS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new Migration($this->loadConfig($input)))->run();
        return self::SUCCESS;
    }
}
