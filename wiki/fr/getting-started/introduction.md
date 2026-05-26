# Introduction

## Que fait `oihana/php-files` ?

`oihana/php-files` est une **boîte à outils PHP 8.4+** qui consolide en un seul package les opérations courantes mais dispersées de l'écosystème : manipulation de **chemins**, opérations sur les **fichiers et répertoires**, **archivage tar** (avec gzip/bzip2), inspection des **Phar**, **chiffrement OpenSSL** de fichiers, lecture de **configurations TOML**, et un catalogue exhaustif de **types MIME** et d'**extensions**.

Le code ne définit *aucune classe massive* : il s'organise comme une collection de **fonctions autonomes** (~70), chacune dans son propre fichier, autochargée via `composer.autoload.files`. À cela s'ajoute une poignée de **classes pour cas spécifiques** (chiffrement, options hydratables) et une **vingtaine d'énumérations** fortement typées.

## La philosophie *oihana*

Cinq principes traversent l'ensemble de la librairie — et plus largement l'écosystème `oihana/*` :

1. **Fonctions composables, pas de framework lourd.** Chaque utilitaire est une fonction PHP autoload-friendly. On compose `joinPaths()` avec `canonicalizePath()` et `makeAbsolute()` au lieu d'instancier un `PathBuilder` et de chaîner ses méthodes. La courbe d'apprentissage est plate : si tu sais lire une signature de fonction, tu sais utiliser la librairie.

2. **Zéro *magic string*.** Les options de configuration (`'recursive'`, `'mode'`, `'pattern'`...) sont exposées comme constantes d'énumération (`FindFilesOption::RECURSIVE`, `FindFilesOption::MODE`, etc.). Les types MIME (`'image/jpeg'`, `'application/cbor'`) sont des constantes de `FileMimeType`. Les extensions (`.tar.gz`, `.cose`) vivent dans `FileExtension`. Conséquence directe : les renommages sont *refactor-friendly*, l'IDE complète, et un typo se voit immédiatement.

3. **Validation explicite via assertions.** Les fonctions `assertFile`, `assertDirectory`, `assertWritableDirectory`, `assertPhar`, `assertTar` lèvent des exceptions typées (`FileException`, `DirectoryException`) avec des messages parlants. Au lieu d'enchaîner `if ( !is_file($path) ) throw ...`, on écrit `assertFile($path)` une fois et on garantit l'état du système.

4. **Tests unitaires complets.** Tout le code est couvert par PHPUnit 12, avec un usage important de `mikey179/vfsstream` pour simuler le système de fichiers sans toucher au disque. Cela rend la librairie **fiable** et **utilisable comme dépendance dans tes propres tests**.

5. ***Cross-platform* par défaut.** La normalisation des chemins gère Unix, Windows, URL et `phar://`. Les helpers `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot` permettent d'écrire du code portable sans recourir à des `DIRECTORY_SEPARATOR` partout.

## Pourquoi cette librairie ?

PHP fournit historiquement un grand nombre de fonctions natives pour les fichiers (`is_file`, `is_dir`, `glob`, `realpath`, `pathinfo`, `mime_content_type`, `tempnam`, etc.), mais ces fonctions :

- ont des **conventions de retour hétérogènes** (`false` vs exception, *string* vs *array*, `null` parfois) ;
- ne couvrent **pas la sémantique haut niveau** (joindre deux chemins en respectant le *scheme*, normaliser `..` proprement, lister récursivement avec filtre, créer un fichier horodaté, chiffrer avec IV embarqué) ;
- **ne sont pas typées** (impossible d'attraper une exception spécifique, on doit tester `false` ou inspecter le message d'erreur).

`oihana/php-files` comble ces lacunes :

- **Une API homogène** : toutes les fonctions retournent un type clair, lèvent des exceptions typées en cas d'erreur, et acceptent leurs options sous forme de tableau associatif documenté avec `@param array{...}` (annotations PHPStan / Psalm-friendly).
- **Des opérations haut niveau** prêtes à l'emploi : `findFiles` avec filtres glob/regex/callback, `copyFilteredFiles` avec liste d'exclusions, `makeTimestampedFile` pour les *backups*, `tarDirectory` avec compression.
- **Un catalogue MIME / extensions partagé** : `FileMimeType` couvre les formats web standards plus des formats spécialisés (`application/cbor`, `application/cose`, `application/cose.enc`), et reste un point unique à mettre à jour.

## Public visé et prérequis

Cette documentation suppose que le lecteur :

- maîtrise **PHP 8.4+** — l'usage systématique des *enums*, des *readonly properties*, des *first-class callable syntax* et des *named arguments* est central ;
- est à l'aise avec **Composer** et son mécanisme d'`autoload.files` ;
- a une connaissance basique des **extensions PHP** `fileinfo`, `openssl` et `phar` (toutes activées par défaut dans la plupart des distributions).

Aucune connaissance préalable des autres librairies `oihana/*` n'est requise. Cependant, `oihana/php-files` réutilise des **constantes** (`Char`, `Order`) et des **helpers fonctionnels** (`oihana\core\arrays\deepMerge`, `oihana\core\strings\isRegexp`, `oihana\reflect\helpers\getFunctionInfo`) fournis par :

- [`oihana/php-core`](https://github.com/BcommeBois/oihana-php-core) — utilitaires fondamentaux.
- [`oihana/php-reflect`](https://github.com/BcommeBois/oihana-php-reflect) — réflexion et hydratation.
- [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) — énumérations partagées.

Ces dépendances sont déclarées dans `composer.json` et tirées automatiquement — voir [Dépendances](dependencies.md) pour le détail.

## Structure du code

Le code vit sous [`src/oihana/`](../../../src/oihana/) selon deux namespaces racines :

```
src/oihana/
├── files/                  ← namespace oihana\files
│   ├── *.php               ← ~45 fonctions racine (assertFile, findFiles, makeDirectory, ...)
│   ├── archive/tar/        ← 9 fonctions tar (tar, untar, tarDirectory, ...)
│   ├── path/               ← 14 fonctions de chemins (joinPaths, normalizePath, ...)
│   ├── phar/               ← 4 fonctions Phar
│   ├── openssl/            ← classe OpenSSLFileEncryption
│   ├── toml/               ← resolveTomlConfig
│   ├── images/             ← getImageMimeType
│   ├── enums/              ← 18 énumérations + 3 traits MIME
│   ├── exceptions/         ← DirectoryException, FileException, UnsupportedCompressionException
│   └── options/            ← MakeFileOptions, OwnershipInfos (objets options concrets)
└── options/                ← namespace oihana\options
    ├── Options.php         ← classe abstraite hydratable + sérialisable + format CLI
    └── Option.php          ← contrat associé
```

La répartition par sous-dossier reflète la table des matières du wiki : chaque sous-domaine technique correspond à une section de la documentation.

## Et la suite ?

- [Installation](installation.md) — installer la librairie, vérifier les prérequis, *one-liner* `composer require`.
- [Dépendances](dependencies.md) — le rôle des paquets `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums`, `devium/toml`.
- [Glossaire](glossary.md) — les termes récurrents (*canonical path*, *scheme*, IV, *MIME type*, Phar, etc.).
- [Vue d'ensemble des chemins](../path/README.md) — premier sous-domaine fonctionnel.

Pour un index complet, retour au [sommaire FR](../README.md).
