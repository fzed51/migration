# migration

[![CI](https://github.com/fzed51/migration/actions/workflows/ci.yml/badge.svg)](https://github.com/fzed51/migration/actions/workflows/ci.yml)
[![Version](https://img.shields.io/packagist/v/fzed51/migration)](https://packagist.org/packages/fzed51/migration)
[![PHP](https://img.shields.io/packagist/dependency-v/fzed51/migration/php)](composer.json)
[![Licence](https://img.shields.io/packagist/l/fzed51/migration)](LICENSE)

`migration` est un outil en ligne de commande qui applique des migrations de structure de base de données écrites en **SQL pur**. Il n'y a pas de DSL à apprendre : vous décrivez la connexion dans un fichier JSON, écrivez vos scripts SQL, puis lancez `migrate run`.

Bases supportées : **MySQL**, **SQLite**, **PostgreSQL**.

Cette documentation s'adresse aux humains comme aux **agents** (IA, scripts, CI). Les effets de bord, les préconditions et les codes de retour de chaque commande y sont décrits précisément.

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Démarrage rapide](#démarrage-rapide)
- [Commandes](#commandes)
- [Configuration](#configuration)
- [Écrire une migration](#écrire-une-migration)
- [Sorties et codes de retour](#sorties-et-codes-de-retour)
- [Utilisation par un agent ou en CI](#utilisation-par-un-agent-ou-en-ci)
- [Migration depuis la v2](#migration-depuis-la-v2)
- [Développement](#développement)
- [Changelog, sécurité et licence](#changelog-sécurité-et-licence)

## Prérequis

- PHP 8.2 ou supérieur, avec `ext-json`, `ext-mbstring` et `ext-pdo`
- le driver PDO de votre base : `pdo_mysql`, `pdo_sqlite` ou `pdo_pgsql`

## Installation

```shell
composer require fzed51/migration
```

Le binaire est installé dans `./vendor/bin/migrate` (sous Windows : `vendor\bin\migrate.bat`). Les exemples de cette documentation l'appellent simplement `migrate`.

## Démarrage rapide

Exemple complet avec SQLite :

```shell
# 1. créer le fichier de configuration ./migration-config.json et le dossier ./db/migration
./vendor/bin/migrate init

# 2. éditer ./migration-config.json (voir « Configuration »), par exemple :
#    "config_intern": { "provider": "sqlite", "name": "./db/data.sqlite" }

# 3. pour SQLite uniquement : créer le fichier de base
touch db/data.sqlite

# 4. créer le dossier du provider : db/migration/sqlite/
./vendor/bin/migrate provider sqlite

# 5. créer un fichier de migration vide : db/migration/sqlite/YYYYMMDD-01-create_user.sql
./vendor/bin/migrate new create_user

# 6. écrire le SQL dans ce fichier, puis appliquer les migrations en attente
./vendor/bin/migrate run
```

Lancé sans argument, `migrate` affiche une vue d'ensemble et la liste des commandes, **sans rien modifier**.

## Commandes

| Commande | Rôle | Modifie la base ? | Idempotente ? |
|---|---|---|---|
| `migrate` / `migrate list` | Vue d'ensemble et liste des commandes | non | oui |
| `migrate help <commande>` | Aide détaillée d'une commande | non | oui |
| `migrate init` | Crée le fichier de configuration | non | **non** : échoue si le fichier existe |
| `migrate provider <name>` | Crée le dossier d'un provider | non | oui |
| `migrate new <name>` | Crée un fichier de migration vide par provider | non | **non** : crée un nouveau fichier à chaque appel |
| `migrate run` | Applique les migrations en attente | **oui** | oui : les fichiers déjà appliqués sont ignorés |

Option commune à `init`, `provider`, `new` et `run` :

| Option | Défaut | Description |
|---|---|---|
| `-c`, `--config=FILE` | `./migration-config.json` | Chemin du fichier de configuration, relatif au répertoire courant |

Options globales fournies par Symfony Console : `-h|--help`, `-V|--version`, `-q|--quiet`, `--silent`, `-v|-vv|-vvv`, `--ansi|--no-ansi`, `-n|--no-interaction`.

### `migrate init`

- **Effet** : écrit un modèle de configuration au chemin `--config`, avec `migration_directory: ./db/migration` et les sections `config_intern` et `config_extern` à compléter.
- **Crée** aussi le dossier `./db/migration`, relatif au répertoire courant, s'il n'existe pas.
- **Échoue** si le fichier existe déjà : il n'est jamais écrasé, et le dossier n'est pas créé.
- Ne contacte pas la base.

### `migrate provider <name>`

- **Arguments** : `name` vaut `mysql`, `sqlite`, `postgres` ou `postgresql`. `postgresql` est un alias qui crée le dossier `postgres`.
- **Effet** : crée `<migration_directory>/<name>/`.
- **Préconditions** : la configuration est valide et `migration_directory` existe.
- Idempotente : réussit aussi si le dossier existe déjà.

### `migrate new <name>`

- **Effet** : crée un fichier vide `YYYYMMDD-NN-<name>.sql` dans **chaque** dossier provider existant.
  - `YYYYMMDD` est la date du jour.
  - `NN` est le numéro d'ordre du jour : 01, 02…
  - `<name>` est normalisé : minuscules, accents retirés, et chaque caractère non alphanumérique remplacé par `_`. Par exemple, `"Création User"` devient `creation_user`.
- **Échoue** (code de retour différent de 0) s'il n'existe aucun dossier provider : lancez d'abord `migrate provider <name>`.

### `migrate run`

La base est **modifiée**. La commande :

1. se connecte à la base décrite dans la configuration ;
2. crée la table d'historique `migration_story` si elle n'existe pas ;
3. exécute, par ordre alphabétique, chaque fichier `<migration_directory>/<provider>/YYYYMMDD-NN-*.sql` absent de l'historique, puis l'enregistre avec son checksum SHA1.

Points d'attention :

- **Pas de transaction** : si une requête échoue, les requêtes déjà exécutées du même fichier restent appliquées, et le fichier n'est pas enregistré.
- **Pas de rollback** : pour annuler une migration, écrivez-en une nouvelle.
- **Ne modifiez jamais un fichier déjà appliqué** : son checksum ne correspondrait plus et `run` échouerait avec le message « Intégrité compromise ».
- Avec SQLite, le fichier de base doit exister avant le premier `run`.

## Configuration

Le fichier de configuration est un JSON (1 Mo maximum) :

```json
{
    "migration_directory": "./db/migration",
    "config_intern": {
        "provider": "sqlite",
        "host": "",
        "port": 0,
        "name": "./db/data.sqlite",
        "user": "",
        "pass": ""
    }
}
```

| Clé | Description |
|---|---|
| `migration_directory` | Dossier des migrations. Il doit exister et contient un sous-dossier par provider. |
| `config_intern.provider` | `mysql`, `sqlite`, `postgres` ou `postgresql` |
| `config_intern.host` | Hôte du serveur (MySQL, PostgreSQL) |
| `config_intern.port` | Port, en **entier** (PostgreSQL) |
| `config_intern.name` | Nom de la base, ou chemin du fichier pour SQLite |
| `config_intern.user` / `pass` | Identifiants (MySQL, PostgreSQL) |

Exemples de `config_intern` :

```jsonc
// MySQL
{ "provider": "mysql", "host": "localhost", "name": "app", "user": "app", "pass": "secret" }
// PostgreSQL
{ "provider": "postgres", "host": "localhost", "port": 5432, "name": "app", "user": "app", "pass": "secret" }
```

### Réutiliser la configuration PHP d'un projet (`config_extern`)

Au lieu de dupliquer les identifiants, `config_extern` lit un fichier PHP qui **retourne un tableau**. Si `config_extern.file` pointe vers un fichier existant, cette section est utilisée à la place de `config_intern`.

```json
{
    "migration_directory": "./db/migration",
    "config_extern": {
        "file": "./config/settings.php",
        "array_path": "settings/db",
        "provider": "driver",
        "host": "host",
        "port": "port",
        "name": "database",
        "user": "username",
        "pass": "password"
    }
}
```

- `file` : un fichier `.php` situé **dans le répertoire du fichier de configuration** (ou un sous-dossier).
- `array_path` : le chemin, séparé par `/`, vers le sous-tableau qui contient la connexion. Laissez-le vide si c'est la racine.
- `provider`, `host`, `port`, `name`, `user`, `pass` : le **nom de la clé** à lire dans ce sous-tableau.

## Écrire une migration

- **Emplacement** : `<migration_directory>/<provider>/`, par exemple `db/migration/sqlite/`.
- **Nom** : `YYYYMMDD-NN-description.sql`. Seuls les fichiers qui suivent ce motif sont pris en compte, dans l'ordre alphabétique. Utilisez `migrate new` pour les créer.
- **Séparateur** : une ligne commençant par `---` sépare deux requêtes.
- **Commentaires** : ce qui suit `--` sur une ligne est ignoré. Le `;` final est optionnel.

```sql
CREATE TABLE user (
    id INTEGER PRIMARY KEY
);
---
-- une seconde requête
CREATE TABLE entity (
    id INTEGER PRIMARY KEY
);
```

L'historique est conservé dans la table `migration_story` (colonnes `file`, `content`, `checksum`). Une migration appliquée ne doit plus être modifiée. Pour corriger, créez-en une nouvelle.

## Sorties et codes de retour

| Canal | Contenu |
|---|---|
| stdout | Progression : `migration : setup migration`, `migration : sqlite/20260925-01-create_user.sql`, fichiers ou dossiers créés… |
| stderr | Message d'erreur (utilisez `-v` pour avoir la trace) |
| code de retour | `0` = succès, différent de `0` = échec |

Aucune commande ne pose de question interactive.

Par défaut, les erreurs PHP (warnings, notices) ne sont pas affichées mais envoyées au journal d'erreurs de PHP. Pour les afficher pendant un diagnostic, lancez la commande avec `APP_ENV=development` :

```shell
APP_ENV=development ./vendor/bin/migrate run -v
```

## Utilisation par un agent ou en CI

Règles à suivre pour piloter l'outil automatiquement :

1. **Découvrir sans risque** : `migrate` et `migrate list` ne modifient rien. Une description lisible par une machine est disponible :
   ```shell
   ./vendor/bin/migrate list --format=json    # commandes, arguments, options et aides
                                              # (équivalent : migrate --help --format=json)
   ./vendor/bin/migrate help run --format=md  # aide d'une commande en Markdown
   ```
2. **Lancer en mode non interactif et sans couleurs** : `--no-interaction --no-ansi`.
3. **Toujours tester le code de retour** : toute commande qui n'a pas pu faire son travail retourne un code différent de 0.
4. **`run` est la seule commande qui modifie la base.** Il n'y a ni transaction ni rollback.
5. **Ne jamais éditer un fichier déjà appliqué.** Pour corriger, créez une nouvelle migration avec `migrate new`.

Check-list avant `migrate run` :

- [ ] le fichier de configuration pointe vers la **bonne base** (pas la production par erreur) ;
- [ ] les nouveaux fichiers sont dans `<migration_directory>/<provider>/` et respectent le motif `YYYYMMDD-NN-*.sql` ;
- [ ] chaque requête est séparée par une ligne `---` ;
- [ ] aucun fichier déjà appliqué n'a été modifié (`git diff` sur le dossier de migration).

## Migration depuis la v2

La v3 remplace les options par des sous-commandes (CLI basée sur `symfony/console`) et demande PHP 8.2 ou supérieur. La liste complète des changements est dans le [CHANGELOG](CHANGELOG.md).

Étapes de mise à jour :

1. Passez à PHP 8.2 ou supérieur si besoin.
2. Mettez à jour la dépendance : `composer require fzed51/migration:^3.0`.
3. Remplacez les appels dans vos scripts, votre CI et votre documentation à l'aide du tableau ci-dessous. Attention : **`migrate` seul n'applique plus les migrations**, utilisez `migrate run`.
4. Si vous utilisez `config_extern`, vérifiez que le fichier PHP est dans le répertoire du fichier de configuration (ou un sous-dossier).
5. Lancez `migrate run` sur une base de test : si un fichier déjà appliqué a été modifié depuis, la commande s'arrête sur « Intégrité compromise ».

| v2 | v3 |
|---|---|
| `migrate` | `migrate run` (`migrate` seul affiche maintenant l'aide) |
| `migrate -i` | `migrate init` |
| `migrate -n <nom>` | `migrate new <nom>` |
| `migrate -p <provider>` | `migrate provider <provider>` |
| `migrate -c <fichier>` / `-config_file <fichier>` | `-c <fichier>` / `--config=<fichier>` |

Autres changements de comportement :

- en cas d'erreur, le code de retour est différent de 0, et le message est écrit sur stderr ;
- `init` crée aussi le dossier `./db/migration` ;
- `new` échoue quand aucun dossier provider n'existe (la v2 affichait seulement un avertissement) ;
- `run` vérifie le checksum des fichiers déjà appliqués ;
- `config_extern.file` doit être un fichier `.php` placé sous le répertoire du fichier de configuration ;
- `--help` et `--version` sont disponibles.

## Développement

```shell
composer lint   # composer validate + phpcs (PSR-2) + phpstan (niveau 6)
composer test   # phpunit
```

La CI GitHub Actions lance `composer lint`, puis `composer test` sur chaque version de PHP supportée (8.2 à 8.5), ainsi qu'avec les dépendances les plus récentes autorisées par `composer.json`.

## Changelog, sécurité et licence

- Historique des versions : [CHANGELOG.md](CHANGELOG.md).
- Audit de sécurité et correctifs : [SECURITY_REPORT.md](SECURITY_REPORT.md).- Licence : [MIT](LICENSE).
