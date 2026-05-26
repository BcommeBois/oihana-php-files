# Filtered copy

A single function, but central for **backup**, **sync** and **export** workflows.

- [`copyFilteredFiles`](#copyfilteredfiles) — recursive copy with pattern exclusions + filter callback.

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

## See also

- [Discovery](discovery.md#shouldexcludefile) — `shouldExcludeFile` used for filtering.
- [Creation](creation.md) — `makeDirectory` creates destination directories.
- [Deletion](deletion.md) — `deleteDirectory` to clean an existing destination before copying.
- [Overview](README.md).
