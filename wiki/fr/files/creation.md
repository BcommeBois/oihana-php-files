# Création

Cinq fonctions pour créer fichiers et dossiers, avec options de permissions, ownership, et nommage horodaté.

- [`makeFile`](#makefile) — créer ou mettre à jour un fichier avec contenu.
- [`makeDirectory`](#makedirectory) — créer un dossier (récursif par défaut).
- [`makeTimestampedFile`](#maketimestampedfile) — créer un fichier dont le nom contient un timestamp formaté.
- [`makeTimestampedDirectory`](#maketimestampeddirectory) — version dossier.
- [`makeTemporaryDirectory`](#maketemporarydirectory) — créer un sous-dossier dans le temp système.

> 💡 La suppression correspondante est documentée dans [deletion.md](deletion.md). Le workflow temp complet (create + use + delete) est documenté dans [temporary.md](temporary.md).

---

## `makeFile`

```php
makeFile(
    array|string|null $fileOrOptions ,
    ?string           $content = null ,
    array             $options = []
) : string
```

Crée (ou met à jour) un fichier avec son contenu. **Deux signatures équivalentes** :

### Style positionnel

```php
use function oihana\files\makeFile;

makeFile( '/path/to/file.txt' , 'Hello World' ) ;
// Crée le fichier (et le dossier parent si nécessaire)

makeFile( '/log/app.log' , "\nNouvelle ligne" , [ 'append' => true ] ) ;
// Append au lieu d'écraser
```

### Style options-as-array

```php
use oihana\files\enums\MakeFileOption;

makeFile([
    MakeFileOption::FILE        => '/path/to/file.txt' ,
    MakeFileOption::CONTENT     => 'Hello World' ,
    MakeFileOption::APPEND      => true ,
    MakeFileOption::PERMISSIONS => 0600 ,
]) ;
```

### Options disponibles

| Clé (string ou enum)             | Type             | Défaut    | Effet |
|----------------------------------|------------------|-----------|-------|
| `'file'` / `MakeFileOption::FILE` | `string`        | —         | Chemin du fichier (obligatoire en style array). |
| `'content'` / `::CONTENT`         | `string`        | `''`      | Contenu à écrire. |
| `'append'` / `::APPEND`           | `bool`          | `false`   | Append (`FILE_APPEND`) au lieu d'écraser. |
| `'force'` / `::FORCE`             | `bool`          | `true`    | Crée les dossiers parents manquants. |
| `'lock'` / `::LOCK`               | `bool`          | `true`    | Lock exclusif pendant l'écriture (`LOCK_EX`). |
| `'overwrite'` / `::OVERWRITE`     | `bool`          | `false`   | Autorise l'écrasement d'un fichier existant. |
| `'permissions'` / `::PERMISSIONS` | `int` octal     | `0644`    | Mode `chmod` final. |
| `'owner'` / `::OWNER`             | `?string`       | `null`    | Utilisateur (`chown`) — nécessite les droits. |
| `'group'` / `::GROUP`             | `?string`       | `null`    | Groupe (`chgrp`) — nécessite les droits. |

### Comportement face à un fichier existant

| Situation                        | Comportement |
|----------------------------------|---|
| Le fichier n'existe pas          | Création. |
| Le fichier existe + `overwrite: true` | Écrasement. |
| Le fichier existe + `append: true`    | Append. |
| Le fichier existe + ni overwrite ni append | **Pas d'écriture** : retourne le chemin tel quel si le fichier est inscriptible, sinon `FileException`. |

### Exceptions

- **`FileException`** : chemin invalide, échec d'écriture, échec de `chmod`/`chown`/`chgrp`.
- **`DirectoryException`** : échec de création d'un dossier parent (si `force: true`).

### Exemples

```php
// Permissions restreintes, sans lock
makeFile( '/etc/myapp/secret.key' , $key , [
    MakeFileOption::PERMISSIONS => 0600 ,
    MakeFileOption::LOCK        => false ,
]) ;

// Avec ownership (nécessite root)
makeFile( '/var/www/site/upload/file.bin' , $data , [
    MakeFileOption::OWNER => 'www-data' ,
    MakeFileOption::GROUP => 'www-data' ,
]) ;

// Sans créer les dossiers parents (échoue si manquants)
makeFile( '/var/log/app.log' , $line , [
    MakeFileOption::APPEND => true ,
    MakeFileOption::FORCE  => false ,
]) ;
```

---

## `makeDirectory`

```php
makeDirectory(
    null|array|string $pathOrOptions ,
    int               $permissions = 0755 ,
    bool              $recursive   = true ,
    ?string           $owner       = null ,
    ?string           $group       = null
) : ?string
```

Crée un dossier s'il n'existe pas. Retourne le chemin (utile pour chaîner).

**Si le dossier existe déjà** : pas d'erreur — il est juste vérifié inscriptible (sinon `DirectoryException`).

### Style positionnel

```php
use function oihana\files\makeDirectory;

makeDirectory( '/var/log/myapp' ) ;
// → '/var/log/myapp'  (créé en 0755, récursif)

makeDirectory( '/var/log/myapp/debug' , 0700 , true , 'www-data' , 'www-data' ) ;
```

### Style options-as-array

```php
use oihana\files\enums\MakeDirectoryOption;

makeDirectory([
    MakeDirectoryOption::PATH        => '/var/www/mydir' ,
    MakeDirectoryOption::PERMISSIONS => 0775 ,
    MakeDirectoryOption::RECURSIVE   => true ,
    MakeDirectoryOption::OWNER       => 'www-data' ,
    MakeDirectoryOption::GROUP       => 'www-data' ,
]) ;
```

### Options disponibles

| Clé (string ou enum)                | Type      | Défaut  | Effet |
|-------------------------------------|-----------|---------|-------|
| `'path'` / `MakeDirectoryOption::PATH` | `string` | —       | Chemin (obligatoire en style array). |
| `'permissions'` / `::PERMISSIONS`      | `int`    | `0755`  | Mode `mkdir`. |
| `'recursive'` / `::RECURSIVE`          | `bool`   | `true`  | Crée les dossiers parents. |
| `'owner'` / `::OWNER`                  | `?string`| `null`  | `chown`. |
| `'group'` / `::GROUP`                  | `?string`| `null`  | `chgrp`. |

### Chaînage typique

`makeDirectory` retourne le chemin, ce qui permet :

```php
$path = makeDirectory( '/tmp/myapp/cache' ) ;
file_put_contents( $path . '/data.json' , json_encode( $data ) ) ;

// Ou en combinaison avec joinPaths
use function oihana\files\path\joinPaths;
$logs = makeDirectory( joinPaths( $base , 'var' , 'logs' ) ) ;
```

---

## `makeTimestampedFile`

```php
makeTimestampedFile(
    ?string $date      = null ,
     string $basePath  = '' ,
    ?string $extension = null ,
     string $prefix    = '' ,
     string $suffix    = '' ,
    ?string $timezone  = 'Europe/Paris' ,
    ?string $format    = 'Y-m-d\TH:i:s' ,
       bool $mustExist = false
) : ?string
```

Crée un fichier **vide** (via `touch`) dont le nom est composé d'un timestamp formaté + prefix/suffix/extension optionnels.

**Tous les arguments sont nommables** — usage avec arguments nommés fortement recommandé.

### Exemples

```php
use function oihana\files\makeTimestampedFile;

makeTimestampedFile() ;
// → './2026-05-26T15:30:12'

makeTimestampedFile(
    basePath  : '/tmp' ,
    extension : '.log' ,
) ;
// → '/tmp/2026-05-26T15:30:12.log'

makeTimestampedFile(
    date     : '2025-12-01 14:00:00' ,
    basePath : '/backups' ,
    extension: '.tar.gz' ,
    prefix   : 'site-' ,
) ;
// → '/backups/site-2025-12-01T14:00:00.tar.gz'

makeTimestampedFile(
    prefix  : 'backup_' ,
    suffix  : '_final' ,
    timezone: 'UTC' ,
    format  : 'Ymd_His' ,
) ;
// → './backup_20260526_133012_final'
```

### Cas particulier : `$mustExist`

Si `mustExist: true`, la fonction lève `FileException` si le fichier généré **n'existe pas** après `touch`. Utile pour valider qu'on a bien les droits d'écriture sur le `basePath`.

### Format de date

Le paramètre `$format` accepte n'importe quel format `DateTime::format()`. Voir [`getTimestampedFile`](system.md#gettimestampedfile) pour la version qui **ne crée pas le fichier** mais retourne juste le chemin formaté.

---

## `makeTimestampedDirectory`

```php
makeTimestampedDirectory(
    ?string $date     = null ,
     string $basePath = '' ,
     string $prefix   = '' ,
     string $suffix   = '' ,
    ?string $timezone = 'Europe/Paris' ,
    ?string $format   = 'Y-m-d\TH:i:s'
) : ?string
```

Même principe que `makeTimestampedFile`, mais crée un **dossier** (mode `0755`, récursif).

```php
use function oihana\files\makeTimestampedDirectory;

makeTimestampedDirectory(
    basePath: '/backups' ,
    prefix  : 'snapshot_' ,
) ;
// → '/backups/snapshot_2026-05-26T15:30:12'

makeTimestampedDirectory(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/tmp' ,
    prefix  : 'backup_' ,
    suffix  : '_v1' ,
) ;
// → '/tmp/backup_2025-12-01T14:00:00_v1'
```

> ⚠ Pas d'options de permissions / owner / group ici — utiliser `makeDirectory` après si besoin de finer-grain. Sinon, le mode est figé à `0755`.

---

## `makeTemporaryDirectory`

```php
makeTemporaryDirectory(
    string|array|null $path ,
    int               $permission = 0755
) : string
```

Crée (ou retourne, s'il existe déjà) un sous-dossier dans `sys_get_temp_dir()`.

**Le paramètre `$path` :**

- `null` → renvoie `sys_get_temp_dir()` lui-même (sans créer quoi que ce soit).
- `string` → sous-dossier : `'cache'` → `/tmp/cache`.
- `array` → segments joints : `['my', 'app']` → `/tmp/my/app`.

```php
use function oihana\files\makeTemporaryDirectory;

$reports = makeTemporaryDirectory( 'reports' ) ;
// → '/tmp/reports'

$cache = makeTemporaryDirectory( [ 'my' , 'app' , 'cache' ] , 0700 ) ;
// → '/tmp/my/app/cache' (mode 0700)

$tmp = makeTemporaryDirectory( null ) ;
// → '/tmp' (existant, juste retourné)
```

**Lève `DirectoryException`** si la création échoue.

Voir le [workflow temporaire complet](temporary.md).

---

## Voir aussi

- [Suppression](deletion.md) — toutes les fonctions miroirs (`deleteFile`, `deleteDirectory`, `deleteTemporaryDirectory`).
- [Répertoires temporaires](temporary.md) — workflow create/use/delete.
- [Système](system.md) — `getTimestampedFile`, `getTimestampedDirectory` (génèrent le chemin sans créer le fichier).
- [Énumérations](../enums.md) — `MakeFileOption`, `MakeDirectoryOption`.
- [Vue d'ensemble](README.md).
