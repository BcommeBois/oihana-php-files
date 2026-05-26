# oihana/php-files — Outils de fichiers, chemins et archives pour PHP

![Langue](https://img.shields.io/badge/langue-Français-blue)

`oihana/php-files` est une bibliothèque PHP 8.4+ qui rassemble des utilitaires portables pour manipuler **chemins**, **fichiers**, **archives tar**, **Phar**, **chiffrement OpenSSL**, **configuration TOML** et **types MIME**. Le code est organisé en **fonctions autonomes composables** chargées par `composer.autoload.files`, avec des **énumérations fortement typées** pour éviter les *magic strings*.

![Oihana PHP Files](https://raw.githubusercontent.com/BcommeBois/oihana-php-files/main/.phpdoc/template/assets/images/oihana-php-files-logo-inline-512x160.png)

## À qui s'adresse cette documentation

Aux développeurs PHP qui veulent :

- manipuler des **chemins** (Unix, Windows, URL, `phar://`) de manière homogène — `joinPaths`, `normalizePath`, `canonicalizePath`, `makeAbsolute`/`makeRelative` ;
- effectuer des opérations **fichier/dossier** robustes avec validations explicites — `assertFile`, `findFiles`, `makeDirectory`, `copyFilteredFiles`, `deleteDirectory` ;
- créer et extraire des **archives tar/tar.gz/tar.bz2** sans dépendance externe au-delà de l'extension `phar` ;
- **chiffrer/déchiffrer** des fichiers via OpenSSL (`aes-256-cbc` par défaut) avec IV embarqué ;
- charger une configuration **TOML** avec valeurs par défaut et fusion profonde ;
- éviter de redéfinir partout les **types MIME** standards (image, audio, vidéo, divers) et leurs **extensions** associées.

## Démarrage rapide

```php
use function oihana\files\path\joinPaths;
use function oihana\files\findFiles;
use function oihana\files\makeDirectory;
use function oihana\files\archive\tar\tar;

$dir = makeDirectory( joinPaths( sys_get_temp_dir(), 'mon-projet' ) ) ;

$files = findFiles( $dir,
[
    'recursive' => true,
    'mode'      => 'files',
    'pattern'   => '*.php',
]) ;

tar( $files, joinPaths( $dir, 'sources.tar.gz' ) , compression: 'gz' ) ;
```

Pour les détails (options, énumérations, gestion des exceptions, contrats), voir le sommaire ci-dessous.

## Table des matières

### Démarrage — [`getting-started/`](getting-started/)

- [Introduction](getting-started/introduction.md) — ce que fait la librairie, la philosophie *oihana*, et pourquoi elle existe.
- [Installation](getting-started/installation.md) — prérequis PHP 8.4+, extensions (`fileinfo`, `openssl`), commande `composer require`.
- [Dépendances](getting-started/dependencies.md) — `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums`, `devium/toml` et leur rôle.
- [Glossaire](getting-started/glossary.md) — *canonical path*, *scheme*, MIME type, Phar, IV, et autres termes récurrents.

### Chemins — [`path/`](path/)

- [Vue d'ensemble](path/README.md) — les 14 fonctions du namespace `oihana\files\path`.
- [Jointure et normalisation](path/joining-and-normalizing.md) — `joinPaths`, `normalizePath`, `canonicalizePath`, `extractCanonicalParts`.
- [Absolu vs relatif](path/absolute-vs-relative.md) — `isAbsolutePath`, `isRelativePath`, `makeAbsolute`, `makeRelative`, `computeRelativePath`, `relativePath`.
- [Inspection](path/inspection.md) — `splitPath`, `directoryPath`, `isLocalPath`, `isBasePath`.

### Fichiers — [`files/`](files/)

- [Vue d'ensemble](files/README.md) — les ~45 fonctions du namespace `oihana\files`.
- [Assertions](files/assertions.md) — `assertFile`, `assertDirectory`, `assertWritableDirectory`.
- [Création](files/creation.md) — `makeFile`, `makeDirectory`, `makeTimestampedFile`, `makeTimestampedDirectory`, `makeTemporaryDirectory`.
- [Suppression](files/deletion.md) — `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory`.
- [Lecture](files/reading.md) — `getFileLines`, `getFileLinesGenerator`, `countFileLines`, `requireAndMergeArrays`.
- [Découverte](files/discovery.md) — `findFiles`, `recursiveFilePaths`, `shouldExcludeFile`, `sortFiles`, `hasFiles`, `hasDirectories`.
- [Copie filtrée](files/copying.md) — `copyFilteredFiles`.
- [Répertoires temporaires](files/temporary.md) — `getTemporaryDirectory`, `makeTemporaryDirectory`, `deleteTemporaryDirectory`.
- [Système](files/system.md) — `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot`, `getSchemeAndHierarchy`, `getOwnershipInfos`, `getDirectory`, `getBaseFileName`, `getFileExtension`, `getTimestampedFile`, `getTimestampedDirectory`.
- [MIME et validation](files/mime.md) — `validateMimeType`, `getImageMimeType`.

### Archives — [`archive/`](archive/)

- [Vue d'ensemble](archive/README.md) — les 9 fonctions du namespace `oihana\files\archive\tar`.
- [Créer un tar](archive/tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, compression `gz`/`bz2`/aucune, `tarIsCompressed`.
- [Extraire un tar](archive/untar.md) — `untar`, `validateTarStructure`, `assertTar`, `hasTarExtension`, `hasTarMimeType`.

### Phar — [`phar/`](phar/)

- [Vue d'ensemble](phar/README.md) — `assertPhar`, `getPharBasePath`, `getPharCompressionType`, `preservePharFilePermissions`.

### OpenSSL — [`openssl/`](openssl/)

- [Vue d'ensemble](openssl/README.md) — la classe `OpenSSLFileEncryption` : chiffrement/déchiffrement de fichiers avec IV embarqué.

### TOML — [`toml/`](toml/)

- [Vue d'ensemble](toml/README.md) — `resolveTomlConfig` : chargement d'une config TOML, fusion profonde avec valeurs par défaut, callback d'initialisation.

### Options — [`options/`](options/)

- [Vue d'ensemble](options/README.md) — la classe abstraite `Options` (hydratation, sérialisation, format placeholders, génération CLI) et son contrat `Option`.
- [Options concrètes](options/make-file-options.md) — `MakeFileOptions`, `OwnershipInfos` en exemples concrets.

### Énumérations et exceptions

- [Catalogue des énumérations](enums.md) — les 18 enums (`FileMimeType`, `FileExtension`, `ImageMimeType`, `AudioMimeType`, `VideoMimeType`, `ImageFormat`, `CompressionType`, `TarExtension`, `TarInfo`, `TarOption`, `FindMode`, `FindFileOption`, `FindFilesOption`, `MakeDirectoryOption`, `MakeFileOption`, `RecursiveFilePathsOption`, `OwnershipInfo`, `CanonicalizeBuffer`) et leurs traits.
- [Exceptions](exceptions.md) — `DirectoryException`, `FileException`, `UnsupportedCompressionException` : quand et comment les attraper.

### Transverse

- [Astuces et pièges](tips.md) — règles d'or et incidents rencontrés (chemins Windows, *symlinks*, permissions, *encoding*, etc.).

## Code source

Le code de la librairie vit sous [`src/oihana/`](../../src/oihana/) :

- [`src/oihana/files/`](../../src/oihana/files/) — namespace principal `oihana\files`.
- [`src/oihana/options/`](../../src/oihana/options/) — namespace transverse `oihana\options`.

## Voir aussi

- [Packagist `oihana/php-files`](https://packagist.org/packages/oihana/php-files) — page du package.
- [Référence API (phpDocumentor)](https://bcommebois.github.io/oihana-php-files) — référence générée au niveau classe/fonction.
- [Astuces et pièges](tips.md) — conventions et erreurs fréquentes.
