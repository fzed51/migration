# Changelog

Toutes les évolutions notables de ce projet sont consignées dans ce fichier.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet suit le [versionnage sémantique](https://semver.org/lang/fr/).

## [3.0.0] - 2026-09-25

Version majeure : la CLI passe en sous-commandes et PHP 8.2 devient le minimum. Voir la section « Migration depuis la v2 » du [README](README.md#migration-depuis-la-v2).

### Changements cassants

- La CLI repose sur `symfony/console` (`^7.4 || ^8.0`) à la place de `fzed51/console-options`, et les options deviennent des sous-commandes :

  | v2 | v3 |
  |---|---|
  | `migrate` | `migrate run` |
  | `migrate -i` | `migrate init` |
  | `migrate -n <nom>` | `migrate new <nom>` |
  | `migrate -p <provider>` | `migrate provider <provider>` |
  | `migrate -c <fichier>` / `-config_file <fichier>` | `-c <fichier>` / `--config=<fichier>` |

- `migrate` seul affiche la vue d'ensemble et ne touche plus la base de données.
- En cas d'erreur, le code de retour est différent de 0 et le message est écrit sur stderr. La v2 affichait le message et sortait avec le code 0.
- `migrate new` échoue quand aucun dossier provider n'existe. La v2 affichait seulement un avertissement.
- `migrate run` échoue avec « Intégrité compromise » si un fichier déjà appliqué a été modifié : son checksum SHA1 est maintenant vérifié.
- `config_extern.file` doit être un fichier `.php` situé dans le répertoire du fichier de configuration (ou un sous-dossier).
- Le fichier de configuration est limité à 1 Mo, et ses valeurs doivent être des chaînes ou des entiers (`port`).
- PHP 8.2 minimum (8.1 auparavant).

### Ajouts

- `migrate init` crée aussi le dossier `./db/migration`.
- Aide structurée pour chaque commande (Effet, Préconditions, Idempotence, Sortie, Erreurs fréquentes, Exemples), pensée pour les humains et les agents.
- Description lisible par une machine : `migrate list --format=json` (ou `migrate --help --format=json`) et `migrate help <commande> --format=md`.
- Options `--help`, `--version`, `--quiet`, `--verbose`, `--no-ansi` et `--no-interaction`.
- README réécrit : démarrage rapide, configuration, usage par un agent ou en CI, guide de migration depuis la v2.
- CI GitHub Actions : lint, puis tests sur PHP 8.2 à 8.5 et avec les dépendances les plus récentes.
- Fichier `LICENSE` (MIT, licence déjà déclarée dans `composer.json`) et métadonnées Packagist (mots-clés, extensions PDO suggérées).

### Corrections

- Les commentaires SQL `--` sont retirés avant l'exécution d'un fichier de migration.
- L'historique `migration_story` est lu correctement quel que soit le `PDO::ATTR_CASE` de la connexion (#7).
- `migrate new` conserve les chiffres 2 à 9 dans le nom de la migration.
- Messages de sortie de `provider`, `new` et `run` : chemins sans séparateurs mélangés, message distinct quand le dossier provider existe déjà, faute de frappe corrigée.

### Sécurité

- Le fichier `config_extern` n'est inclus que s'il se trouve dans le répertoire de la configuration et porte l'extension `.php`.
- Le nom du provider est validé avant de construire un chemin.
- Création du fichier de migration et du fichier de configuration sans race condition (ouverture exclusive, écriture atomique).
- `display_errors` n'est activé que si `APP_ENV=development`.
- Détail et suivi dans [SECURITY_REPORT.md](SECURITY_REPORT.md).

## [2.0.0] - 2022-10-03

Dernière version de la v2. Les versions antérieures ne sont pas détaillées ici : voir l'historique git et les tags.

[3.0.0]: https://github.com/fzed51/migration/compare/v2.0.0...v3.0.0
[2.0.0]: https://github.com/fzed51/migration/releases/tag/v2.0.0
