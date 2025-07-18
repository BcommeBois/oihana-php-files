# oihana-php-files

## Oihana PHP - Files library

Provides universal PHP utilities for handling filesystem operations and manipulating file and directory paths, independent of the platform in use.

## Documentation

Read the full documentation of the project on : 
- https://bcommebois.github.io/oihana-php-files/

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


### Generates and update the documentation

We use PhpDocumentor to generates the documentation of the library in the ./docs directory.

### Usage
Run the command : 
```bash
composer doc
```
