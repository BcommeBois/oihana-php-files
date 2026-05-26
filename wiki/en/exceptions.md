# Exceptions

`oihana/php-files` defines **3 custom exceptions** in the `oihana\files\exceptions` namespace. All inherit directly from `\Exception` — no intermediate hierarchy.

| Class | When thrown |
|---|---|
| [`FileException`](#fileexception) | Error related to a **file**: failed assertion, write impossible, refused MIME, encrypt/decrypt failure. |
| [`DirectoryException`](#directoryexception) | Error related to a **directory**: failed assertion, create/delete failure, traversal failure. |
| [`UnsupportedCompressionException`](#unsupportedcompressionexception) | Unsupported **compression type** in tar / phar functions. |

> 💡 These exceptions do **not share** an intermediate parent class. To catch them all together, use `\Exception` or `\Throwable`.

---

## `FileException`

```php
namespace oihana\files\exceptions ;

class FileException extends \Exception { }
```

Thrown by all functions and classes touching an individual file.

### Throwing functions/classes

| Code | Case |
|---|---|
| [`assertFile`](files/assertions.md#assertfile) | Null/empty path, not a file, not readable, not writable, refused MIME. |
| [`assertWritableDirectory`](files/assertions.md#assertwritabledirectory) | No — this one throws `DirectoryException`. |
| [`makeFile`](files/creation.md#makefile) | Empty path, write failure, `chmod`/`chown`/`chgrp` failure. |
| [`makeTimestampedFile`](files/creation.md#maketimestampedfile) | `touch` failure on the generated path. |
| [`deleteFile`](files/deletion.md#deletefile) | `unlink` failure or assertions. |
| [`clearFile`](files/deletion.md#clearfile) | In `assertable: true` mode, via `assertFile`. |
| [`countFileLines`](files/reading.md#countfilelines), [`getFileLines`](files/reading.md#getfilelines), [`getFileLinesGenerator`](files/reading.md#getfilelinesgenerator) | `fopen` failure, or assertions. |
| [`getTimestampedFile`](files/system.md#gettimestampedfile) | Wraps internal errors. |
| [`validateMimeType`](files/mime.md#validatemimetype) | Undeterminable MIME, or outside the allowed list. |
| [`OpenSSLFileEncryption::encrypt`](openssl/README.md) / `decrypt` | Error wrapping (also throws `RuntimeException` for pure cryptographic failures). |
| [`tar`](archive/tar.md#tar) | One of `$paths` does not exist. |

### Typical handling

```php
use function oihana\files\assertFile;
use oihana\files\exceptions\FileException;

try {
    assertFile( '/upload/document.pdf' , [ 'application/pdf' ] ) ;
    // ... processing
}
catch ( FileException $e ) {
    http_response_code( 400 ) ;
    echo 'Invalid file: ' . $e->getMessage() ;
}
```

### Message inspection

Messages are **descriptive and include the path**:

- `'The file path must not be null.'`
- `'The file path must not be empty.'`
- `'The file path "/foo" is not a valid file.'`
- `'The file "/foo" is not readable.'`
- `'The file "/foo" is not writable.'`
- `'Invalid MIME type for file "/foo". Expected one of [...], but got "..."`.

> 💡 For i18n-able or categorised errors, consider a logging wrapper that catches + classifies via regex on the message.

---

## `DirectoryException`

```php
namespace oihana\files\exceptions ;

class DirectoryException extends \Exception { }
```

Thrown by all functions and classes touching a directory.

### Throwing functions/classes

| Code | Case |
|---|---|
| [`assertDirectory`](files/assertions.md#assertdirectory) | Null/empty path, not a directory, not readable, not writable, unexpected permissions. |
| [`assertWritableDirectory`](files/assertions.md#assertwritabledirectory) | Same, with forced `isWritable: true`. |
| [`makeDirectory`](files/creation.md#makedirectory) | Empty path, `mkdir` failure, non-writable, `chown`/`chgrp` failure. |
| [`makeTimestampedDirectory`](files/creation.md#maketimestampeddirectory) | Wraps internal errors. |
| [`makeTemporaryDirectory`](files/creation.md#maketemporarydirectory) | Temp directory creation failure. |
| [`deleteDirectory`](files/deletion.md#deletedirectory) | `rmdir`, inner `unlink`, or assertion failure. |
| [`getDirectory`](files/system.md#getdirectory) | If `assertable: true`, via `assertDirectory`. |
| [`getTemporaryDirectory`](files/system.md#gettemporarydirectory) | If `assertable: true`. |
| [`getTimestampedDirectory`](files/system.md#gettimestampeddirectory) | Wraps internal errors. |
| [`tar`](archive/tar.md#tar) | Internal temp directory creation failure. |
| [`OpenSSLFileEncryption::encrypt`](openssl/README.md) | Output directory not writable. |

### Message inspection

- `'The directory path must not be null.'`
- `'The directory path must not be empty.'`
- `'The path "/foo" is not a valid directory.'`
- `'The directory "/foo" is not readable.'`
- `'The directory "/foo" is not writable.'`
- `'The directory "/foo" has permissions "777", expected "755".'`
- `'Failed to create directory "/foo".'`
- `'Failed to remove directory "/foo".'`
- `'Failed to remove file "/foo".'` (from `deleteDirectory` when inner `unlink` fails)

### Typical handling

```php
use function oihana\files\makeDirectory;
use oihana\files\exceptions\DirectoryException;

try {
    $path = makeDirectory( '/var/log/myapp' , 0755 , true , 'www-data' , 'www-data' ) ;
}
catch ( DirectoryException $e ) {
    error_log( '[FATAL] Cannot provision logs directory: ' . $e->getMessage() ) ;
    exit( 1 ) ;
}
```

---

## `UnsupportedCompressionException`

```php
namespace oihana\files\exceptions ;

class UnsupportedCompressionException extends \Exception { }
```

Thrown when an **unrecognised compression type** is passed to an archive or Phar function.

### Throwing functions

| Code | Case |
|---|---|
| [`tar`](archive/tar.md#tar) | If `$compression` is not `gz`/`bz2`/`none` (e.g. `'xz'`, `'rar'`). |
| [`getPharCompressionType`](phar/README.md#getpharcompressiontype) | Same. |
| [`TarExtension::getExtensionForCompression`](enums.md#tarextension) | Same. |
| [`TarExtension::getCompressionExtension`](enums.md#tarextension) | Same. |

### Why this exception exists

`CompressionType` defines 8 constants (`NONE`, `GZIP`, `BZIP2`, `ZIP`, `LZ4`, `LZMA`, `XZ`, `ZSTD`), but only the **first 3** are supported by the implementation (`PharData`-based). This exception bridges the (broad) enum and the (restricted) effective implementation.

### Typical handling

```php
use function oihana\files\archive\tar\tar;
use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;

try {
    tar( '/var/log' , '/backups/logs.tar.xz' , CompressionType::XZ ) ;
}
catch ( UnsupportedCompressionException $e ) {
    // Fallback to gzip
    tar( '/var/log' , '/backups/logs.tar.gz' , CompressionType::GZIP ) ;
}
```

---

## Handling strategies

### 1. Fine catch by type

Ideal when you want to **react differently** by context:

```php
try {
    doSomeFileOps() ;
}
catch ( FileException $e ) {
    // "File" error: 400 or 404 by message
}
catch ( DirectoryException $e ) {
    // "Directory" error: 500 (server has a provisioning issue)
}
catch ( UnsupportedCompressionException $e ) {
    // Specific business case: fallback or clear message
}
```

### 2. Broad catch

For a wrapper that just wants to **log and abort**:

```php
try {
    doFileWork() ;
}
catch ( \Exception $e ) {
    // Covers the 3 oihana exceptions + everything else (RuntimeException, etc.)
    logger()->error( 'File operation failed' , [ 'exception' => $e ] ) ;
    throw $e ; // re-throw to avoid masking
}
```

### 3. `assertable: false` pattern instead of try/catch

For **destructive** operations where absence is not an error:

```php
use function oihana\files\deleteFile;

// Instead of try { deleteFile() } catch { ignore }
deleteFile( $maybeExisting , assertable: false ) ;
// → silent false if file does not exist
```

See [Deletion](files/deletion.md) for details.

### 4. Re-throw with context

`oihana/php-files` already uses this pattern (cf. `tar`, `getTimestampedFile`):

```php
try {
    // ... operation
}
catch ( \Throwable $original ) {
    throw new FileException(
        'X operation failed: ' . $original->getMessage() ,
        $original->getCode() ,
        $original , // ← preserves the chain via $previous
    ) ;
}
```

You can walk the chain with `$e->getPrevious()` on the caller side.

---

## Missing hierarchy: to know

The 3 exceptions all inherit **directly from `\Exception`** — there is **no** parent `OihanaFilesException` class. This means:

- ❌ You cannot do `catch ( OihanaFilesException $e )` to catch them all together (but only oihana-specific).
- ✅ You can catch them via `catch ( \Exception $e )` but this includes **everything** (not just oihana).
- ✅ You can catch them individually with a multi-catch (`catch ( FileException | DirectoryException $e )`).

It's a simplicity choice — possibly to revisit if the need emerges. See [Tips](tips.md).

## See also

- [Assertions](files/assertions.md) — main source of `FileException` and `DirectoryException`.
- [Creation](files/creation.md), [Deletion](files/deletion.md) — other sources.
- [Tar](archive/tar.md) — source of `UnsupportedCompressionException`.
- [Enums](enums.md) — `CompressionType`, `TarExtension`.
- [Tips](tips.md) — pitfalls and conventions.
- [English TOC](README.md).
