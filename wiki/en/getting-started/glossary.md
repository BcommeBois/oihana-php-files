# Glossary

Recurring terms used throughout this documentation and in the `oihana/php-files` code. Alphabetical order.

## A

### Absolute (path)

A path that starts with a **root separator** (`/` on Unix), a **drive letter** (`C:\` on Windows), or a **scheme** (`phar://`, `file://`). Opposite: *relative*.

See [`isAbsolutePath`](../path/absolute-vs-relative.md) for detection, [`makeAbsolute`](../path/absolute-vs-relative.md) for conversion.

### Assertion

A function that **throws an exception** if a condition is not met. Convention in `oihana/php-files`: `assert` prefix (`assertFile`, `assertDirectory`, `assertWritableDirectory`, `assertPhar`, `assertTar`). No useful return value — the point is to guarantee the system state after the call.

```php
assertFile('/etc/hosts') ; // OK: does nothing if the file exists
assertFile('/foo')       ; // FileException thrown
```

### Autoload (`composer.autoload.files`)

Composer mechanism that **automatically includes** a PHP file at the start of each request (right after `vendor/autoload.php`). Used by `oihana/php-files` to make **standalone functions** (`joinPaths`, `findFiles`, etc.) available without requiring a `use function` or a manual `require` — you just call them with their fully-qualified namespace (or a `use` alias).

See [`composer.json`](../../composer.json) section `autoload.files`.

## C

### Canonical path

**Normalised** form of a path:

- separators are unified (`/` everywhere, even internally on Windows);
- `.` segments (current directory) are removed;
- `..` segments (parent directory) are resolved when possible;
- multiple slashes are collapsed (`//` → `/`).

⚠ Different from PHP's native `realpath()`, which **resolves symlinks** and **checks existence**. `canonicalizePath` is purely textual: it works on non-existent paths.

See [`canonicalizePath`](../path/joining-and-normalizing.md).

### Cipher (symmetric encryption)

Encryption algorithm used by `OpenSSLFileEncryption`. Default is **`aes-256-cbc`** (Advanced Encryption Standard, 256-bit key, Cipher Block Chaining mode). Any algorithm listed by `openssl_get_cipher_methods()` is accepted.

## E

### File extension

A file-name suffix preceded by a dot: `.txt`, `.tar.gz`, `.cose`, etc. The exhaustive catalogue lives in the `FileExtension` enum. For tar archives, see `TarExtension`.

Note: `.tar.gz` is a **compound extension** — `getFileExtension` returns `tar.gz` (not `gz`) when the appropriate option is passed.

## F

### Virtual filesystem (vfs)

A filesystem **simulated in memory**, with no real I/O on disk. Provided by `mikey179/vfsstream` (test-time dependency). Enables fast, deterministic tests with no cleanup. The library uses vfsstream extensively in its own tests.

## G

### Glob (pattern)

File pattern syntax based on **wildcards**: `*` (zero or more chars except `/`), `?` (one char), `[abc]` (one char from a set), `{a,b}` (alternatives).

Examples: `*.php`, `test_*.txt`, `src/**/*.md` (recursive in some implementations).

Distinct from **regex** (regular expression), which uses different syntax (`^`, `$`, `.+`, `\w`, etc.). `findFiles` auto-detects the format via `isRegexp` from `oihana/php-core`.

## H

### Hash / Hashing

A **non-reversible** transformation of a string into a fixed-size fingerprint. Distinct from encryption (which is reversible). `oihana/php-files` does not provide a hash helper — use the native `hash_file()` for that.

## I

### IV (Initialization Vector)

A block of **random bits** mixed with the first cleartext block during symmetric encryption in CBC (Cipher Block Chaining) mode. Guarantees that two encryptions of the same file with the same key produce different outputs.

`OpenSSLFileEncryption` **automatically prepends the IV** in the encrypted output file (16 bytes for AES). Decryption reads it back from the start of the file — the user never has to handle it.

## L

### Local (path)

A path pointing to the **machine's filesystem** (not a remote URL). Includes `/var/www`, `C:\Users`, `phar://`. **Excludes** `https://`, `ftp://`, `s3://`.

See [`isLocalPath`](../path/inspection.md).

## M

### MIME type (Multipurpose Internet Mail Extensions)

Standardised identifier of a file's **content type**: `image/jpeg`, `application/json`, `text/plain`, etc. Standardised by IANA.

`oihana/php-files` exposes a typed catalogue:

- `FileMimeType` — all types (web + specialised).
- `ImageMimeType` — images only.
- `AudioMimeType` — audio only.
- `VideoMimeType` — video only.

Runtime detection uses `ext-fileinfo` (`mime_content_type`).

## N

### Normalise (a path)

The act of **rewriting a path into a standard form**: convert separators to `/`, remove resolvable `.` and `..`, collapse multiple `//`. Partial synonym for *canonicalise*. See [`normalizePath`](../path/joining-and-normalizing.md).

## O

### Options (pattern)

Convention in `oihana/php-files` (and `oihana/*` more broadly) consisting in **passing a function's configuration as an associative array** whose keys are **enum constants**.

Example:

```php
findFiles( $dir, [
    FindFilesOption::RECURSIVE => true,
    FindFilesOption::PATTERN   => '*.php',
    FindFilesOption::ORDER     => Order::ASC,
]) ;
```

Preferred over: a constructor with 10 positional parameters, or a *fluent builder*.

For more structured configurations (hydrated, serialisable, formattable), see the abstract [`Options`](../options/README.md) class.

## P

### Phar (PHP Archive)

PHP-specific **executable archive** format. A `.phar` is a ZIP/TAR (your choice) with a PHP *stub* header that can be included with `require` or executed with `php archive.phar`. The `phar://` scheme lets you **access an internal file** without extraction (`phar:///app/bundle.phar/src/Main.php`).

`oihana/php-files` provides Phar helpers in [`phar/`](../phar/README.md) and relies on the native `PharData` class for tar archives.

## R

### Relative (path)

A path that does not start with `/`, a drive letter, or a scheme. Its resolution depends on the **current directory** (`getcwd()`) or an explicit **base directory**. See [`isRelativePath`](../path/absolute-vs-relative.md), [`makeRelative`](../path/absolute-vs-relative.md).

## S

### Scheme

Prefix of a path/URL identifying its **protocol** or PHP **wrapper**: `file://`, `phar://`, `http://`, `https://`, `s3://`, etc. `oihana/php-files` preserves the scheme across joining and normalisation operations.

See [`getSchemeAndHierarchy`](../files/system.md).

### Symlink (symbolic link)

Special Unix file that **points to another path**. On Windows, equivalent supported since Vista. The library exposes a `followLinks` option on `findFiles` and `recursiveFilePaths` to decide whether to traverse symlinks during a recursive walk.

⚠ Beware of **infinite loops** when `followLinks = true` on circular symlinks. PHP detects them with `SKIP_DOTS` but the memory cost grows.

## T

### Tar (Tape Archive)

Historical Unix archive format. Concatenates several files into one, with their metadata (size, perms, owner). **Not compressed by default** — hence the very common `tar.gz` (gzip) and `tar.bz2` (bzip2) variants.

`oihana/php-files` uses PHP's native `PharData` class to create/extract tars. See [`tar`](../archive/tar.md), [`untar`](../archive/untar.md).

### Temporary (file/directory)

Item created in the **system temporary directory** (`/tmp` on Unix, `%TEMP%` on Windows), reachable via `sys_get_temp_dir()`. `oihana/php-files` adds:

- `getTemporaryDirectory` — read the temp directory.
- `makeTemporaryDirectory` — create a unique temporary subdirectory.
- `deleteTemporaryDirectory` — safe deletion (verifies the path is under `sys_get_temp_dir()`).

### Timestamped (file/directory)

A file or directory whose name embeds a **date/time** in a configurable format: `backup-2026-05-26.tar.gz`, `log-20260526-153012.txt`, etc. Useful for backups and simple *rotation*. See `makeTimestampedFile`, `makeTimestampedDirectory`.

## V

### vfsStream

See [Virtual filesystem](#virtual-filesystem-vfs).

## What's next?

- [Introduction](introduction.md) — overview.
- [Installation](installation.md) — install the library.
- [Dependencies](dependencies.md) — packages pulled by Composer.
- [English TOC](../README.md) — full table of contents.
