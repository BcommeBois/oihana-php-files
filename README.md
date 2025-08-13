# Oihana PHP - Files

![Oihana PHP Files](https://raw.githubusercontent.com/BcommeBois/oihana-php-files/main/.phpdoc/template/assets/images/oihana-php-files-logo-inline-512x160.png)

A versatile PHP library for seamless and portable file and path handling.

[![Latest Version](https://img.shields.io/packagist/v/oihana/php-files.svg?style=flat-square)](https://packagist.org/packages/oihana/php-files)  
[![Total Downloads](https://img.shields.io/packagist/dt/oihana/php-files.svg?style=flat-square)](https://packagist.org/packages/oihana/php-files)  
[![License](https://img.shields.io/packagist/l/oihana/php-files.svg?style=flat-square)](LICENSE)

## 📚 Documentation

Full project documentation is available at:  
👉 https://bcommebois.github.io/oihana-php-files

## 🚀 Features

- 📁 Cross-platform path and file utilities — Normalize, join, and manipulate file paths with ease.
- 🔐 File encryption and decryption powered by OpenSSL.
- 🗜️ Create, compress and extract archives (.tar and .tar.gz).
- 📂 Recursive file discovery with advanced filters and options.
- 🧪 Full unit test coverage ensuring reliability and maintainability.

💡 Designed to be lightweight, testable, and compatible with any PHP 8.4+ project.

## 📦 Installation

> **Requires [PHP 8.4+](https://php.net/releases/)**  

Install via [Composer](https://getcomposer.org):
```bash
composer require oihana/php-files
```

## ✅ Running Unit Tests

To run all tests:
```bash
composer run-script test
```

To run a specific test file:
```bash
composer run test ./tests/oihana/files/OpenSSLFileEncryptionTest.php
```

## 🧾 License

This project is licensed under the [Mozilla Public License 2.0 (MPL-2.0)](https://www.mozilla.org/en-US/MPL/2.0/).

## 👤 About the author

* Author : Marc ALCARAZ (aka eKameleon)
* Mail : marc@ooop.fr
* Website : http://www.ooop.fr

## 🛠️ Generate the Documentation

We use [phpDocumentor](https://phpdoc.org/) to generate the documentation into the ./docs folder.

### Usage
Run the command : 
```bash
composer doc
```

## 🔗 Related packages

- `oihana/php-core` – core helpers and utilities used by this library: `https://github.com/BcommeBois/oihana-php-core`
- `oihana/php-reflect` – reflection and hydration utilities: `https://github.com/BcommeBois/oihana-php-reflect`
- `oihana/php-enums` – a collection of strongly-typed constant enumerations for PHP.: `https://github.com/BcommeBois/oihana-php-enums`
