# Phar — `oihana\files\phar`

Four helpers around PHP's native **`PharData`** class, mainly used internally by the [archive/tar](../archive/README.md) module but reusable standalone.

- [`assertPhar`](#assertphar) — verifies the `phar` extension is loaded.
- [`getPharBasePath`](#getpharbasepath) — builds a `phar://...` URI to access an archive's internal content.
- [`getPharCompressionType`](#getpharcompressiontype) — converts `CompressionType::*` → `Phar::*` constant.
- [`preservePharFilePermissions`](#preservepharfilepermissions) — restores original permissions after extraction.

> 💡 All helpers live in the `oihana\files\phar` namespace and are autoloaded via `composer.autoload.files`.

## Why a dedicated module?

PHP provides `PharData` (native class, `ext-phar` extension) to read and write `.phar`, `.tar`, `.tar.gz`, `.tar.bz2`, `.zip` archives. But the native API has ergonomic gaps:

- no helper to map the **string** compression name (`'gzip'`, `'bz2'`) to the **constant** `Phar::GZ` / `Phar::BZ2`;
- no function to build the `phar://...` URI from an instance;
- no **safeguard** to verify extension availability before using it;
- permissions stored in the archive are not restored automatically by `extractTo`.

This module fills those gaps.

---

## `assertPhar`

```php
assertPhar() : void
```

**Safeguard** to call before any `PharData` operation. Checks two things:

1. The `PharData` class exists (`class_exists`).
2. The `phar` extension is loaded (`extension_loaded`).

**Throws `RuntimeException`** if either check fails.

```php
use function oihana\files\phar\assertPhar;

try {
    assertPhar() ;
    $phar = new \PharData('/path/to/archive.tar') ;
    // ... Phar operations
}
catch ( \RuntimeException $e ) {
    echo "Phar support unavailable: " . $e->getMessage() ;
}
```

> 💡 In practice, on most PHP distributions (Debian/Ubuntu/Mac brew/Windows), `ext-phar` is compiled in by default and always present. `assertPhar` is useful for minimalist environments (PHP built-from-source with `--disable-phar`, custom Docker containers).

---

## `getPharBasePath`

```php
getPharBasePath( PharData $phar ) : string
```

Returns the **`phar://` URI** pointing to the archive root — used to access internal files via PHP *stream wrappers*.

```php
use function oihana\files\phar\getPharBasePath;

$phar = new \PharData('/absolute/path/to/archive.tar') ;
$baseUri = getPharBasePath( $phar ) ;
// → 'phar:///absolute/path/to/archive.tar'

// Read an internal file without extraction
$content = file_get_contents( $baseUri . '/docs/readme.txt' ) ;

// List
$files = scandir( $baseUri ) ;
```

**Detail:** the function uses `realpath()` on the archive path to guarantee an absolute URI even if you opened the `PharData` with a relative path.

Used internally by [`untar`](../archive/untar.md#untar) to traverse the archive without decompressing to disk.

---

## `getPharCompressionType`

```php
getPharCompressionType( string $compression ) : int
```

Converts a **string** (`CompressionType::*`) to a **numeric** `Phar::*` constant. Handy to call `PharData::compress($pharConstant)` from a user option stored as a string.

### Mapping

| Input                       | Output       |
|-----------------------------|--------------|
| `CompressionType::GZIP`     | `Phar::GZ`   |
| `CompressionType::BZIP2`    | `Phar::BZ2`  |
| `CompressionType::NONE`     | `Phar::NONE` |
| any other value             | `UnsupportedCompressionException` |

```php
use function oihana\files\phar\getPharCompressionType;
use oihana\files\enums\CompressionType;

$compression = CompressionType::GZIP ;
$pharConstant = getPharCompressionType( $compression ) ;
// → 4096 (numeric value of Phar::GZ)

$phar = new \PharData('/path/to/archive.tar') ;
$phar->compress( $pharConstant ) ;

// Invalid value → exception
getPharCompressionType( 'rar' ) ;
// → UnsupportedCompressionException: Compression type 'rar' is not supported
```

Used internally by [`tar`](../archive/tar.md#tar) to go from string type to the Phar constant expected by the native API.

---

## `preservePharFilePermissions`

```php
preservePharFilePermissions( PharData $phar , string $outputPath ) : void
```

**Restores the original permissions** (`chmod` mode) of the files inside an archive, **after** it has been extracted via `extractTo`.

`PharData::extractTo` extracts the files but applies the process's **default** permissions (umask) — not those stored in the archive. This helper re-applies the proper modes on the extracted files.

### Behaviour

- Iterates over archive files.
- For each file present in `$outputPath` as `basename(file)`, applies `chmod($filePath, $file->getPerms())`.
- On error, **logs a warning** via `error_log()` and continues (no blocking exception).

```php
use function oihana\files\phar\{ assertPhar , preservePharFilePermissions } ;

assertPhar() ;

$phar = new \PharData('/archives/app.tar') ;
$phar->extractTo('/var/www/app' , null , true ) ;

preservePharFilePermissions( $phar , '/var/www/app' ) ;
// Restores modes (e.g. 0755 for executable binaries)
```

### ⚠ Limitations

- **Basename comparison**, not full relative path — if the archive contains two files with the same name in different subfolders, behaviour is unpredictable. Rare in practice but worth knowing.
- **No owner/group restoration** — only the mode (`chmod`). For owner/group, use `chown`/`chgrp` separately (cf. [`getOwnershipInfos`](../files/system.md#getownershipinfos)).

Used internally by [`untar`](../archive/untar.md#untar) with the `keepPermissions: true` option.

---

## When to use this module directly?

95% of the time you **do not need** to touch the `phar/` namespace — the high-level functions ([`tar`](../archive/tar.md), [`untar`](../archive/untar.md), [`tarFileInfo`](../archive/tar.md#tarfileinfo)) orchestrate everything.

Use the helpers directly if:

- you work with **raw `PharData`** for a case not covered by the `archive/tar` module (e.g. `.zip` archives, fine-grained handling of an executable Phar);
- you want to **read an internal file without extraction** → `getPharBasePath` + `file_get_contents`;
- you build your own extraction loop and want to **manually restore** permissions.

## See also

- [Archive (tar)](../archive/README.md) — the module that consumes these helpers in practice.
- [`untar`](../archive/untar.md#untar) — uses `preservePharFilePermissions` with the `keepPermissions` option.
- [Enums](../enums.md) — `CompressionType`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`.
- [English TOC](../README.md).
