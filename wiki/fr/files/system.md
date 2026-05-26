# Système

Quatorze fonctions utilitaires pour interagir avec le système d'exploitation, manipuler les chemins, et extraire des informations sur les fichiers.

## OS détection

- [`isLinux`](#islinux) — vrai si l'OS est Linux.
- [`isMac`](#ismac) — vrai si l'OS est macOS (Darwin).
- [`isWindows`](#iswindows) — vrai si l'OS est Windows.
- [`isOtherOS`](#isotheros) — vrai sinon (BSD, Solaris, etc.).

## Dossiers système

- [`getHomeDirectory`](#gethomedirectory) — répertoire `~` de l'utilisateur courant.
- [`getRoot`](#getroot) — partie racine d'un chemin.
- [`getDirectory`](#getdirectory) — normalise et valide un chemin de dossier.
- [`getSchemeAndHierarchy`](#getschemeandhierarchy) — sépare scheme et hiérarchie d'un chemin/URI.

## Métadonnées de fichier

- [`getOwnershipInfos`](#getownershipinfos) — UID/GID + noms (owner/group).
- [`getBaseFileName`](#getbasefilename) — nom de fichier **sans extension** (gère multi-part).
- [`getFileExtension`](#getfileextension) — extension (gère multi-part comme `.tar.gz`).

## Chemins horodatés (générateurs purs)

- [`getTimestampedFile`](#gettimestampedfile) — chemin de fichier avec timestamp, **sans le créer**.
- [`getTimestampedDirectory`](#gettimestampeddirectory) — idem pour un dossier.

> 💡 Les versions qui **créent** réellement le fichier/dossier sont `makeTimestampedFile` / `makeTimestampedDirectory` dans [creation.md](creation.md).

---

## `isLinux`

```php
isLinux() : bool
```

Vrai si `PHP_OS` commence par `LINUX`. Résultat **mémoïsé** au premier appel (`static $isLinux = null`).

```php
use function oihana\files\isLinux;

if ( isLinux() ) {
    // Code spécifique Linux
}
```

---

## `isMac`

```php
isMac() : bool
```

Vrai si `PHP_OS` commence par `DARWIN`. Mémoïsé.

> ⚠ Ne confondre pas : sur macOS, `PHP_OS` est `Darwin` (le kernel), pas `Mac` ou `macOS`.

---

## `isWindows`

```php
isWindows() : bool
```

Vrai si `PHP_OS` commence par `WIN`. Mémoïsé.

---

## `isOtherOS`

```php
isOtherOS() : bool
```

Vrai si **aucun** des trois précédents — couvre BSD, Solaris, AIX, Haiku, etc.

```php
use function oihana\files\{ isLinux , isMac , isWindows , isOtherOS } ;

if ( isLinux() )      { /* ... */ }
else if ( isMac() )   { /* ... */ }
else if ( isWindows() ) { /* ... */ }
else                  { /* fallback POSIX-ish */ }
```

---

## `getHomeDirectory`

```php
getHomeDirectory() : string
```

Retourne le **chemin canonique** du dossier home de l'utilisateur courant.

**Stratégie de résolution :**

1. **Unix / macOS / Linux** : `$HOME` si défini et non-vide.
2. **Windows ≥ XP** : `$HOMEDRIVE` + `$HOMEPATH` (ex. `C:` + `\Users\John`).
3. **Échec** : `RuntimeException`.

Le résultat passe par [`canonicalizePath`](../path/joining-and-normalizing.md#canonicalizepath) — séparateurs uniformisés, slashes finaux retirés.

```php
use function oihana\files\getHomeDirectory;

echo getHomeDirectory() ;
// → /home/alice     (Linux)
// → /Users/alice    (macOS)
// → C:/Users/Alice  (Windows)
```

> 💡 Utilisée en interne par `canonicalizePath` pour l'expansion du `~`.

---

## `getRoot`

```php
getRoot( string $path ) : string
```

Extrait la **partie racine** d'un chemin :

| Entrée                        | Sortie       |
|-------------------------------|--------------|
| `'/usr/local/bin'`            | `'/'`        |
| `'C:\\Windows\\System32'`     | `'C:/'`      |
| `'D:'`                        | `'D:/'`      |
| `'file:///var/log'`           | `'file:///'` |
| `'phar:///app/bundle.phar'`   | `'phar:///'` |
| `'relative/path'`             | `''` (vide) |
| `''`                          | `''` (vide) |

```php
use function oihana\files\getRoot;

echo getRoot( 'file:///var/log' ) ;       // 'file:///'
echo getRoot( '/usr/local/bin' ) ;         // '/'
echo getRoot( 'C:\\Windows\\System32' ) ;  // 'C:/'
echo getRoot( 'D:' ) ;                     // 'D:/'
echo getRoot( 'some/relative/path' ) ;     // ''
```

À comparer avec [`splitPath`](../path/inspection.md#splitpath) qui retourne **racine + reste** dans un array.

---

## `getDirectory`

```php
getDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = false
) : string
```

**Normalise** un chemin de dossier (et valide optionnellement). Très flexible sur l'entrée :

- `null` ou `''` → traité comme chaîne vide (puis assertion levée si `assertable: true`).
- `string` → utilisé tel quel.
- `array` → segments non-vides joints par `DIRECTORY_SEPARATOR`.

Le **slash final est toujours retiré** avant retour.

### Usage

```php
use function oihana\files\getDirectory;

// String avec slash final
getDirectory( '/tmp/' ) ;
// → '/tmp'  (slash retiré, existence vérifiée)

// Array avec segments null/vides ignorés
getDirectory( [ '/tmp' , '' , 'logs' , null ] ) ;
// → '/tmp/logs'  (segments invalides filtrés)

// Sans validation
getDirectory( '/path/does/not/exist/' , assertable: false ) ;
// → '/path/does/not/exist'  (aucune erreur, slash retiré)

// Avec exigence d'écriture
getDirectory( sys_get_temp_dir() , isWritable: true ) ;
```

**Utilisée en interne** par `deleteDirectory`, `getTemporaryDirectory`, `makeTemporaryDirectory`.

---

## `getSchemeAndHierarchy`

```php
getSchemeAndHierarchy( string $filename ) : array
// retour : [?string $scheme, string $hierarchy]
```

Sépare un **scheme** (`file`, `s3`, `phar`, ...) de la partie hiérarchique.

**Validation** : le scheme doit matcher la RFC-3986 (`[A-Za-z][A-Za-z0-9+\-.]*`) — sinon `InvalidArgumentException`.

```php
use function oihana\files\getSchemeAndHierarchy;

getSchemeAndHierarchy( 's3://bucket/folder/img' ) ;
// → ['s3', 'bucket/folder/img']

getSchemeAndHierarchy( '/home/user/report.pdf' ) ;
// → [null, '/home/user/report.pdf']

getSchemeAndHierarchy( 'C:\\Windows\\notepad.exe' ) ;
// → [null, 'C:\\Windows\\notepad.exe']

getSchemeAndHierarchy( 'file:///tmp/cache.db' ) ;
// → ['file', '/tmp/cache.db']

// Scheme malformé
getSchemeAndHierarchy( '1http://invalid' ) ;
// → InvalidArgumentException
```

À comparer avec [`getRoot`](#getroot) (retourne juste la racine) et [`splitPath`](../path/inspection.md#splitpath) (retourne racine + reste avec slash conservé dans la racine).

---

## `getOwnershipInfos`

```php
getOwnershipInfos( string $path ) : OwnershipInfos
```

Retourne les **informations de propriétaire** d'un fichier ou dossier sous forme d'objet `OwnershipInfos` (voir [options.md](../options/make-file-options.md)).

**Champs retournés :**

- `uid` : User ID numérique.
- `gid` : Group ID numérique.
- `owner` : Nom d'utilisateur (via `posix_getpwuid` — requiert `ext-posix`).
- `group` : Nom de groupe (via `posix_getgrgid`).

**Si `ext-posix` n'est pas chargé** (Windows par défaut), `owner` et `group` sont `null` — les UID/GID restent disponibles.

**Lève `RuntimeException`** si le chemin n'existe pas.

```php
use function oihana\files\getOwnershipInfos;

$info = getOwnershipInfos( '/var/www/html' ) ;

echo $info->owner ;  // 'www-data' (ou null sans posix)
echo $info->uid ;    // 33
echo $info ;         // 'www-data:www-data (33:33)'
```

---

## `getBaseFileName`

```php
getBaseFileName(
    string $file ,
    ?array $multiplePartExtensions = null
) : string
```

Retourne le **nom de fichier sans extension**, en supportant les **extensions multi-parties** (`.tar.gz`, `.blade.php`, etc.).

**Liste des extensions multi-parties** :

- Par défaut : `FileExtension::getMultiplePartExtensions()` (qui inclut `.tar.gz`, `.tar.bz2`, `.blade.php`, etc.).
- Override possible via le 2e argument.

**Lève `InvalidArgumentException`** si :
- chemin vide ;
- chemin pointe vers un dossier ou se termine par `/`.

```php
use function oihana\files\getBaseFileName;

// Extension simple
echo getBaseFileName( '/path/to/image.png' ) ;
// → 'image'

// Extension composée connue
echo getBaseFileName( '/backups/2025-07-18.tar.gz' ) ;
// → '2025-07-18'  (et pas '2025-07-18.tar')

echo getBaseFileName( '/views/template.blade.php' ) ;
// → 'template'

// Extension multi-dot inconnue → fallback au dernier point
echo getBaseFileName( '/logs/system.debug.txt' ) ;
// → 'system.debug'

// Pas d'extension
echo getBaseFileName( '/opt/bin/mybinary' ) ;
// → 'mybinary'

// Windows : backslashes normalisés
echo getBaseFileName( 'C:\\Users\\me\\file.tar.gz' ) ;
// → 'file'

// Custom multi-part extensions
echo getBaseFileName( '/path/file.custom.ext' , [ '.custom.ext' ] ) ;
// → 'file'

// Dot file (pas d'extension)
echo getBaseFileName( '/path/.env' ) ;
// → '.env'
```

---

## `getFileExtension`

```php
getFileExtension(
    string $file ,
    ?array $multiplePartExtensions = null ,
    bool   $lowercase = true
) : ?string
```

Retourne l'**extension** d'un fichier, **avec le point initial**, en supportant les extensions multi-parties. Retourne `null` si pas d'extension.

**Par défaut `lowercase: true`** — `.JPG` devient `.jpg`. Désactivable.

```php
use function oihana\files\getFileExtension;

echo getFileExtension( '/path/to/archive.tar.gz' ) ;    // '.tar.gz'
echo getFileExtension( 'photo.JPG' ) ;                  // '.jpg'  (lowercased)
echo getFileExtension( '/some/file.txt' ) ;             // '.txt'
echo getFileExtension( '/templates/home.blade.php' ) ;  // '.blade.php'
echo getFileExtension( 'script.min.js' ) ;              // '.js'  (.min.js n'est pas dans la liste par défaut)

// Custom
echo getFileExtension( 'file.custom.ext' , [ '.custom.ext' ] ) ;  // '.custom.ext'

// Preserve case
echo getFileExtension( 'README.MD' , null , false ) ;   // '.MD'

// Pas d'extension
echo getFileExtension( 'Makefile' ) ;                   // null
echo getFileExtension( '.env' ) ;                       // null  (.env est un nom, pas une extension)

// Windows
echo getFileExtension( 'C:\\projects\\demo.tar.bz2' ) ; // '.tar.bz2'
```

> 💡 Conjointement avec [`FileExtension`](../enums.md) (catalogue d'extensions) et `getBaseFileName`, on peut décomposer proprement n'importe quel chemin :
>
> ```php
> $base = getBaseFileName( $path ) ;
> $ext  = getFileExtension( $path ) ;
> // Re-construit identiquement
> assert( $base . ( $ext ?? '' ) === basename( $path ) ) ;
> ```

---

## `getTimestampedFile`

```php
getTimestampedFile(
    ?string $date       = null ,
     string $basePath   = '' ,
    ?string $extension  = null ,
     string $prefix     = '' ,
     string $suffix     = '' ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true
) : ?string
```

**Génère** un chemin de fichier avec timestamp formaté — **ne crée rien sur disque**.

**Différence avec [`makeTimestampedFile`](creation.md#maketimestampedfile)** : ce dernier appelle `touch` pour créer le fichier vide. `getTimestampedFile` est le **générateur pur** — utile pour calculer un chemin à l'avance.

```php
use function oihana\files\getTimestampedFile;

// Défaut
echo getTimestampedFile() ;
// → './2026-05-26T15:30:12'

// Avec base + prefix + suffix
echo getTimestampedFile(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/tmp' ,
    prefix  : 'backup_' ,
    suffix  : '.sql' ,
) ;
// → '/tmp/backup_2025-12-01T14:00:00.sql'

// Sans validation (le fichier n'est pas censé exister)
echo getTimestampedFile(
    basePath  : '/backups' ,
    extension : '.tar.gz' ,
    assertable: false ,
) ;
// → '/backups/2026-05-26T15:30:12.tar.gz'
```

> ⚠ `$assertable: true` (défaut) attend que le fichier **existe** — comportement contre-intuitif pour un *générateur*. Pour générer un nom de fichier de sortie, passe `assertable: false`.

---

## `getTimestampedDirectory`

```php
getTimestampedDirectory(
    ?string $date       = null ,
     string $basePath   = '' ,
     string $prefix     = '' ,
     string $suffix     = '' ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true
) : string
```

Variante dossier de `getTimestampedFile` — **pas d'extension**, le reste identique.

```php
use function oihana\files\getTimestampedDirectory;

echo getTimestampedDirectory() ;
// → './2026-05-26T15:30:12'

echo getTimestampedDirectory(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/var/backups' ,
    suffix  : '_archive' ,
) ;
// → '/var/backups/2025-12-01T14:00:00_archive'

// Sans assertion (le dossier sera créé après)
echo getTimestampedDirectory(
    prefix    : 'backup_' ,
    suffix    : '_final' ,
    timezone  : 'UTC' ,
    format    : 'Ymd_His' ,
    assertable: false ,
) ;
// → './backup_20260526_133012_final'
```

---

## Voir aussi

- [Création](creation.md) — `makeTimestampedFile`, `makeTimestampedDirectory` qui matérialisent réellement le chemin.
- [Path](../path/README.md) — `splitPath`, `canonicalizePath`, etc.
- [Options](../options/make-file-options.md) — détail de l'objet `OwnershipInfos`.
- [Énumérations](../enums.md) — `FileExtension` (extensions multi-parties).
- [Dépendances](../getting-started/dependencies.md#oihanaphp-core) — `formatDateTime` utilisée par les timestampés.
- [Vue d'ensemble](README.md).
