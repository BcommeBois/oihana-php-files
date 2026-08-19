# oihana/php-files — File, path and archive toolkit for PHP

![Language](https://img.shields.io/badge/language-English-blue)

`oihana/php-files` is a PHP 8.4+ library bundling portable utilities to work with **paths**, **files**, **tar archives**, **Phar**, **OpenSSL encryption**, **TOML configuration** and **MIME types**. The code is organised as **composable standalone functions** autoloaded via `composer.autoload.files`, with **strongly-typed enums** instead of *magic strings*.

![Oihana PHP Files](https://raw.githubusercontent.com/BcommeBois/oihana-php-files/main/.phpdoc/template/assets/images/oihana-php-files-logo-inline-512x160.png)

## Who this documentation is for

PHP developers who want to:

- manipulate **paths** (Unix, Windows, URL, `phar://`) consistently — `joinPaths`, `normalizePath`, `canonicalizePath`, `makeAbsolute`/`makeRelative`;
- perform robust **file/directory** operations with explicit validation — `assertFile`, `findFiles`, `makeDirectory`, `copyFilteredFiles`, `deleteDirectory`;
- create and extract **tar / tar.gz / tar.bz2 archives** (via `phar`) and **zip** (via `ext-zip`), with no subprocess;
- **encrypt/decrypt** files with OpenSSL — **authenticated AES-256-GCM** (AEAD) with key derivation (Argon2id/PBKDF2);
- load **TOML configuration** with default values and deep merging;
- avoid re-declaring standard **MIME types** (image, audio, video, misc) and their **extensions** across projects.

## Quick start

```php
use function oihana\files\path\joinPaths;
use function oihana\files\findFiles;
use function oihana\files\makeDirectory;
use function oihana\files\archive\tar\tar;

$dir = makeDirectory( joinPaths( sys_get_temp_dir(), 'my-project' ) ) ;

$files = findFiles( $dir,
[
    'recursive' => true,
    'mode'      => 'files',
    'pattern'   => '*.php',
]) ;

tar( $files, joinPaths( $dir, 'sources.tar.gz' ) , compression: 'gz' ) ;
```

For full details (options, enums, exception handling, contracts), see the table of contents below.

## Table of contents

### Getting started — [`getting-started/`](getting-started/)

- [Introduction](getting-started/introduction.md) — what the library does, the *oihana* philosophy, and why it exists.
- [Installation](getting-started/installation.md) — PHP 8.4+ requirements, extensions (`fileinfo`, `openssl`), `composer require` command.
- [Dependencies](getting-started/dependencies.md) — `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums`, `devium/toml` and their role.
- [Glossary](getting-started/glossary.md) — *canonical path*, *scheme*, MIME type, Phar, IV, and other recurring terms.

### Paths — [`path/`](path/)

- [Overview](path/README.md) — the 14 functions of the `oihana\files\path` namespace.
- [Joining and normalising](path/joining-and-normalizing.md) — `joinPaths`, `normalizePath`, `canonicalizePath`, `extractCanonicalParts`.
- [Absolute vs relative](path/absolute-vs-relative.md) — `isAbsolutePath`, `isRelativePath`, `makeAbsolute`, `makeRelative`, `computeRelativePath`, `relativePath`.
- [Inspection](path/inspection.md) — `splitPath`, `directoryPath`, `isLocalPath`, `isBasePath`.

### Files — [`files/`](files/)

- [Overview](files/README.md) — the ~45 functions of the `oihana\files` namespace.
- [Assertions](files/assertions.md) — `assertFile`, `assertDirectory`, `assertWritableDirectory`.
- [Creation](files/creation.md) — `makeFile`, `makeDirectory`, `makeTimestampedFile`, `makeTimestampedDirectory`, `makeTemporaryDirectory`.
- [Deletion](files/deletion.md) — `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory`.
- [Reading](files/reading.md) — `getFileLines`, `getFileLinesGenerator`, `countFileLines`, `requireAndMergeArrays`.
- [Discovery](files/discovery.md) — `findFiles`, `recursiveFilePaths`, `shouldExcludeFile`, `sortFiles`, `hasFiles`, `hasDirectories`.
- [Filtered copy](files/copying.md) — `copyFilteredFiles`.
- [Temporary directories](files/temporary.md) — `getTemporaryDirectory`, `makeTemporaryDirectory`, `deleteTemporaryDirectory`.
- [System](files/system.md) — `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot`, `getSchemeAndHierarchy`, `getOwnershipInfos`, `getDirectory`, `getBaseFileName`, `getFileExtension`, `getTimestampedFile`, `getTimestampedDirectory`.
- [MIME and validation](files/mime.md) — `validateMimeType`, `getImageMimeType`.

### Archives — [`archive/`](archive/)

- [Overview](archive/README.md) — the `oihana\files\archive\tar` and `oihana\files\archive\zip` (ZipArchive) namespaces.
- [How a tar gets built](archive/tar-engine.md) — the system GNU tar where there is one, `PharData` otherwise; why only GNU tar, and how to ask which engine is in place.
- [Creating a tar](archive/tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, compression `gz`/`bz2`/none, `tarIsCompressed`.
- [Extracting a tar](archive/untar.md) — `untar`, `validateTarStructure`, `assertTar`, `hasTarExtension`, `hasTarMimeType`.
- [Creating a zip](archive/zip.md) — `zip`, `zipDirectory`, `zipFileInfo`, DEFLATE/STORE compression.
- [Extracting a zip](archive/unzip.md) — `unzip` (Zip Slip + bomb guards + `keepPermissions`), `assertZip`, `hasZipExtension`, `hasZipMimeType`, `validateZipStructure`.

### Phar — [`phar/`](phar/)

- [Overview](phar/README.md) — `assertPhar`, `getPharBasePath`, `getPharCompressionType`, `preservePharFilePermissions`.

### OpenSSL — [`openssl/`](openssl/)

- [Overview](openssl/README.md) — the `OpenSSLFileEncryption` class: **authenticated** file encryption/decryption (AES-256-GCM, V2 format; backward-compatible reading of V1 files).

### TOML — [`toml/`](toml/)

- [Overview](toml/README.md) — `resolveTomlConfig`: load a TOML config, deep-merge it with defaults, optional init callback.

### Options — [`options/`](options/)

- [Overview](options/README.md) — the abstract `Options` class (hydration, serialisation, placeholder formatting, CLI generation) and its `Option` contract.
- [Concrete options](options/make-file-options.md) — `MakeFileOptions`, `OwnershipInfos` as worked examples.

### Enumerations and exceptions

- [Enums catalogue](enums.md) — the 18 enums (`FileMimeType`, `FileExtension`, `ImageMimeType`, `AudioMimeType`, `VideoMimeType`, `ImageFormat`, `CompressionType`, `TarExtension`, `TarInfo`, `TarOption`, `FindMode`, `FindFileOption`, `FindFilesOption`, `MakeDirectoryOption`, `MakeFileOption`, `RecursiveFilePathsOption`, `OwnershipInfo`, `CanonicalizeBuffer`) and their traits.
- [Exceptions](exceptions.md) — `DirectoryException`, `FileException`, `UnsupportedCompressionException`: when and how to catch them.

### Cross-cutting

- [Security](security.md) — security perimeter, covered / not-covered threats, user best practices.
- [Tests & coverage](testing.md) — run the PHPUnit suite, measure coverage, and the `@codeCoverageIgnore` annotation policy.
- [Tips and pitfalls](tips.md) — golden rules and incidents encountered (Windows paths, *symlinks*, permissions, encoding, etc.).

## Source code

The library code lives under [`src/oihana/`](../../src/oihana/):

- [`src/oihana/files/`](../../src/oihana/files/) — main namespace `oihana\files`.
- [`src/oihana/options/`](../../src/oihana/options/) — cross-cutting namespace `oihana\options`.

## See also

- [Packagist `oihana/php-files`](https://packagist.org/packages/oihana/php-files) — the package page.
- [API reference (phpDocumentor)](https://bcommebois.github.io/oihana-php-files) — class/function-level generated reference.
- [Tips and pitfalls](tips.md) — conventions and common mistakes.
