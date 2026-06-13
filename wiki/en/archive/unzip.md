# Extracting a zip archive

Five functions to extract and validate zip archives.

- [`unzip`](#unzip) — extraction (with `dryRun`, `overwrite`, `maxEntries`, `maxSize`, `keepPermissions`).
- [`assertZip`](#assertzip) — combined validation (extension + MIME + structure).
- [`hasZipExtension`](#haszipextension) — fast extension-based check.
- [`hasZipMimeType`](#haszipmimetype) — MIME type check via `finfo`.
- [`validateZipStructure`](#validatezipstructure) — open + inspection of first entries.

---

## `unzip`

```php
unzip(
    string $zipFile ,
    string $outputPath ,
    array  $options = []
) : true|array
```

Extracts a zip archive into an output directory. Mirror of [`untar`](untar.md#untar), with **security safeguards** (Zip Slip, decompression bomb) and a **typed-exception** policy.

### Options

| Key (string or enum) | Type | Default | Effect |
|---|---|---|---|
| `'dryRun'` / `ZipOption::DRY_RUN` | `bool` | `false` | Extracts nothing — returns **the list of file entries** that would be extracted (directory entries excluded). |
| `'overwrite'` / `ZipOption::OVERWRITE` | `bool` | `true` | If `false`, throws `FileException` at the first already-existing file. |
| `'maxEntries'` / `ZipOption::MAX_ENTRIES` | `int\|null` | `null` | If set, rejects an archive that declares more entries than this limit (anti-bomb). |
| `'maxSize'` / `ZipOption::MAX_SIZE` | `int\|null` | `null` | If set, caps the total uncompressed size (in bytes). Throws `FileException` **before** any write if exceeded. |
| `'keepPermissions'` / `ZipOption::KEEP_PERMISSIONS` | `bool` | `false` | Restores the Unix permissions stored in each entry (`OPSYS_UNIX` external attributes) via `chmod`. |

### Returns

- `true` on successful extraction;
- `string[]` (file entries) in `dryRun` mode.

### Exceptions

> **Design note.** Unlike `untar()`, which wraps every error in `RuntimeException`, `unzip()` throws **typed exceptions**: `ZipArchive` reports its errors via return value (rather than by throwing), so there is nothing to "catch". Typed exceptions are more actionable and stay close to the original `extractZip` code.

- **`FileException`**:
  - `$zipFile` missing or unreadable (via `assertZip`);
  - archive impossible to open (corrupted);
  - **too many entries** (`maxEntries` exceeded);
  - **total uncompressed size greater than `maxSize`** (decompression bomb);
  - **Zip Slip**: an entry escapes the destination directory;
  - overwrite attempt with `overwrite: false`.
- **`DirectoryException`**: cannot create `$outputPath` (or the parent directory of an entry).

### Internal pipeline

1. `assertZip( $zipFile )` — guarantees the file exists (throws `FileException` if missing).
2. `makeDirectory( $outputPath )` — creates the output directory.
3. `ZipArchive::open()` — actual opening (failure → `FileException`).
4. Pre-scan **`maxEntries`** then **`maxSize`** (sum of uncompressed sizes) — **before** any write.
5. For each entry: **Zip Slip** check, then (outside `dryRun`) directory creation or file write, and optional permission restoration.
6. Returns the list (`dryRun` mode) or `true`.

### Zip Slip protection

A forged archive can contain an entry whose name climbs out of the target directory (`../../etc/passwd`). `unzip` canonicalises each entry's target and compares it to the destination root via [`isBasePath`](../path/README.md):

```php
// Forged archive with an entry "../evil.txt"
unzip( '/uploads/malicious.zip' , '/var/www/uploads' ) ;
// → FileException: Zip Slip detected: the entry "../evil.txt" escapes the destination directory.
```

⚠ This check runs **for each entry**, in normal mode **as well as** in `dryRun` — the protection is therefore systematic.

### Decompression-bomb protection

A **decompression bomb** is an archive of a few kilobytes that expands to several gigabytes. Two complementary safeguards, both checked **before** any write:

```php
use function oihana\files\archive\zip\unzip;
use oihana\files\enums\ZipOption;

unzip( $uploadedArchive , $extractDir , [
    ZipOption::MAX_ENTRIES => 10_000 ,             // reject > 10,000 entries
    ZipOption::MAX_SIZE    => 100 * 1024 * 1024 ,  // reject > 100 MiB uncompressed
]) ;
// → FileException if either limit is crossed; no file written.
```

- `maxEntries` compares `numFiles` to the limit (negligible cost).
- `maxSize` sums the uncompressed sizes of all entries (`statIndex`) then compares to the threshold.
- Both are **opt-in** (`null` by default) — unbounded behaviour by default.

> 💡 **Security recommendation**: for any externally-sourced archive (upload, download), combine `maxEntries` + `maxSize` + `overwrite: false`, and ideally a preliminary `dryRun` to inspect the content.

### Permission restoration (`keepPermissions`)

When `keepPermissions: true`, `unzip` reads the Unix mode stored in each entry's external attributes (`OPSYS_UNIX`) and applies it via `chmod`, for **files and directories**:

```php
unzip( '/releases/app.zip' , '/opt/app' , [
    ZipOption::KEEP_PERMISSIONS => true ,
]) ;
// → a script packaged with mode 0750 is restored executable.
```

- Entries **without** Unix permissions (zip created on Windows, or attributes absent) keep the default mode.
- Best-effort: a `chmod` that fails (e.g. the target is not owned by us) is silently ignored.

### Examples

```php
use function oihana\files\archive\zip\unzip;
use oihana\files\enums\ZipOption;

// 1. Basic extraction
unzip( '/path/to/archive.zip' , '/output/dir' ) ;

// 2. With options
unzip( '/path/to/archive.zip' , '/output/dir' , [
    ZipOption::OVERWRITE       => false ,
    ZipOption::KEEP_PERMISSIONS => true ,
]) ;

// 3. Dry-run: preview content without extracting
$files = unzip( '/path/to/archive.zip' , '/output/dir' , [
    ZipOption::DRY_RUN => true ,
]) ;
print_r( $files ) ;
// ['file1.txt', 'subdir/file2.php', ...]   (directory entries excluded)

// 4. Safe workflow for user upload
$preview = unzip( $uploadedArchive , $extractDir , [
    ZipOption::DRY_RUN     => true ,
    ZipOption::MAX_ENTRIES => 10_000 ,
    ZipOption::MAX_SIZE    => 100 * 1024 * 1024 ,
]) ;

unzip( $uploadedArchive , $extractDir , [
    ZipOption::OVERWRITE => false ,             // refuse to overwrite an existing file
    ZipOption::MAX_SIZE  => 100 * 1024 * 1024 , // re-checks at pre-scan
]) ;
```

---

## `assertZip`

```php
assertZip( string $filePath , bool $strictMode = false ) : bool
```

**⚠ Misleading name warning**: like [`assertTar`](untar.md#asserttar), this function **returns a `bool`** and does not systematically throw.

**Throws `FileException`** only if the file does not exist (via `assertFile`).

### Validation logic

1. **`hasZipExtension`** — `.zip` extension? Otherwise → `false`.
2. **`hasZipMimeType`** — zip MIME? Otherwise → `false`.
3. **Strict mode** (`$strictMode: true`) — `validateZipStructure` (`ZipArchive` open + inspection).

```php
use function oihana\files\archive\zip\assertZip;

assertZip( '/archives/sample.zip' ) ;                    // → true (extension + MIME)
assertZip( '/archives/sample.zip' , strictMode: true ) ; // → true if structurally valid
assertZip( '/path/missing.zip' ) ;                       // → FileException
```

> ℹ️ `unzip()` calls `assertZip()` mainly to guarantee the file exists; the actual validity of the archive is decided by `ZipArchive::open()`.

---

## `hasZipExtension`

```php
hasZipExtension(
    string $filePath ,
    array  $zipExtensions = [ FileExtension::ZIP ] // ['.zip']
) : bool
```

**Fast, purely textual** check: compares the (lowercased) extension to the provided list.

```php
use function oihana\files\archive\zip\hasZipExtension;

hasZipExtension( '/path/archive.zip' ) ; // true
hasZipExtension( '/path/ARCHIVE.ZIP' ) ; // true (case-insensitive)
hasZipExtension( '/path/archive.tar' ) ; // false
hasZipExtension( '/path/README.md'   ) ; // false

// Custom list
hasZipExtension( '/path/pack.zipx' , [ '.zip' , '.zipx' ] ) ; // true
```

---

## `hasZipMimeType`

```php
hasZipMimeType(
    string $filePath ,
    array  $mimeTypes = [
        'application/zip' ,
        'application/x-zip' ,
        'application/x-zip-compressed' ,
        'application/zip-compressed' ,
        'multipart/x-zip' ,
    ]
) : bool
```

**MIME-based** check via `finfo` (analysis of the first bytes). Delegates to [`hasMimeType`](../files/mime.md#hasmimetype): **matches if the detected MIME contains** one of the listed strings (`str_contains`).

```php
use function oihana\files\archive\zip\hasZipMimeType;

hasZipMimeType( '/archives/file.zip' ) ;    // → true (MIME: application/zip)
hasZipMimeType( '/archives/missing.zip' ) ; // → false (file does not exist)

// Custom list
hasZipMimeType( '/path/file.zip' , [ 'application/zip' ] ) ;
```

> 💡 More reliable than `hasZipExtension` for maliciously renamed files, but slower (reads the file). A corrupted zip is often detected as `application/octet-stream` → returns `false`.

---

## `validateZipStructure`

```php
validateZipStructure( string $filePath ) : bool
```

Validates the **internal structure** of a zip by attempting to open it via `ZipArchive` and inspecting **at most the first 10 entries** (perf limit).

```php
use function oihana\files\archive\zip\validateZipStructure;

validateZipStructure( '/path/to/archive.zip'  ) ; // true or false
validateZipStructure( '/path/to/invalid.zip'  ) ; // false (cannot open)
validateZipStructure( '/path/to/not_a_zip.txt') ; // false
validateZipStructure( '/nonexistent/file.zip' ) ; // false (missing file)
```

### Validation function choice: matrix

| Level | Speed | Function | Checks |
|---|---|---|---|
| 1 (fast) | µs | `hasZipExtension` | `.zip` extension. |
| 2 (moderate) | ms | `hasZipMimeType` | MIME via `finfo` (reads file start). |
| 3 (slow) | ms+ | `validateZipStructure` | `ZipArchive` open + entry inspection. |
| 4 (combined) | ms+ | `assertZip` (strict) | 1 + 2 + 3. |

---

## See also

- [Creating an archive](zip.md) — `zip`, `zipDirectory`, `zipFileInfo`.
- [Namespace overview](README.md).
- [Enums](../enums.md) — `ZipOption`, `ZipInfo`, `FileExtension`, `CompressionType`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Extracting a tar](untar.md) — the equivalent for the tar format.
