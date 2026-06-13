# Copy and move

File copy and move helpers, from a **single file** to a **whole directory** (**backup**, **sync**, **export** and **archiving** workflows).

**Single file:**
- [`copyFile`](#copyfile) — copies a file (overwrite, directory destination, parent creation, typed exceptions).
- [`moveFile`](#movefile) — moves/renames a file (atomic `rename` + cross-filesystem copy/delete fallback).
- [`renameFile`](#renamefile) — semantic alias of `moveFile`.

**Directory (filtered copy):**
- [`copyFilteredFiles`](#copyfilteredfiles) — recursive copy with pattern exclusions + filter callback.
- [`copyFilteredFilesWithMetadata`](#copyfilteredfileswithmetadata) — `copyFilteredFiles` + optional `.metadata.json` + "nothing matched" guard.

---

## `copyFile`

```php
copyFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

Copies **one** file, with typed error handling (whereas [`copyFilteredFiles`](#copyfilteredfiles) mirrors a whole tree):

- the source is validated with [`assertFile`](assertions.md#assertfile) (must exist and be readable);
- if `$destination` is an **existing directory**, the file is copied **inside** it, keeping the source basename (the `cp source dir/` convention);
- copying a file **onto itself** is refused (it would truncate the source);
- when `$overwrite` is `false`, an existing destination raises a `FileException`;
- the destination's parent directory is created on demand via [`makeDirectory`](creation.md#makedirectory) when `$createDirectory` is `true`.

**Returns `true`** on success.

| Case | Exception |
|---|---|
| Missing / unreadable source | `FileException` (via `assertFile`) |
| Source and destination are the same file | `FileException` |
| Existing destination with `$overwrite = false` | `FileException` |
| Copy failure | `FileException` |
| Missing parent directory with `$createDirectory = false` | `DirectoryException` |

```php
use function oihana\files\copyFile;

copyFile( '/data/report.pdf' , '/backup/report.pdf' ) ;          // explicit target
copyFile( '/data/report.pdf' , '/backup' ) ;                     // into a directory
copyFile( '/data/report.pdf' , '/backup/report.pdf' , false ) ;  // throws if it already exists
```

---

## `moveFile`

```php
moveFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

Moves (or renames) **one** file. Shares the destination semantics of [`copyFile`](#copyfile) (directory destination, `overwrite`, parent creation).

The move uses `rename()` (**atomic on the same filesystem**). When source and destination live on **different filesystems**, `rename()` cannot span devices, so the function transparently falls back to [`copyFile`](#copyfile) + [`deleteFile`](deletion.md#deletefile).

**Returns `true`** on success. Same exceptions as `copyFile` (except "same file": renaming a file onto itself is a successful no-op).

```php
use function oihana\files\moveFile;

moveFile( '/tmp/upload.tmp' , '/data/final.pdf' ) ; // move + rename
moveFile( '/data/final.pdf' , '/archive' ) ;        // into a directory
```

---

## `renameFile`

```php
renameFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

**Semantic alias** of [`moveFile`](#movefile): renaming a file is the same as moving it. See `moveFile` for full behaviour.

```php
use function oihana\files\renameFile;

renameFile( '/data/old-name.txt' , '/data/new-name.txt' ) ;
```

---

## `copyFilteredFiles`

```php
copyFilteredFiles(
    string    $sourceDir ,
    string    $destDir ,
    array     $excludePatterns = [] ,
    ?callable $filterCallback  = null
) : bool
```

Recursively copies a directory to another, **preserving structure**. Two independent and combinable filtering mechanisms:

1. **`$excludePatterns`** — list of **glob/regex patterns**; any file or directory matching is **skipped** (via [`shouldExcludeFile`](discovery.md#shouldexcludefile)).
2. **`$filterCallback`** — `fn(string $filePath): bool` callback; returns `true` to **include**.

Destination directories are **created on the fly** via [`makeDirectory`](creation.md#makedirectory).

**Returns `bool`**: `true` if at least one file or directory was copied, `false` otherwise.

**Throws `DirectoryException`** if a destination directory creation fails.

### Combined filtering logic

For a file to be copied, it must:

1. **NOT** match an exclude pattern.
2. **AND** return `true` from the callback (if provided).

The two filters are **AND-combined** — a file excluded by either is rejected.

### Complete example

Source structure:

```
/tmp/source/
├── .git/
│   └── config
├── images/
│   └── logo.png   (5 KB)
├── index.php      (1 KB)
└── error.log
```

Call:

```php
use function oihana\files\copyFilteredFiles;

$source = '/tmp/source' ;
$dest   = '/tmp/destination' ;

// 1. Exclude .git directories and all *.log
$exclude = [ '.git' , '*.log' ] ;

// 2. Also filter by size: no more than 2 KB
$filter = fn( string $filePath ) =>
    is_dir( $filePath ) || filesize( $filePath ) < 2048 ;

copyFilteredFiles( $source , $dest , $exclude , $filter ) ;
```

Result (`/tmp/destination/`):

```
/tmp/destination/
├── images/        ← directory copied (passes is_dir check)
└── index.php      ← copied (1 KB < 2 KB)
```

Explanation:
- `.git/` excluded by `.git`;
- `error.log` excluded by `*.log`;
- `images/` created by `makeDirectory`;
- `images/logo.png` rejected by the filter callback (5 KB ≥ 2 KB);
- `index.php` copied.

### Common exclusion patterns

```php
// VCS and dependencies
$dependencies = [ '.git' , '.svn' , 'node_modules' , 'vendor' ] ;

// Caches and builds
$builds = [ '.cache' , 'tmp' , 'build' , 'dist' , '*.log' , '*.bak' ] ;

// Environment files
$envs = [ '.env' , '.env.local' , '*.local' ] ;

copyFilteredFiles( $source , $dest , [ ...$dependencies , ...$builds , ...$envs ] ) ;
```

### Use case: site backup

```php
use function oihana\files\{ copyFilteredFiles , makeTimestampedDirectory } ;

$snapshot = makeTimestampedDirectory(
    basePath: '/backups' ,
    prefix  : 'site-' ,
) ;
// → /backups/site-2026-05-26T15:30:12

copyFilteredFiles(
    '/var/www/site' ,
    $snapshot ,
    [ '.git' , 'node_modules' , 'vendor' , '*.log' , 'cache/*' ] ,
    fn( string $path ) =>
        // No file bigger than 50 MB
        is_dir( $path ) || filesize( $path ) < 50 * 1024 * 1024
) ;
```

### Use case: export for publication

```php
// Copy sources without anything useless to a final consumer
copyFilteredFiles(
    '/dev/myproject' ,
    '/dist/myproject' ,
    [
        '.git' , '.gitignore' , '.github' ,
        'node_modules' , 'vendor' ,
        'tests' , 'docs' ,
        '*.md.bak' , '*.tmp' ,
        'phpunit.xml' , 'phpdoc.xml' ,
    ]
) ;
```

### Pitfalls and limitations

- **Symlinks**: the function uses `RecursiveDirectoryIterator::SKIP_DOTS`, but **does not follow symlinks** by default unless you change the flags. Native `copy()` doesn't traverse either — symlinks are copied as symlinks (or as target, depending on the platform).
- **Permissions**: PHP's native `copy()` **does not preserve** owner/group (only content and basic perms). For a faithful backup, consider `rsync` or `cp -p`.
- **Files open for writing**: may be copied in an intermediate state — no read lock.
- **Atomicity**: the copy is not transactional. If it fails mid-way (disk full, permission), `$destDir` is left in a partial state.

> 💡 **For very large volumes**, `rsync` remains faster and more robust. `copyFilteredFiles` is ideal for one-off snapshots < ~1 GB.

---

## `copyFilteredFilesWithMetadata`

```php
copyFilteredFilesWithMetadata(
    string        $sourceDir ,
    string        $destDir ,
    array         $excludePatterns = [] ,
    ?callable     $filterCallback  = null ,
    array         $metadata        = []
) : void
```

The **staging** step shared by the directory-archiving helpers ([`tarDirectory`](../archive/tar.md#tardirectory) and [`zipDirectory`](../archive/zip.md#zipdirectory)). It:

1. delegates the filtered copy to [`copyFilteredFiles`](#copyfilteredfiles);
2. writes, when `$metadata` is non-empty, a `.metadata.json` file (pretty JSON, unescaped slashes) at the root of `$destDir`;
3. **guarantees the destination is non-empty** — otherwise throws `RuntimeException`.

> ℹ️ Embedding metadata makes the destination non-empty even when **no** source file matched the filters (the archive then contains only `.metadata.json`).

### Return and exceptions

- **Return**: `void`.
- **`RuntimeException`**: no file matched the filters **and** no metadata was provided (message `"No files match the filtering criteria."`).

### Example

```php
use function oihana\files\copyFilteredFilesWithMetadata;

copyFilteredFilesWithMetadata(
    '/var/www/html' ,
    '/tmp/staging' ,
    [ '.git' , 'node_modules' ] ,
    fn( string $path ): bool => str_ends_with( $path , '.php' ) ,
    [ 'createdBy' => 'admin' , 'date' => date( 'c' ) ] ,
) ;
// /tmp/staging contains the filtered .php files + a .metadata.json
```

---

## See also

- [Discovery](discovery.md#shouldexcludefile) — `shouldExcludeFile` used for filtering.
- [Creation](creation.md) — `makeDirectory` creates destination directories.
- [Deletion](deletion.md) — `deleteDirectory` to clean an existing destination before copying.
- [Overview](README.md).
