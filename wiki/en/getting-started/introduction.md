# Introduction

## What `oihana/php-files` does

`oihana/php-files` is a **PHP 8.4+ toolkit** that consolidates into a single package the operations that PHP traditionally scatters across native functions: **path** manipulation, **file and directory** operations, **tar archiving** (with gzip/bzip2), **Phar** inspection, **OpenSSL file encryption**, **TOML configuration** loading, and an exhaustive catalogue of **MIME types** and **extensions**.

The code defines *no monolithic class*: it is organised as a collection of **~70 standalone functions**, each in its own file, autoloaded via `composer.autoload.files`. On top of that, a handful of **purpose-built classes** (encryption, hydratable options) and roughly **twenty strongly-typed enums** complete the picture.

## The *oihana* philosophy

Five principles run through the whole library — and more broadly through the `oihana/*` ecosystem:

1. **Composable functions, no heavy framework.** Every utility is an autoload-friendly PHP function. You compose `joinPaths()` with `canonicalizePath()` and `makeAbsolute()` instead of instantiating a `PathBuilder` and chaining its methods. The learning curve is flat: if you can read a function signature, you can use the library.

2. **Zero *magic strings*.** Configuration options (`'recursive'`, `'mode'`, `'pattern'`...) are exposed as enum constants (`FindFilesOption::RECURSIVE`, `FindFilesOption::MODE`, etc.). MIME types (`'image/jpeg'`, `'application/cbor'`) are constants of `FileMimeType`. Extensions (`.tar.gz`, `.cose`) live in `FileExtension`. Direct consequence: renames are *refactor-friendly*, IDE autocomplete works, and a typo is caught instantly.

3. **Explicit validation through assertions.** The functions `assertFile`, `assertDirectory`, `assertWritableDirectory`, `assertPhar`, `assertTar` throw typed exceptions (`FileException`, `DirectoryException`) with descriptive messages. Instead of chaining `if ( !is_file($path) ) throw ...`, you write `assertFile($path)` once and the filesystem state is guaranteed downstream.

4. **Comprehensive unit tests.** All code is covered by PHPUnit 12, with extensive use of `mikey179/vfsstream` to simulate the filesystem without touching the disk. The library is **reliable** and **usable as a dependency in your own tests**.

5. ***Cross-platform* by default.** Path normalisation handles Unix, Windows, URL and `phar://`. Helpers `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot` let you write portable code without sprinkling `DIRECTORY_SEPARATOR` everywhere.

## Why this library

PHP has historically offered many native functions for files (`is_file`, `is_dir`, `glob`, `realpath`, `pathinfo`, `mime_content_type`, `tempnam`, etc.), but those functions:

- have **inconsistent return conventions** (`false` vs exception, *string* vs *array*, occasional `null`);
- do **not cover higher-level semantics** (joining two paths while preserving the *scheme*, normalising `..` cleanly, listing recursively with filters, creating a timestamped file, encrypting with an embedded IV);
- are **untyped** (no way to catch a specific exception — you must test for `false` or inspect error messages).

`oihana/php-files` fills those gaps:

- **A uniform API**: every function returns a clear type, throws typed exceptions on failure, and accepts its options as an associative array documented with `@param array{...}` (PHPStan / Psalm-friendly annotations).
- **High-level operations** ready to use: `findFiles` with glob/regex/callback filters, `copyFilteredFiles` with exclusion lists, `makeTimestampedFile` for *backups*, `tarDirectory` with compression.
- **A shared MIME / extension catalogue**: `FileMimeType` covers standard web formats plus specialised ones (`application/cbor`, `application/cose`, `application/cose.enc`), and stays a single point to update.

## Audience and prerequisites

This documentation assumes the reader:

- masters **PHP 8.4+** — systematic use of *enums*, *readonly properties*, *first-class callable syntax* and *named arguments* is central;
- is comfortable with **Composer** and its `autoload.files` mechanism;
- has basic knowledge of the PHP extensions `fileinfo`, `openssl` and `phar` (all enabled by default in most distributions).

No prior knowledge of other `oihana/*` libraries is required. However, `oihana/php-files` reuses **constants** (`Char`, `Order`) and **functional helpers** (`oihana\core\arrays\deepMerge`, `oihana\core\strings\isRegexp`, `oihana\reflect\helpers\getFunctionInfo`) provided by:

- [`oihana/php-core`](https://github.com/BcommeBois/oihana-php-core) — foundational utilities.
- [`oihana/php-reflect`](https://github.com/BcommeBois/oihana-php-reflect) — reflection and hydration.
- [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) — shared enums.

Those dependencies are declared in `composer.json` and pulled in automatically — see [Dependencies](dependencies.md) for the details.

## Code structure

The code lives under [`src/oihana/`](../../../src/oihana/) under two root namespaces:

```
src/oihana/
├── files/                  ← namespace oihana\files
│   ├── *.php               ← ~45 root functions (assertFile, findFiles, makeDirectory, ...)
│   ├── archive/tar/        ← 9 tar functions (tar, untar, tarDirectory, ...)
│   ├── path/               ← 14 path functions (joinPaths, normalizePath, ...)
│   ├── phar/               ← 4 Phar functions
│   ├── openssl/            ← OpenSSLFileEncryption class
│   ├── toml/               ← resolveTomlConfig
│   ├── images/             ← getImageMimeType
│   ├── enums/              ← 18 enums + 3 MIME traits
│   ├── exceptions/         ← DirectoryException, FileException, UnsupportedCompressionException
│   └── options/            ← MakeFileOptions, OwnershipInfos (concrete options objects)
└── options/                ← namespace oihana\options
    ├── Options.php         ← abstract hydratable + serialisable + CLI-format class
    └── Option.php          ← associated contract
```

The subfolder layout mirrors the wiki table of contents: each functional sub-domain corresponds to a section in the documentation.

## What's next?

- [Installation](installation.md) — install the library, verify prerequisites, the `composer require` one-liner.
- [Dependencies](dependencies.md) — the role of `oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums`, `devium/toml`.
- [Glossary](glossary.md) — recurring terms (*canonical path*, *scheme*, IV, *MIME type*, Phar, etc.).
- [Paths overview](../path/README.md) — first functional sub-domain.

For the full index, back to the [English TOC](../README.md).
