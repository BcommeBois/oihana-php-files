# oihana-php-files

## Oihana PHP - Files library

Provides universal PHP utilities for handling filesystem operations and manipulating file and directory paths, independent of the platform in use.

## Installation and Usage

> **Requires [PHP 8.4+](https://php.net/releases/)**

This library using [Composer](https://getcomposer.org):

```bash
composer require oihana/php-files
```

## Unit tests

Run all unit tests.
```bash
composer run-script test
```

Run a unique unit test.
```bash
composer run test ./tests/oihana/files/OpenSSLFileEncryptionTest.php
```

## Licences
 * Licence MPL 2.0 : Mozilla Public License Version 2.0

## About
 * Author : Marc ALCARAZ (aka eKameleon)
 * Mail : marc@ooop.fr
 * Link : http://www.ooop.fr

## Documentation

Use PhpDocumentor to generates the documentation of the library in the ./docs directory.

### Usage

```bash
composer doc
```
or
```bash
./tools/phpDocumentor
```

### Installation

#### phive

The PHAR Installation and Verification Environment (PHIVE) - https://phar.io/

**1 - Install phive**
```bash
wget -O phive.phar https://phar.io/releases/phive.phar
wget -O phive.phar.asc https://phar.io/releases/phive.phar.asc
gpg --keyserver hkps://keys.openpgp.org --recv-keys 0x9D8A98B29B2D5D79
gpg --verify phive.phar.asc phive.phar
chmod +x phive.phar
sudo mv phive.phar /usr/local/bin/phive
```

**2 - Install PHP Documentor**
```bash
composer doc-install
```
or
```bash
phive install phpDocumentor --trust-gpg-keys 6DA3ACC4991FFAE5
```

