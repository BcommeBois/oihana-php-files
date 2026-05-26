# Extracting a tar archive

Five functions to extract and validate tar archives.

- [`untar`](#untar) — extraction (with `dryRun`, `keepPermissions`, `overwrite`).
- [`assertTar`](#asserttar) — combined validation (extension + MIME + structure).
- [`hasTarExtension`](#hastarextension) — fast extension-based check.
- [`hasTarMimeType`](#hastarmimetype) — MIME type check via `finfo`.
- [`validateTarStructure`](#validatetarstructure) — parse + iteration of first entries.

---

## `untar`

```php
untar(
    string $tarFile ,
    string $outputPath ,
    array  $options = []
) : true|array
```

Extracts a tar archive (compressed or not) into an output directory.

### Options

| Key (string or enum) | Type | Default | Effect |
|---|---|---|---|
| `'dryRun'` / `TarOption::DRY_RUN` | `bool` | `false` | Extracts nothing — returns **the list of relative paths** that would be extracted. |
| `'overwrite'` / `TarOption::OVERWRITE` | `bool` | `true` | If `false`, throws `RuntimeException` at the first existing file. |
| `'keepPermissions'` / `TarOption::KEEP_PERMISSIONS` | `bool` | `false` | Restores original permissions via [`preservePharFilePermissions`](../phar/README.md). |
| `'maxExtractedSize'` / `TarOption::MAX_EXTRACTED_SIZE` | `int\|null` | `null` | If set, caps the total uncompressed size (in bytes). Throws `RuntimeException` **before** any write if exceeded. See [Decompression-bomb protection](#decompression-bomb-protection). |

### Returns

- `true` on successful extraction;
- `string[]` (relative paths) in `dryRun` mode.

### Exceptions

- **`FileException`**: `$tarFile` invalid or inaccessible (via `assertTar`).
- **`DirectoryException`**: cannot create `$outputPath`.
- **`RuntimeException`**:
  - **path traversal detected** in an entry (`..` in path);
  - overwrite attempt with `overwrite: false`;
  - **total uncompressed size exceeds `maxExtractedSize`** (decompression bomb);
  - other extraction error.

### Internal pipeline

1. `assertTar( $tarFile )` — validation.
2. `makeDirectory( $outputPath )` — creates the output directory if needed.
3. If the archive is compressed → `decompress()` to a temporary `.tar`.
4. If `overwrite: false` OR `dryRun: true` OR `maxExtractedSize !== null` → first walk to detect `..`, conflicts **and accumulate uncompressed size**.
5. If `dryRun: true` → returns the list.
6. Otherwise → `extractTo( $outputPath )`.
7. If `keepPermissions` → permission restoration.
8. Cleanup of the temporary `.tar` if decompression was performed.

### Path-traversal protection

`untar` walks the archive entry names **before** extraction and throws `RuntimeException` if an entry contains `..`:

```php
// Forged archive with entry "../../etc/passwd"
untar( '/uploads/malicious.tar' , '/var/www/uploads' ) ;
// → RuntimeException: Path traversal attempt detected in tar file: ../../etc/passwd
```

⚠ **This protection only activates if `overwrite: false` OR `dryRun: true`** — that is, in the pre-scan phase. In `overwrite: true` mode (default), it depends on `PharData::extractTo`'s behaviour (which in theory also applies protections, but less explicitly).

> 💡 **Security recommendation**: to extract an archive from an external source (user upload, download), **always** use `dryRun: true` first to validate, then extract with `overwrite: false`. Cost: double walk, but maximum safety.

### Decompression-bomb protection

A **decompression bomb** is an archive of a few kilobytes that expands to several gigabytes — it can saturate disk space, RAM, or cause a denial of service. The `maxExtractedSize` option caps the total uncompressed size accepted:

```php
use function oihana\files\archive\tar\untar;
use oihana\files\enums\TarOption;

// Reject any archive whose entries cumulatively exceed 100 MiB.
untar( $uploadedArchive , $extractDir , [
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 ,
]) ;
// → RuntimeException: untar() aborted: extracted size exceeds maximum 104857600 bytes (potential decompression bomb).
```

**How it works.** When `maxExtractedSize` is set, `untar()` forces a **pre-scan** of the archive before any write, accumulates the uncompressed size of each entry, and throws `RuntimeException` as soon as the cumulative size exceeds the limit. **No file is written** to `$outputPath` when the limit is exceeded.

**Key points.**

- The check is **opt-in**: `null` (default) preserves the historical unbounded behaviour — **backward compatible**.
- The cap applies to the **sum** of the uncompressed sizes of all entries (not per file).
- Enabling this option triggers an additional walk of the archive — small overhead, but systematic protection.
- The protection also fires in `dryRun` mode (exception raised during pre-scan).
- Composable with `overwrite: false` and the path-traversal check — all checks happen in the same pass.

> 💡 **Recommendation**: for any externally-sourced archive (upload, download), set a sensible value based on the disk/RAM budget you are willing to allocate server-side (typically a few hundred MiB).

### Examples

```php
use function oihana\files\archive\tar\untar;
use oihana\files\enums\TarOption;

// 1. Basic extraction
untar( '/path/to/archive.tar' , '/output/dir' ) ;

// 2. With options
untar( '/path/to/archive.tar.gz' , '/output/dir' , [
    TarOption::OVERWRITE        => false ,
    TarOption::KEEP_PERMISSIONS => true ,
]) ;

// 3. Dry-run: preview content without extracting
$files = untar( '/path/to/archive.tar' , '/output/dir' , [
    TarOption::DRY_RUN => true ,
]) ;
print_r( $files ) ;
// ['file1.txt', 'subdir/file2.php', ...]

// 4. Safe workflow for user upload
$preview = untar( $uploadedArchive , $extractDir , [
    TarOption::DRY_RUN            => true ,
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // reject > 100 MiB
]) ;

if ( count( $preview ) > 10_000 ) {
    throw new \RuntimeException( "Archive too large" ) ;
}

untar( $uploadedArchive , $extractDir , [
    TarOption::OVERWRITE          => false ,             // refuse to overwrite existing files
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // re-checks at pre-scan
]) ;
```

---

## `assertTar`

```php
assertTar( string $filePath , bool $strictMode = false ) : bool
```

**⚠ Misleading name warning**: unlike other `assert*` functions in the `oihana\files` namespace, this one **returns a `bool`** and does not systematically throw.

**Throws `FileException`** only if the file does not exist (via `assertFile`).

### Validation logic

1. **`hasTarExtension`** — recognised extension? Otherwise → `false`.
2. **`hasTarMimeType`** — tar MIME? Otherwise → `false`.
3. **Strict mode** (`$strictMode: true`) — `validateTarStructure` (PharData parse + iterate up to 10 entries).

```php
use function oihana\files\archive\tar\assertTar;

// Quick validation (extension + MIME)
assertTar( '/archives/sample.tar' ) ;
// → true

// Deep validation (with PharData parse)
assertTar( '/archives/sample.tar' , strictMode: true ) ;
// → true if structurally valid

// Non-existent file
assertTar( '/path/missing.tar' ) ;
// → FileException
```

> 💡 For a quick name-only check without reading the file, prefer [`hasTarExtension`](#hastarextension) or [`tarIsCompressed`](tar.md#tariscompressed).

---

## `hasTarExtension`

```php
hasTarExtension(
    string $filePath ,
    array  $tarExtensions = [
        FileExtension::TAR ,      // '.tar'
        FileExtension::TGZ ,      // '.tgz'
        FileExtension::GZ ,       // '.gz'
        FileExtension::TAR_GZ ,   // '.tar.gz'
        FileExtension::TAR_BZ2 ,  // '.tar.bz2'
        FileExtension::BZ2 ,      // '.bz2'
    ]
) : bool
```

**Fast, purely textual** check: recognises simple extensions (`.tar`, `.gz`) and **compound** ones (`.tar.gz`, `.tar.bz2`).

```php
use function oihana\files\archive\tar\hasTarExtension;

hasTarExtension( '/path/archive.tar'      ) ; // true
hasTarExtension( '/path/archive.tar.gz'   ) ; // true
hasTarExtension( '/path/archive.tgz'      ) ; // true
hasTarExtension( '/path/archive.tar.bz2'  ) ; // true
hasTarExtension( '/path/archive.zip'      ) ; // false
hasTarExtension( '/path/README.md'        ) ; // false
```

**Custom list**:

```php
hasTarExtension( '/path/file.dat' , [ '.dat' , '.bin' ] ) ;
// → true (reuses the same simple + compound extension mechanism)
```

> ⚠ The bare `.gz` and `.bz2` extensions are included by default, which may surprise — `file.gz` (non-tar) is recognised. Adjust the list for strict tar matching.

---

## `hasTarMimeType`

```php
hasTarMimeType(
    string $filePath ,
    array  $mimeTypes = [
        'application/x-tar' ,
        'application/tar' ,
        'application/gzip' ,
        'application/x-gzip' ,
        'application/x-bzip2' ,
        'application/bzip2' ,
        'application/x-compressed-tar' ,
    ]
) : bool
```

**MIME-based** check via `finfo` (reads first bytes of the file).

**Matches if the detected MIME contains** one of the listed strings (`str_contains`) — accepts MIMEs with `; charset=...`.

```php
use function oihana\files\archive\tar\hasTarMimeType;

hasTarMimeType( '/archives/file.tar.gz' ) ;
// → true (MIME: application/gzip)

hasTarMimeType( '/archives/file.tar' ) ;
// → true (MIME: application/x-tar)

hasTarMimeType( '/archives/missing.tar' ) ;
// → false (file does not exist)

// Custom list
hasTarMimeType( '/path/file.tar' , [ 'application/x-tar' , 'application/x-custom-tar' ] ) ;
```

> 💡 More reliable than `hasTarExtension` for maliciously renamed files, but slower (reads the file).

---

## `validateTarStructure`

```php
validateTarStructure( string $filePath ) : bool
```

Validates the **internal structure** of an **uncompressed** tar by attempting to open it via `PharData` and iterating on **at most 10 first entries** (perf limit).

```php
use function oihana\files\archive\tar\validateTarStructure;

validateTarStructure( '/path/to/archive.tar'     ) ; // true or false
validateTarStructure( '/path/to/invalid.tar'     ) ; // false (parse error)
validateTarStructure( '/path/to/archive.tar.gz'  ) ; // false ⚠ NOT SUPPORTED
validateTarStructure( '/path/to/not_a_tar.txt'   ) ; // false
validateTarStructure( '/nonexistent/file.tar'    ) ; // false (missing file)
```

### ⚠ Limitations

- **Does not support compressed tars** (`.tar.gz`, `.tar.bz2`) — `PharData` requires a raw `.tar` for this operation. `assertTar` strict mode calls this function **after** any decompression.
- **Truncated to 10 entries** — a tar valid in the first 10 entries but corrupted further still returns `true`. Perf/reliability trade-off.

### Validation function choice: matrix

| Level | Speed | Function | Checks |
|---|---|---|---|
| 1 (very fast) | µs | `tarIsCompressed` | Extension only (`.tar.gz`, etc.) |
| 2 (fast) | µs | `hasTarExtension` | Recognised extension (tar/tgz/gz/bz2). |
| 3 (moderate) | ms | `hasTarMimeType` | MIME via `finfo` (reads file start). |
| 4 (slow) | ms+ | `validateTarStructure` | PharData parse + iteration (uncompressed). |
| 5 (combined) | ms+ | `assertTar` (strict) | 1 + 2 + 3 + 4. |

---

## See also

- [Creating an archive](tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, `tarIsCompressed`.
- [Overview](README.md).
- [Enums](../enums.md) — `TarExtension`, `TarOption`, `FileExtension`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — `preservePharFilePermissions` used by `keepPermissions`.
