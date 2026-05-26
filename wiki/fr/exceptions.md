# Exceptions

`oihana/php-files` définit **3 exceptions personnalisées** dans le namespace `oihana\files\exceptions`. Toutes héritent directement de `\Exception` — pas de hiérarchie intermédiaire.

| Classe | Quand levée |
|---|---|
| [`FileException`](#fileexception) | Erreur liée à un **fichier** : assertion échouée, écriture impossible, MIME refusé, chiffrement/déchiffrement KO. |
| [`DirectoryException`](#directoryexception) | Erreur liée à un **dossier** : assertion échouée, création/suppression échouée, traversal échoué. |
| [`UnsupportedCompressionException`](#unsupportedcompressionexception) | Type de **compression non supporté** par les fonctions tar / phar. |

> 💡 Ces exceptions ne **partagent pas** de classe parente intermédiaire. Pour les attraper toutes en bloc, utilise `\Exception` ou `\Throwable`.

---

## `FileException`

```php
namespace oihana\files\exceptions ;

class FileException extends \Exception { }
```

Levée par toutes les fonctions et classes qui touchent à un fichier individuel.

### Fonctions/classes qui lèvent

| Code | Cas |
|---|---|
| [`assertFile`](files/assertions.md#assertfile) | Chemin null/vide, n'est pas un fichier, non lisible, non inscriptible, MIME refusé. |
| [`assertWritableDirectory`](files/assertions.md#assertwritabledirectory) | Non — celle-ci lève `DirectoryException`. |
| [`makeFile`](files/creation.md#makefile) | Chemin vide, échec d'écriture, échec `chmod`/`chown`/`chgrp`. |
| [`makeTimestampedFile`](files/creation.md#maketimestampedfile) | Échec `touch` sur le chemin généré. |
| [`deleteFile`](files/deletion.md#deletefile) | Échec `unlink` ou assertions. |
| [`clearFile`](files/deletion.md#clearfile) | En mode `assertable: true`, via `assertFile`. |
| [`countFileLines`](files/reading.md#countfilelines), [`getFileLines`](files/reading.md#getfilelines), [`getFileLinesGenerator`](files/reading.md#getfilelinesgenerator) | Échec `fopen`, ou assertions. |
| [`getTimestampedFile`](files/system.md#gettimestampedfile) | Wrapping d'erreurs internes. |
| [`validateMimeType`](files/mime.md#validatemimetype) | MIME indéterminable, ou hors liste autorisée. |
| [`OpenSSLFileEncryption::encrypt`](openssl/README.md) / `decrypt` | Wrapping d'erreurs (mais lève aussi `RuntimeException` pour les erreurs purement cryptographiques). |
| [`tar`](archive/tar.md#tar) | Un des `$paths` n'existe pas. |

### Gestion typique

```php
use function oihana\files\assertFile;
use oihana\files\exceptions\FileException;

try {
    assertFile( '/upload/document.pdf' , [ 'application/pdf' ] ) ;
    // ... traitement
}
catch ( FileException $e ) {
    http_response_code( 400 ) ;
    echo 'Fichier invalide : ' . $e->getMessage() ;
}
```

### Inspection du message

Les messages sont **descriptifs et incluent le chemin** :

- `'The file path must not be null.'`
- `'The file path must not be empty.'`
- `'The file path "/foo" is not a valid file.'`
- `'The file "/foo" is not readable.'`
- `'The file "/foo" is not writable.'`
- `'Invalid MIME type for file "/foo". Expected one of [...], but got "..."`.

> 💡 Pour des erreurs internationalisables ou catégorisées, considérer un wrapper de logging qui catch + classifie selon une regex sur le message.

---

## `DirectoryException`

```php
namespace oihana\files\exceptions ;

class DirectoryException extends \Exception { }
```

Levée par toutes les fonctions et classes qui touchent à un dossier.

### Fonctions/classes qui lèvent

| Code | Cas |
|---|---|
| [`assertDirectory`](files/assertions.md#assertdirectory) | Chemin null/vide, n'est pas un dossier, non lisible, non inscriptible, permissions inattendues. |
| [`assertWritableDirectory`](files/assertions.md#assertwritabledirectory) | Idem, en forçant `isWritable: true`. |
| [`makeDirectory`](files/creation.md#makedirectory) | Chemin vide, échec `mkdir`, non-inscriptible, échec `chown`/`chgrp`. |
| [`makeTimestampedDirectory`](files/creation.md#maketimestampeddirectory) | Wrapping d'erreurs internes. |
| [`makeTemporaryDirectory`](files/creation.md#maketemporarydirectory) | Échec création du dossier temp. |
| [`deleteDirectory`](files/deletion.md#deletedirectory) | Échec `rmdir`, `unlink` interne, ou assertions. |
| [`getDirectory`](files/system.md#getdirectory) | Si `assertable: true`, via `assertDirectory`. |
| [`getTemporaryDirectory`](files/system.md#gettemporarydirectory) | Si `assertable: true`. |
| [`getTimestampedDirectory`](files/system.md#gettimestampeddirectory) | Wrapping d'erreurs internes. |
| [`tar`](archive/tar.md#tar) | Échec création du dossier temp interne. |
| [`OpenSSLFileEncryption::encrypt`](openssl/README.md) | Dossier de sortie non inscriptible. |

### Inspection du message

- `'The directory path must not be null.'`
- `'The directory path must not be empty.'`
- `'The path "/foo" is not a valid directory.'`
- `'The directory "/foo" is not readable.'`
- `'The directory "/foo" is not writable.'`
- `'The directory "/foo" has permissions "777", expected "755".'`
- `'Failed to create directory "/foo".'`
- `'Failed to remove directory "/foo".'`
- `'Failed to remove file "/foo".'` (depuis `deleteDirectory` quand `unlink` interne échoue)

### Gestion typique

```php
use function oihana\files\makeDirectory;
use oihana\files\exceptions\DirectoryException;

try {
    $path = makeDirectory( '/var/log/myapp' , 0755 , true , 'www-data' , 'www-data' ) ;
}
catch ( DirectoryException $e ) {
    error_log( '[FATAL] Impossible de provisionner le dossier de logs : ' . $e->getMessage() ) ;
    exit( 1 ) ;
}
```

---

## `UnsupportedCompressionException`

```php
namespace oihana\files\exceptions ;

class UnsupportedCompressionException extends \Exception { }
```

Levée quand un **type de compression non reconnu** est passé à une fonction d'archive ou de Phar.

### Fonctions qui lèvent

| Code | Cas |
|---|---|
| [`tar`](archive/tar.md#tar) | Si `$compression` n'est ni `gz`/`bz2`/`none` (ex. `'xz'`, `'rar'`). |
| [`getPharCompressionType`](phar/README.md#getpharcompressiontype) | Idem. |
| [`TarExtension::getExtensionForCompression`](enums.md#tarextension) | Idem. |
| [`TarExtension::getCompressionExtension`](enums.md#tarextension) | Idem. |

### Pourquoi cette exception est nécessaire

`CompressionType` définit 8 constantes (`NONE`, `GZIP`, `BZIP2`, `ZIP`, `LZ4`, `LZMA`, `XZ`, `ZSTD`), mais seules les **3 premières** sont supportées par l'implémentation (basée sur `PharData`). Cette exception fait le pont entre l'énumération (large) et l'implémentation effective (restreinte).

### Gestion typique

```php
use function oihana\files\archive\tar\tar;
use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;

try {
    tar( '/var/log' , '/backups/logs.tar.xz' , CompressionType::XZ ) ;
}
catch ( UnsupportedCompressionException $e ) {
    // Fallback sur gzip
    tar( '/var/log' , '/backups/logs.tar.gz' , CompressionType::GZIP ) ;
}
```

---

## Stratégies de gestion

### 1. Catch fin par type

Idéal quand on veut **réagir différemment** selon le contexte :

```php
try {
    doSomeFileOps() ;
}
catch ( FileException $e ) {
    // Erreur "fichier" : retourner 400 ou 404 selon le message
}
catch ( DirectoryException $e ) {
    // Erreur "dossier" : 500 (le serveur a un problème de provisionning)
}
catch ( UnsupportedCompressionException $e ) {
    // Cas métier spécifique : fallback ou message clair
}
```

### 2. Catch large

Pour un wrapper qui veut **juste logger et abort** :

```php
try {
    doFileWork() ;
}
catch ( \Exception $e ) {
    // Couvre les 3 exceptions oihana + tout le reste (RuntimeException, etc.)
    logger()->error( 'File operation failed' , [ 'exception' => $e ] ) ;
    throw $e ; // re-throw pour ne pas masquer
}
```

### 3. Pattern `assertable: false` plutôt que try/catch

Pour les opérations **destructives** où l'absence n'est pas une erreur :

```php
use function oihana\files\deleteFile;

// Au lieu de try { deleteFile() } catch { ignore }
deleteFile( $maybeExisting , assertable: false ) ;
// → false silencieux si le fichier n'existe pas
```

Voir [Suppression](files/deletion.md) pour le détail.

### 4. Re-throw avec contexte

`oihana/php-files` utilise déjà ce pattern (cf. `tar`, `getTimestampedFile`) :

```php
try {
    // ... opération
}
catch ( \Throwable $original ) {
    throw new FileException(
        'Échec de l\'opération X : ' . $original->getMessage() ,
        $original->getCode() ,
        $original , // ← preserve la chaîne via $previous
    ) ;
}
```

Tu peux remonter la chaîne avec `$e->getPrevious()` côté caller.

---

## Hiérarchie absente : à savoir

Les 3 exceptions héritent **toutes directement de `\Exception`** — il n'y a **pas** de classe `OihanaFilesException` parente. Cela signifie :

- ❌ Tu ne peux pas faire `catch ( OihanaFilesException $e )` pour les attraper toutes ensemble (mais seulement spécifique à oihana).
- ✅ Tu peux les attraper via `catch ( \Exception $e )` mais ça englobe **tout** (pas seulement oihana).
- ✅ Tu peux les attraper individuellement avec un `catch` multiple (`catch ( FileException | DirectoryException $e )`).

C'est un choix de simplicité — possible à revoir si le besoin émerge. Voir [Tips](tips.md).

## Voir aussi

- [Assertions](files/assertions.md) — source principale de `FileException` et `DirectoryException`.
- [Création](files/creation.md), [Suppression](files/deletion.md) — autres sources.
- [Tar](archive/tar.md) — source de `UnsupportedCompressionException`.
- [Énumérations](enums.md) — `CompressionType`, `TarExtension`.
- [Tips](tips.md) — pièges et conventions.
- [Sommaire FR](README.md).
