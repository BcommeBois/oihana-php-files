# Oihana PHP - Files

![Oihana PHP Files](https://raw.githubusercontent.com/BcommeBois/oihana-php-files/main/.phpdoc/template/assets/images/oihana-php-files-logo-inline-512x160.png)

## 📚 Documentation

Full project documentation is available at:  
👉 https://bcommebois.github.io/oihana-php-files

## 🚀 Features

- 📁 Cross-platform path and file utilities
- 🔐 File encryption and decryption with OpenSSL
- 🗜️ Tar and compressed `.tar.gz` archive creation and extraction
- 📂 Recursive file discovery with filters and options
- 🧪 Full unit test coverage

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

## 🧾 Licence

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
