# Creating a tar archive

Four functions to produce and inspect tar archives.

- [`tar`](#tar) — create from files and/or directories (main API).
- [`tarDirectory`](#tardirectory) — convenience for a single directory with filters and metadata.
- [`tarFileInfo`](#tarfileinfo) — inspection (validity, MIME, compression, count, size).
- [`tarIsCompressed`](#tariscompressed) — fast extension-based detection.

---

## `tar`

```php
tar(
    string|array $paths ,
    ?string      $outputPath  = null ,
    ?string      $compression = CompressionType::GZIP ,
    ?string      $preserveRoot = null
) : string
```

**Main API** for creating a tar archive. Accepts one or more files/directories as input.

### Parameters

| Parameter        | Type                | Effect |
|------------------|---------------------|--------|
| `$paths`         | `string \| string[]` | Absolute path(s) to include (files OR directories, mixing allowed). |
| `$outputPath`    | `?string`           | Final archive path. If `null`, an auto-generated name is used in `sys_get_temp_dir()`. |
| `$compression`   | `?string`           | `CompressionType::GZIP` (default), `BZIP2`, or `NONE`. |
| `$preserveRoot`  | `?string`           | If set (absolute path), entries are stored **relative to this directory** — useful to preserve structure. |

### Return and exceptions

- **Returns**: full path of the created archive.
- **`FileException`**: one of `$paths` does not exist.
- **`UnsupportedCompressionException`**: compression not supported (e.g. `bz2` without `ext-bz2`).
- **`DirectoryException`**: cannot create the temp directory.
- **`RuntimeException`**: no files added, or rename failure.

### Key behaviour

1. **Empty directories preserved**: `tar` walks the tree and calls `addEmptyDir` for empty directories — unlike a naive `cp -r`.
2. **Two-step work**: first creates a temporary `.tar` in `/tmp`, then compresses, then renames to `$outputPath`. Guarantees atomicity at the final-file level.
3. **`$preserveRoot`**:
   - If set AND matches a passed directory → entries relative to that directory (no prefix).
   - If unset → entries prefixed with `basename($path)` (typical when archiving multiple directories).

### Examples

```php
use function oihana\files\archive\tar\tar;
use oihana\files\enums\CompressionType;

// 1. One file, auto-named, default gzip
$path = tar( '/var/www/html/index.php' ) ;
// → /tmp/oihana/files/archive/tar/tar/archive_20260526_153012abc.tar.gz

// 2. A directory, bzip2, explicit output path
$path = tar(
    '/var/www/html' ,
    '/tmp/site.tar.bz2' ,
    CompressionType::BZIP2 ,
) ;

// 3. Multiple files, no compression
$path = tar(
    [ '/etc/hosts' , '/etc/hostname' ] ,
    '/tmp/config.tar' ,
    CompressionType::NONE ,
) ;

// 4. Preserve root — entries relative to preserveRoot
$path = tar(
    '/var/www/html/project' ,
    '/tmp/project.tar.gz' ,
    CompressionType::GZIP ,
    '/var/www/html' , // → archive entries: project/...
) ;
```

### Why `$preserveRoot`?

Without `$preserveRoot`:

```
project/
├── src/
└── public/
```

Archive produced (with `tar('/var/www/html/project', ...)`):

```
project/src/...
project/public/...
```

→ Extraction creates a `project/` subdirectory.

With `$preserveRoot = '/var/www/html'`:

```
project/src/...
project/public/...
```

With `$preserveRoot = '/var/www/html/project'`:

```
src/...
public/...
```

→ Extraction directly creates `src/` and `public/` without a wrapper.

---

## `tarDirectory`

```php
tarDirectory(
    string  $directory ,
    ?string $compression = CompressionType::GZIP ,
    ?string $outputPath  = null ,
    array   $options     = []
) : string
```

**Convenience** on top of `tar`, specialised for archiving **a single directory** with:

- **exclude filters** by pattern;
- **custom filter callback**;
- **embedded metadata** in an internal `.metadata.json` file inside the archive.

### Options

| Key (string or enum) | Type | Effect |
|---|---|---|
| `'exclude'` / `TarOption::EXCLUDE` | `string[]` | Glob/regex pattern list (cf. [`shouldExcludeFile`](../files/discovery.md#shouldexcludefile)). |
| `'filter'` / `TarOption::FILTER` | `?callable(string $filePath): bool` | Custom filter callback. Return `true` to include. |
| `'metadata'` / `TarOption::METADATA` | `array<string, string>` | Metadata JSON-serialised into `.metadata.json`. |

### Logic

- **If no options** (no exclude, no filter, no metadata) → direct `tar()` on the directory (fast).
- **Otherwise**:
  1. Filtered copy of the directory to a temp directory (via [`copyFilteredFiles`](../files/copying.md));
  2. Optional write of `.metadata.json`;
  3. `tar()` of the temp directory to `$outputPath`;
  4. Cleanup of the temp directory (in `finally`).

### If `$outputPath` is null

Default path: `dirname($directory)/basename($directory).{ext}`.

Example: `tarDirectory('/var/www/html')` → `/var/www/html.tar.gz`.

### Examples

```php
use function oihana\files\archive\tar\tarDirectory;
use oihana\files\enums\CompressionType;
use oihana\files\enums\TarOption;

// 1. Default gzip, auto name
$archive = tarDirectory( '/var/www/html' ) ;
// → /var/www/html.tar.gz

// 2. bz2, classic exclusions
$archive = tarDirectory(
    '/var/www/html' ,
    CompressionType::BZIP2 ,
    null ,
    [
        TarOption::EXCLUDE => [ '.git' , 'node_modules' , 'vendor' ] ,
    ]
) ;

// 3. Custom callback + metadata
$archive = tarDirectory(
    '/var/www/html' ,
    CompressionType::NONE ,
    '/backups/php-only.tar' ,
    [
        TarOption::FILTER => fn( string $filePath ) =>
            str_ends_with( $filePath , '.php' ) ,
        TarOption::METADATA => [
            'createdBy'    => 'admin' ,
            'description'  => 'Backup of PHP source files' ,
            'creationDate' => date( 'c' ) ,
        ] ,
    ]
) ;
```

> 💡 **When to prefer `tar` over `tarDirectory`?** When archiving **multiple non-contiguous sources** (`tar(['/etc/hosts', '/var/log'])`), or to control `$preserveRoot` manually. `tarDirectory` is handier for single-directory cases.

---

## `tarFileInfo`

```php
tarFileInfo( string $filePath , bool $strictMode = false ) : array
```

Inspects a tar file and returns its information as an associative array:

| Key           | Type      | Description |
|---------------|-----------|-------------|
| `isValid`     | `bool`    | Passes [`assertTar`](untar.md#asserttar) validation. |
| `extension`   | `string`  | Lowercase extension (without dot). |
| `mimeType`    | `?string` | MIME detected via `finfo`. |
| `compression` | `?string` | `gz`, `bz2`, or `none` (deduced from MIME). |
| `fileCount`   | `?int`    | Number of files (if valid). |
| `totalSize`   | `?int`    | Sum of sizes in bytes (if valid). |

**Throws `FileException`** if the file does not exist.

```php
use function oihana\files\archive\tar\tarFileInfo;

$info = tarFileInfo( '/archives/sample.tar' ) ;
print_r( $info ) ;
// [
//     'isValid'     => true,
//     'extension'   => 'tar',
//     'mimeType'    => 'application/x-tar',
//     'compression' => 'none',
//     'fileCount'   => 142,
//     'totalSize'   => 5283920,
// ]

// Invalid file → isValid: false, fileCount/totalSize: null
$info = tarFileInfo( '/bad/file.tar' ) ;

// Strict mode: also validates internal structure via validateTarStructure
$info = tarFileInfo( '/archives/sample.tar' , strictMode: true ) ;
```

---

## `tarIsCompressed`

```php
tarIsCompressed( string $tarFile ) : bool
```

**Fast** check whether a tar archive is compressed — using **filename extension only**, not content.

**Recognises:** `.tar.gz`, `.tgz`, `.tar.bz2`, `.tbz2` (case-insensitive).

```php
use function oihana\files\archive\tar\tarIsCompressed;

tarIsCompressed( 'archive.tar.gz'  ) ; // true
tarIsCompressed( 'archive.tgz'     ) ; // true
tarIsCompressed( 'archive.tar.bz2' ) ; // true
tarIsCompressed( 'archive.tbz2'    ) ; // true

tarIsCompressed( 'archive.tar'     ) ; // false (uncompressed)
tarIsCompressed( 'archive.zip'     ) ; // false
tarIsCompressed( 'README.md'       ) ; // false
```

> ⚠ Does **not consult the content** — a file named `fake.tar.gz` with arbitrary content returns `true`. For real validation, see [`assertTar`](untar.md#asserttar) or [`tarFileInfo`](#tarfileinfo).

---

## See also

- [Extracting an archive](untar.md) — `untar`, `assertTar`, `hasTarExtension`, `hasTarMimeType`, `validateTarStructure`.
- [Namespace overview](README.md).
- [Enums](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`.
- [Filtered copy](../files/copying.md) — `copyFilteredFiles` used by `tarDirectory`.
- [Phar](../phar/README.md) — internally used helpers.
