# Creating a zip archive

Three functions to produce and inspect **zip** archives.

- [`zip`](#zip) — create from files and/or directories (main API).
- [`zipDirectory`](#zipdirectory) — convenience for a single directory with filters and metadata.
- [`zipFileInfo`](#zipfileinfo) — inspection (validity, MIME, compression, count, size).

> 💡 Implementation based on the native **`ZipArchive`** (the `ext-zip` extension). Unlike `tar` (which goes through `PharData`), compression is applied **per entry** and the archive is written to disk on `close()`.

> ℹ️ No equivalent to `tarIsCompressed`: a zip is a container whose compression is decided entry by entry — the notion of "compressed or uncompressed archive" has no global meaning.

---

## `zip`

```php
zip(
    string|array $paths ,
    ?string      $outputPath   = null ,
    ?string      $compression  = CompressionType::ZIP ,
    ?string      $preserveRoot = null
) : string
```

**Main API** for creating a zip archive. Accepts one or more files/directories as input. Mirror of [`tar`](tar.md#tar).

### Parameters

| Parameter        | Type                 | Effect |
|------------------|----------------------|--------|
| `$paths`         | `string \| string[]` | Absolute path(s) to include (files OR directories, mixing allowed). |
| `$outputPath`    | `?string`            | Final archive path. If `null`, an auto-generated name is used in `sys_get_temp_dir()`. |
| `$compression`   | `?string`            | **Per-entry method**: `CompressionType::ZIP` (DEFLATE, default) or `CompressionType::NONE` (stored, no compression). |
| `$preserveRoot`  | `?string`            | If set (absolute path), entries are stored **relative to this directory** — useful to preserve structure. |

### Return and exceptions

- **Returns**: full path of the created archive.
- **`FileException`**: one of `$paths` does not exist, **or** the archive cannot be created/written (output directory inaccessible).
- **`UnsupportedCompressionException`**: compression other than `ZIP` or `NONE` (e.g. `GZIP`, `BZIP2` make no sense for a zip).
- **`DirectoryException`**: cannot create the temp directory (when `$outputPath = null`).
- **`RuntimeException`**: no path provided (`[]`), or no file ultimately added.

### Key behaviour

1. **Empty directories preserved**: `zip` walks the tree and calls `addEmptyDir` for empty directories.
2. **Written on `close()`**: `ZipArchive` writes nothing until `close()` is called. `zip()` then checks that the file really exists — otherwise it throws `FileException` (e.g. missing parent directory: `open()` succeeds but `close()` fails silently).
3. **`$preserveRoot`**:
   - If set AND matches the passed directory → paths relative to that directory (no prefix).
   - If unset → paths prefixed with `basename($path)` (typical when archiving multiple directories).
4. **`NONE` compression**: each entry is marked `ZipArchive::CM_STORE` (useful for already-compressed files — jpeg, mp4 — where DEFLATE brings nothing).

### Examples

```php
use function oihana\files\archive\zip\zip;
use oihana\files\enums\CompressionType;

// 1. One file, auto-named, default DEFLATE
$path = zip( '/var/www/html/index.php' ) ;
// → /tmp/oihana/files/archive/zip/zip/archive_20260613_081500abc.zip

// 2. A directory, explicit output path
$path = zip( '/var/www/html' , '/tmp/site.zip' ) ;

// 3. Multiple files, stored without compression
$path = zip(
    [ '/etc/hosts' , '/etc/hostname' ] ,
    '/tmp/config.zip' ,
    CompressionType::NONE ,
) ;

// 4. Preserve root — entries relative to preserveRoot
$path = zip(
    '/var/www/html/project' ,
    '/tmp/project.zip' ,
    CompressionType::ZIP ,
    '/var/www/html/project' , // → entries: src/... public/... (without a project/ wrapper)
) ;
```

### Why `$preserveRoot`?

Given the tree:

```
project/
├── src/
└── public/
```

Without `$preserveRoot` — `zip('/var/www/html/project', ...)` produces:

```
project/src/...
project/public/...
```

→ Extraction recreates a `project/` subdirectory.

With `$preserveRoot = '/var/www/html/project'`:

```
src/...
public/...
```

→ Extraction directly creates `src/` and `public/` without a wrapper.

---

## `zipDirectory`

```php
zipDirectory(
    string  $directory ,
    ?string $compression = CompressionType::ZIP ,
    ?string $outputPath  = null ,
    array   $options     = []
) : string
```

**Convenience** on top of `zip`, specialised for archiving **a single directory** with filters and metadata. Mirror of [`tarDirectory`](tar.md#tardirectory).

### Options

| Key (string or enum) | Type | Effect |
|---|---|---|
| `'exclude'` / `ZipOption::EXCLUDE` | `string[]` | List of glob/name patterns to exclude (cf. [`shouldExcludeFile`](../files/discovery.md#shouldexcludefile)). |
| `'filter'` / `ZipOption::FILTER` | `?callable(string $filePath): bool` | Custom filter callback. Return `true` to include. |
| `'metadata'` / `ZipOption::METADATA` | `array<string, mixed>` | Metadata JSON-serialised into `.metadata.json`. |

### Logic

- **If no options** (no exclude, no filter, no metadata) → direct `zip()` on the directory (with `preserveRoot = $directory`).
- **Otherwise**:
  1. Filtered copy of the directory to a temp directory (via [`copyFilteredFilesWithMetadata`](../files/copying.md));
  2. Optional write of `.metadata.json`;
  3. `zip()` of the temp directory to `$outputPath`;
  4. Cleanup of the temp directory (in `finally`).

### If `$outputPath` is null

Default path: `dirname($directory)/basename($directory).zip`.

Example: `zipDirectory('/var/www/html')` → `/var/www/html.zip`.

### Examples

```php
use function oihana\files\archive\zip\zipDirectory;
use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipOption;

// 1. Auto name, DEFLATE
$archive = zipDirectory( '/var/www/html' ) ;
// → /var/www/html.zip

// 2. Stored without compression, classic exclusions
$archive = zipDirectory(
    '/var/www/html' ,
    CompressionType::NONE ,
    null ,
    [
        ZipOption::EXCLUDE => [ '.git' , 'node_modules' , 'vendor' ] ,
    ]
) ;

// 3. Custom callback + metadata
$archive = zipDirectory(
    '/var/www/html' ,
    CompressionType::ZIP ,
    '/backups/php-only.zip' ,
    [
        ZipOption::FILTER => fn( string $filePath ) =>
            str_ends_with( $filePath , '.php' ) ,
        ZipOption::METADATA => [
            'createdBy'    => 'admin' ,
            'description'  => 'Backup of PHP source files' ,
            'creationDate' => date( 'c' ) ,
        ] ,
    ]
) ;
```

> 💡 **When to prefer `zip` over `zipDirectory`?** When archiving **multiple non-contiguous sources** (`zip(['/etc/hosts', '/var/log'])`), or to control `$preserveRoot` manually. `zipDirectory` is handier for single-directory cases.

---

## `zipFileInfo`

```php
zipFileInfo( string $filePath , bool $strictMode = false ) : array
```

Inspects a zip file and returns its information as an associative array ([`ZipInfo`](../enums.md#zipinfo) keys):

| Key           | Type      | Description |
|---------------|-----------|-------------|
| `isValid`     | `bool`    | Passes [`assertZip`](unzip.md#assertzip) validation. |
| `extension`   | `string`  | Lowercase extension (without dot). |
| `mimeType`    | `?string` | MIME detected via `finfo`. |
| `compression` | `?string` | `zip` if the MIME is zip-like, otherwise `none`. |
| `fileCount`   | `?int`    | Number of entries (if valid). |
| `totalSize`   | `?int`    | Sum of **uncompressed** sizes in bytes (if valid). |

**Throws `FileException`** if the file does not exist.

```php
use function oihana\files\archive\zip\zipFileInfo;

$info = zipFileInfo( '/archives/sample.zip' ) ;
print_r( $info ) ;
// [
//     'isValid'     => true,
//     'extension'   => 'zip',
//     'mimeType'    => 'application/zip',
//     'compression' => 'zip',
//     'fileCount'   => 42,
//     'totalSize'   => 1048576,
// ]

// Invalid file → isValid: false, fileCount/totalSize: null
$info = zipFileInfo( '/bad/file.zip' ) ;

// Strict mode: also validates internal structure via validateZipStructure
$info = zipFileInfo( '/archives/sample.zip' , strictMode: true ) ;
```

---

## See also

- [Extracting an archive](unzip.md) — `unzip`, `assertZip`, `hasZipExtension`, `hasZipMimeType`, `validateZipStructure`.
- [Namespace overview](README.md).
- [Enums](../enums.md) — `CompressionType`, `ZipOption`, `ZipInfo`.
- [Filtered copy](../files/copying.md) — `copyFilteredFilesWithMetadata` used by `zipDirectory`.
- [Creating a tar](tar.md) — the equivalent for the tar format.
