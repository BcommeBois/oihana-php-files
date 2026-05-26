# Fichiers — `oihana\files`

Le namespace `oihana\files` rassemble **~45 fonctions standalone** pour les opérations courantes sur fichiers et dossiers. Elles vivent à la racine de [`src/oihana/files/`](../../../src/oihana/files/) (les sous-namespaces `path`, `archive`, `phar`, `openssl`, `toml`, `images` sont documentés séparément).

> 💡 **Toutes ces fonctions sont autochargées** via `composer.autoload.files`. Toutes lèvent des exceptions typées (`FileException`, `DirectoryException`) en cas de problème — voir [exceptions.md](../exceptions.md).

## Organisation

Les fonctions sont groupées en **9 catégories** documentées séparément :

| Catégorie | Pages | Fonctions |
|---|---|---|
| **Assertions** | [assertions.md](assertions.md) | `assertFile`, `assertDirectory`, `assertWritableDirectory` |
| **Création** | [creation.md](creation.md) | `makeFile`, `makeDirectory`, `makeTimestampedFile`, `makeTimestampedDirectory`, `makeTemporaryDirectory` |
| **Suppression** | [deletion.md](deletion.md) | `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory` |
| **Répertoires temporaires** | [temporary.md](temporary.md) | `getTemporaryDirectory`, `makeTemporaryDirectory`, `deleteTemporaryDirectory` (workflow) |
| **Lecture** | [reading.md](reading.md) | `getFileLines`, `getFileLinesGenerator`, `countFileLines`, `requireAndMergeArrays` |
| **Découverte** | [discovery.md](discovery.md) | `findFiles`, `recursiveFilePaths`, `shouldExcludeFile`, `sortFiles`, `hasFiles`, `hasDirectories` |
| **Copie filtrée** | [copying.md](copying.md) | `copyFilteredFiles` |
| **Système** | [system.md](system.md) | `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot`, `getSchemeAndHierarchy`, `getOwnershipInfos`, `getDirectory`, `getBaseFileName`, `getFileExtension`, `getTimestampedFile`, `getTimestampedDirectory` |
| **MIME** | [mime.md](mime.md) | `validateMimeType`, `getImageMimeType` |

## Conventions transverses

### 1. Validation par assertions

Toutes les fonctions destructives (`delete*`, `clear*`) acceptent un paramètre `$assertable` (par défaut `true`) qui contrôle l'utilisation de [`assertFile`](assertions.md#assertfile) ou [`assertDirectory`](assertions.md#assertdirectory) en amont. Quand `false`, la fonction tente l'opération directement et renvoie `false` en cas d'échec au lieu de lever une exception.

```php
deleteFile( '/path/maybe-missing.txt' , assertable: false ) ; // false si absent, pas d'exception
deleteFile( '/path/exists.txt' ) ;                            // throws FileException si problème
```

### 2. Options : tableau associatif OU paramètres positionnels

Les fonctions complexes (`makeFile`, `makeDirectory`) acceptent **deux signatures équivalentes** :

```php
// Style positionnel
makeFile( '/path/to/file.txt' , 'content' , [ 'permissions' => 0600 ] ) ;

// Style options-as-array (avec clés d'enum)
makeFile([
    MakeFileOption::FILE        => '/path/to/file.txt' ,
    MakeFileOption::CONTENT     => 'content' ,
    MakeFileOption::PERMISSIONS => 0600 ,
]) ;
```

À toi de choisir selon le contexte ; le style options-as-array est préféré quand tu construis les options dynamiquement.

### 3. Exceptions typées

| Exception | Levée par |
|---|---|
| [`FileException`](../exceptions.md) | Tout ce qui touche aux fichiers (`assertFile`, `makeFile`, `deleteFile`, `clearFile`, lecture, MIME, ownership). |
| [`DirectoryException`](../exceptions.md) | Tout ce qui touche aux dossiers (`assertDirectory`, `makeDirectory`, `deleteDirectory`, `*TemporaryDirectory`, `*TimestampedDirectory`). |
| [`UnsupportedCompressionException`](../exceptions.md) | Archive tar uniquement (voir [archive/](../archive/README.md)). |

### 4. Pas d'I/O caché

Aucune fonction ne fait d'I/O réseau, ne lance de subprocess (sauf l'appel à `posix_*` pour ownership), ne touche à un fichier hors du chemin passé en argument. **Pas de side-effect surprenant**.

## Exemple typique : workflow de backup

```php
use function oihana\files\{ makeTemporaryDirectory , makeTimestampedFile , copyFilteredFiles , deleteTemporaryDirectory } ;
use function oihana\files\path\joinPaths ;

// 1. Préparer un dossier temporaire
$workDir = makeTemporaryDirectory( [ 'backup' , 'staging' ] ) ;

// 2. Copier les fichiers à sauvegarder en filtrant
$copied = copyFilteredFiles( '/var/www/site' , $workDir , [
    'excludes' => [ '.git' , 'node_modules' , 'vendor' ] ,
]) ;

// 3. Créer un fichier d'archive horodaté à côté
$archive = makeTimestampedFile(
    basePath  : '/backups' ,
    extension : '.tar.gz'  ,
    prefix    : 'site-'
) ;
// → e.g. /backups/site-2026-05-26T15:30:12.tar.gz

// (production de l'archive avec tar() — voir wiki/fr/archive/)

// 4. Nettoyage
deleteTemporaryDirectory( [ 'backup' , 'staging' ] ) ;
```

## Voir aussi

- [Path namespace](../path/README.md) — pour la manipulation des chemins avant les opérations fichiers.
- [Archive](../archive/README.md) — pour produire un `.tar.gz` à partir d'un dossier.
- [OpenSSL](../openssl/README.md) — pour chiffrer un fichier de backup.
- [Énumérations](../enums.md) — catalogue des `MakeFileOption`, `MakeDirectoryOption`, etc.
- [Exceptions](../exceptions.md) — hiérarchie complète.
- [Sommaire FR](../README.md).
