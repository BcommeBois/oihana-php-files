# Installation

## Prerequisites

### PHP version

`oihana/php-files` requires **PHP 8.4 or higher**. The library leans on modern language features:

- **Enums** (PHP 8.1+) — `FileMimeType`, `FindFilesOption`, etc.
- **Readonly properties** (PHP 8.1+) — immutable properties in some option classes.
- **First-class callable syntax** (PHP 8.1+) — passing functions as values.
- **Asymmetric visibility and property hooks** (PHP 8.4+) — used by `oihana/php-core` and `oihana/php-reflect` (transitive dependencies).

Check:

```bash
php -v
# PHP 8.4.x (cli) (built: ...)
```

If your version is older, upgrade PHP through your package manager (`brew install php@8.4`, `apt install php8.4`, etc.).

### Required PHP extensions

Declared in `composer.json`:

| Extension | Role in `oihana/php-files` |
|---|---|
| `ext-fileinfo` | MIME type detection (`mime_content_type`, `finfo_*`) — used by `validateMimeType`, `getImageMimeType`. |
| `ext-openssl` | File encryption/decryption (`OpenSSLFileEncryption`). |

**Recommended** extensions (without them, some functions fail silently or are not available):

| Extension | Role |
|---|---|
| `ext-phar` | `tar`, `tar.gz`, `tar.bz2` archives (`tar`, `untar`, `tarDirectory`). Enabled by default in most distributions. |
| `ext-zlib` | `gzip` compression for tar archives (required for tests). |
| `ext-posix` | Owner/group information (`getOwnershipInfos`) — required for tests on Unix. |

Check:

```bash
php -m | grep -iE 'fileinfo|openssl|phar|zlib|posix'
```

## Composer installation

> Requires [Composer](https://getcomposer.org/) ≥ 2.0.

```bash
composer require oihana/php-files
```

This command automatically pulls in `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums` and `devium/toml` (see [Dependencies](dependencies.md)).

### Development install

To contribute or run the test suite locally:

```bash
git clone https://github.com/BcommeBois/oihana-php-files.git
cd oihana-php-files
composer install
```

## Post-installation verification

### Load any function at random

Create a `test.php` file at the root of your project:

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

If the output matches, the `composer.autoload.files` autoload is working and the library is operational.

### Run the test suite (dev install only)

`oihana/php-files` is covered by [PHPUnit 12](https://phpunit.de/). The whole suite runs through the Composer script:

```bash
composer run-script test
# or shorter:
composer test
```

To run a specific test file:

```bash
composer run test ./tests/oihana/files/OpenSSLFileEncryptionTest.php
```

Configuration lives in `phpunit.xml` at the project root.

## Generate the phpDocumentor reference

The reference HTML output (class/function level) is generated through [phpDocumentor](https://phpdoc.org):

```bash
composer doc
```

This command cleans then regenerates `docs/` (HTML output). Not to be confused with **this wiki**, which lives under `wiki/` and is hand-written Markdown in FR/EN.

## What's next?

- [Dependencies](dependencies.md) — what each `oihana/*` and `devium/toml` package provides.
- [Glossary](glossary.md) — recurring terms used throughout the documentation.
- [Introduction](introduction.md) — back to the overview if you landed here directly.
