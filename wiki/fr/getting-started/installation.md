# Installation

## Prérequis

### Version PHP

`oihana/php-files` requiert **PHP 8.4 ou supérieur**. La librairie utilise des fonctionnalités modernes du langage :

- **Enums** (PHP 8.1+) — `FileMimeType`, `FindFilesOption`, etc.
- **Readonly properties** (PHP 8.1+) — propriétés immuables dans certaines classes d'options.
- **First-class callable syntax** (PHP 8.1+) — passage de fonctions comme valeurs.
- **Asymmetric visibility et property hooks** (PHP 8.4+) — utilisés dans `oihana/php-core` et `oihana/php-reflect` (dépendances transitives).

Vérification :

```bash
php -v
# PHP 8.4.x (cli) (built: ...)
```

Si la version est inférieure, mets PHP à jour via ton gestionnaire de paquets (`brew install php@8.4`, `apt install php8.4`, etc.).

### Extensions PHP requises

Déclarées dans `composer.json` :

| Extension | Rôle dans `oihana/php-files` |
|---|---|
| `ext-fileinfo` | Détection du type MIME (`mime_content_type`, `finfo_*`) — utilisée par `validateMimeType`, `getImageMimeType`. |
| `ext-openssl` | Chiffrement/déchiffrement de fichiers (`OpenSSLFileEncryption`). |

Extensions **recommandées** (sinon certaines fonctions échouent silencieusement ou ne sont pas disponibles) :

| Extension | Rôle |
|---|---|
| `ext-phar` | Archives `tar`, `tar.gz`, `tar.bz2` (`tar`, `untar`, `tarDirectory`). Activée par défaut dans la plupart des distributions. |
| `ext-zlib` | Compression `gzip` pour les archives tar (requis pour les tests). |
| `ext-posix` | Informations de propriétaire / groupe (`getOwnershipInfos`) — requis pour les tests sur Unix. |

Vérification :

```bash
php -m | grep -iE 'fileinfo|openssl|phar|zlib|posix'
```

## Installation via Composer

> Requiert [Composer](https://getcomposer.org/) ≥ 2.0.

```bash
composer require oihana/php-files
```

Cette commande tire automatiquement les dépendances `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums` et `devium/toml` (voir [Dépendances](dependencies.md)).

### Installation en mode développement

Pour contribuer ou lancer la suite de tests localement :

```bash
git clone https://github.com/BcommeBois/oihana-php-files.git
cd oihana-php-files
composer install
```

## Vérification post-installation

### Charger une fonction au hasard

Crée un fichier `test.php` à la racine de ton projet :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function oihana\files\path\joinPaths;
use function oihana\files\path\canonicalizePath;

echo joinPaths( '/var/www', '..', 'data', './logs/' ) , PHP_EOL ;
// /var/data/logs

echo canonicalizePath( 'phar:///app/bundle.phar/src/../config' ) , PHP_EOL ;
// phar:///app/bundle.phar/config
```

```bash
php test.php
```

Si la sortie correspond, l'autoload `composer.autoload.files` fonctionne et la librairie est opérationnelle.

### Lancer la suite de tests (mode dev uniquement)

`oihana/php-files` est couvert par [PHPUnit 12](https://phpunit.de/). Toute la suite se lance via le script Composer :

```bash
composer run-script test
# ou plus court :
composer test
```

Pour exécuter un fichier de test spécifique :

```bash
composer run test ./tests/oihana/files/OpenSSLFileEncryptionTest.php
```

La configuration vit dans `phpunit.xml` à la racine du projet.

## Générer la documentation phpDocumentor

La sortie HTML de référence (au niveau classe/fonction) se génère via [phpDocumentor](https://phpdoc.org) :

```bash
composer doc
```

Cette commande nettoie puis régénère `docs/` (sortie HTML). À ne pas confondre avec **ce wiki**, qui vit sous `wiki/` et est rédigé manuellement en Markdown FR/EN.

## Et la suite ?

- [Dépendances](dependencies.md) — détail du rôle de chaque package `oihana/*` et `devium/toml`.
- [Glossaire](glossary.md) — termes récurrents utilisés dans la documentation.
- [Introduction](introduction.md) — retour à la vue d'ensemble si tu arrives par cette page.
