# System

Fourteen utility functions to interact with the operating system, manipulate paths, and extract file information.

## OS detection

- [`isLinux`](#islinux) — true if the OS is Linux.
- [`isMac`](#ismac) — true if the OS is macOS (Darwin).
- [`isWindows`](#iswindows) — true if the OS is Windows.
- [`isOtherOS`](#isotheros) — true otherwise (BSD, Solaris, etc.).

## System directories

- [`getHomeDirectory`](#gethomedirectory) — the current user's `~` directory.
- [`getRoot`](#getroot) — root part of a path.
- [`getDirectory`](#getdirectory) — normalises and validates a directory path.
- [`getSchemeAndHierarchy`](#getschemeandhierarchy) — splits a path/URI into scheme and hierarchy.

## File metadata

- [`getOwnershipInfos`](#getownershipinfos) — UID/GID + names (owner/group).
- [`getBaseFileName`](#getbasefilename) — filename **without extension** (handles multi-part).
- [`getFileExtension`](#getfileextension) — extension (handles multi-part like `.tar.gz`).

## Timestamped paths (pure generators)

- [`getTimestampedFile`](#gettimestampedfile) — file path with timestamp, **without creating it**.
- [`getTimestampedDirectory`](#gettimestampeddirectory) — ditto for a directory.

> 💡 The variants that **actually create** the file/directory are `makeTimestampedFile` / `makeTimestampedDirectory` in [creation.md](creation.md).

---

## `isLinux`

```php
isLinux() : bool
```

True if `PHP_OS` starts with `LINUX`. Result is **memoised** on first call (`static $isLinux = null`).

```php
use function oihana\files\isLinux;

if ( isLinux() ) {
    // Linux-specific code
}
```

---

## `isMac`

```php
isMac() : bool
```

True if `PHP_OS` starts with `DARWIN`. Memoised.

> ⚠ Beware: on macOS, `PHP_OS` is `Darwin` (the kernel), not `Mac` or `macOS`.

---

## `isWindows`

```php
isWindows() : bool
```

True if `PHP_OS` starts with `WIN`. Memoised.

---

## `isOtherOS`

```php
isOtherOS() : bool
```

True if **none** of the three above — covers BSD, Solaris, AIX, Haiku, etc.

```php
use function oihana\files\{ isLinux , isMac , isWindows , isOtherOS } ;

if ( isLinux() )      { /* ... */ }
else if ( isMac() )   { /* ... */ }
else if ( isWindows() ) { /* ... */ }
else                  { /* POSIX-ish fallback */ }
```

---

## `getHomeDirectory`

```php
getHomeDirectory() : string
```

Returns the **canonical path** of the current user's home directory.

**Resolution strategy:**

1. **Unix / macOS / Linux**: `$HOME` if set and non-empty.
2. **Windows ≥ XP**: `$HOMEDRIVE` + `$HOMEPATH` (e.g. `C:` + `\Users\John`).
3. **Failure**: `RuntimeException`.

The result is passed through [`canonicalizePath`](../path/joining-and-normalizing.md#canonicalizepath) — separators unified, trailing slashes stripped.

```php
use function oihana\files\getHomeDirectory;

echo getHomeDirectory() ;
// → /home/alice     (Linux)
// → /Users/alice    (macOS)
// → C:/Users/Alice  (Windows)
```

> 💡 Used internally by `canonicalizePath` for `~` expansion.

---

## `getRoot`

```php
getRoot( string $path ) : string
```

Extracts the **root part** of a path:

| Input                          | Output       |
|--------------------------------|--------------|
| `'/usr/local/bin'`             | `'/'`        |
| `'C:\\Windows\\System32'`      | `'C:/'`      |
| `'D:'`                         | `'D:/'`      |
| `'file:///var/log'`            | `'file:///'` |
| `'phar:///app/bundle.phar'`    | `'phar:///'` |
| `'relative/path'`              | `''` (empty) |
| `''`                           | `''` (empty) |

```php
use function oihana\files\getRoot;

echo getRoot( 'file:///var/log' ) ;       // 'file:///'
echo getRoot( '/usr/local/bin' ) ;         // '/'
echo getRoot( 'C:\\Windows\\System32' ) ;  // 'C:/'
echo getRoot( 'D:' ) ;                     // 'D:/'
echo getRoot( 'some/relative/path' ) ;     // ''
```

Compare with [`splitPath`](../path/inspection.md#splitpath) which returns **root + remainder** in an array.

---

## `getDirectory`

```php
getDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = false
) : string
```

**Normalises** a directory path (and optionally validates). Very flexible on input:

- `null` or `''` → treated as empty string (assertion thrown if `assertable: true`).
- `string` → used as-is.
- `array` → non-empty segments joined by `DIRECTORY_SEPARATOR`.

The **trailing slash is always stripped** before return.

### Usage

```php
use function oihana\files\getDirectory;

// String with trailing slash
getDirectory( '/tmp/' ) ;
// → '/tmp'  (slash stripped, existence checked)

// Array with null/empty segments dropped
getDirectory( [ '/tmp' , '' , 'logs' , null ] ) ;
// → '/tmp/logs'  (invalid segments filtered)

// Without validation
getDirectory( '/path/does/not/exist/' , assertable: false ) ;
// → '/path/does/not/exist'  (no error, slash stripped)

// Require writability
getDirectory( sys_get_temp_dir() , isWritable: true ) ;
```

**Used internally** by `deleteDirectory`, `getTemporaryDirectory`, `makeTemporaryDirectory`.

---

## `getSchemeAndHierarchy`

```php
getSchemeAndHierarchy( string $filename ) : array
// returns: [?string $scheme, string $hierarchy]
```

Splits a **scheme** (`file`, `s3`, `phar`, ...) from the hierarchical part.

**Validation**: the scheme must match RFC-3986 (`[A-Za-z][A-Za-z0-9+\-.]*`) — otherwise `InvalidArgumentException`.

```php
use function oihana\files\getSchemeAndHierarchy;

getSchemeAndHierarchy( 's3://bucket/folder/img' ) ;
// → ['s3', 'bucket/folder/img']

getSchemeAndHierarchy( '/home/user/report.pdf' ) ;
// → [null, '/home/user/report.pdf']

getSchemeAndHierarchy( 'C:\\Windows\\notepad.exe' ) ;
// → [null, 'C:\\Windows\\notepad.exe']

getSchemeAndHierarchy( 'file:///tmp/cache.db' ) ;
// → ['file', '/tmp/cache.db']

// Malformed scheme
getSchemeAndHierarchy( '1http://invalid' ) ;
// → InvalidArgumentException
```

Compare with [`getRoot`](#getroot) (returns just the root) and [`splitPath`](../path/inspection.md#splitpath) (returns root + remainder with the slash kept in the root).

---

## `getOwnershipInfos`

```php
getOwnershipInfos( string $path ) : OwnershipInfos
```

Returns the **ownership information** of a file or directory as an `OwnershipInfos` object (see [options.md](../options/make-file-options.md)).

**Fields returned:**

- `uid`: numeric User ID.
- `gid`: numeric Group ID.
- `owner`: username (via `posix_getpwuid` — requires `ext-posix`).
- `group`: group name (via `posix_getgrgid`).

**If `ext-posix` is not loaded** (Windows by default), `owner` and `group` are `null` — UID/GID stay available.

**Throws `RuntimeException`** if the path does not exist.

```php
use function oihana\files\getOwnershipInfos;

$info = getOwnershipInfos( '/var/www/html' ) ;

echo $info->owner ;  // 'www-data' (or null without posix)
echo $info->uid ;    // 33
echo $info ;         // 'www-data:www-data (33:33)'
```

---

## `getBaseFileName`

```php
getBaseFileName(
    string $file ,
    ?array $multiplePartExtensions = null
) : string
```

Returns the **filename without extension**, supporting **multi-part extensions** (`.tar.gz`, `.blade.php`, etc.).

**Multi-part extensions list**:

- Default: `FileExtension::getMultiplePartExtensions()` (includes `.tar.gz`, `.tar.bz2`, `.blade.php`, etc.).
- Override possible via the 2nd argument.

**Throws `InvalidArgumentException`** if:
- empty path;
- path points to a directory or ends with `/`.

```php
use function oihana\files\getBaseFileName;

// Simple extension
echo getBaseFileName( '/path/to/image.png' ) ;
// → 'image'

// Known compound extension
echo getBaseFileName( '/backups/2025-07-18.tar.gz' ) ;
// → '2025-07-18'  (not '2025-07-18.tar')

echo getBaseFileName( '/views/template.blade.php' ) ;
// → 'template'

// Unknown multi-dot extension → fallback to last dot
echo getBaseFileName( '/logs/system.debug.txt' ) ;
// → 'system.debug'

// No extension
echo getBaseFileName( '/opt/bin/mybinary' ) ;
// → 'mybinary'

// Windows: backslashes normalised
echo getBaseFileName( 'C:\\Users\\me\\file.tar.gz' ) ;
// → 'file'

// Custom multi-part extensions
echo getBaseFileName( '/path/file.custom.ext' , [ '.custom.ext' ] ) ;
// → 'file'

// Dot file (no extension)
echo getBaseFileName( '/path/.env' ) ;
// → '.env'
```

---

## `getFileExtension`

```php
getFileExtension(
    string $file ,
    ?array $multiplePartExtensions = null ,
    bool   $lowercase = true
) : ?string
```

Returns the **extension** of a file, **with the leading dot**, supporting multi-part extensions. Returns `null` if no extension.

**Default `lowercase: true`** — `.JPG` becomes `.jpg`. Can be disabled.

```php
use function oihana\files\getFileExtension;

echo getFileExtension( '/path/to/archive.tar.gz' ) ;    // '.tar.gz'
echo getFileExtension( 'photo.JPG' ) ;                  // '.jpg'  (lowercased)
echo getFileExtension( '/some/file.txt' ) ;             // '.txt'
echo getFileExtension( '/templates/home.blade.php' ) ;  // '.blade.php'
echo getFileExtension( 'script.min.js' ) ;              // '.js'  (.min.js not in default list)

// Custom
echo getFileExtension( 'file.custom.ext' , [ '.custom.ext' ] ) ;  // '.custom.ext'

// Preserve case
echo getFileExtension( 'README.MD' , null , false ) ;   // '.MD'

// No extension
echo getFileExtension( 'Makefile' ) ;                   // null
echo getFileExtension( '.env' ) ;                       // null  (.env is a name, not an extension)

// Windows
echo getFileExtension( 'C:\\projects\\demo.tar.bz2' ) ; // '.tar.bz2'
```

> 💡 Combined with [`FileExtension`](../enums.md) (extension catalogue) and `getBaseFileName`, you can cleanly decompose any path:
>
> ```php
> $base = getBaseFileName( $path ) ;
> $ext  = getFileExtension( $path ) ;
> // Rebuilds identically
> assert( $base . ( $ext ?? '' ) === basename( $path ) ) ;
> ```

---

## `getTimestampedFile`

```php
getTimestampedFile(
    ?string $date       = null ,
     string $basePath   = '' ,
    ?string $extension  = null ,
     string $prefix     = '' ,
     string $suffix     = '' ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true
) : ?string
```

**Generates** a file path with formatted timestamp — **creates nothing on disk**.

**Difference with [`makeTimestampedFile`](creation.md#maketimestampedfile)**: the latter calls `touch` to create the empty file. `getTimestampedFile` is the **pure generator** — useful to compute a path in advance.

```php
use function oihana\files\getTimestampedFile;

// Default
echo getTimestampedFile() ;
// → './2026-05-26T15:30:12'

// With base + prefix + suffix
echo getTimestampedFile(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/tmp' ,
    prefix  : 'backup_' ,
    suffix  : '.sql' ,
) ;
// → '/tmp/backup_2025-12-01T14:00:00.sql'

// Without validation (file is not supposed to exist)
echo getTimestampedFile(
    basePath  : '/backups' ,
    extension : '.tar.gz' ,
    assertable: false ,
) ;
// → '/backups/2026-05-26T15:30:12.tar.gz'
```

> ⚠ `$assertable: true` (default) expects the file to **exist** — counter-intuitive for a *generator*. To generate an output file name, pass `assertable: false`.

---

## `getTimestampedDirectory`

```php
getTimestampedDirectory(
    ?string $date       = null ,
     string $basePath   = '' ,
     string $prefix     = '' ,
     string $suffix     = '' ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true
) : string
```

Directory variant of `getTimestampedFile` — **no extension**, rest is identical.

```php
use function oihana\files\getTimestampedDirectory;

echo getTimestampedDirectory() ;
// → './2026-05-26T15:30:12'

echo getTimestampedDirectory(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/var/backups' ,
    suffix  : '_archive' ,
) ;
// → '/var/backups/2025-12-01T14:00:00_archive'

// Without assertion (directory will be created afterwards)
echo getTimestampedDirectory(
    prefix    : 'backup_' ,
    suffix    : '_final' ,
    timezone  : 'UTC' ,
    format    : 'Ymd_His' ,
    assertable: false ,
) ;
// → './backup_20260526_133012_final'
```

---

## See also

- [Creation](creation.md) — `makeTimestampedFile`, `makeTimestampedDirectory` which materialise the path.
- [Path](../path/README.md) — `splitPath`, `canonicalizePath`, etc.
- [Options](../options/make-file-options.md) — details on the `OwnershipInfos` object.
- [Enums](../enums.md) — `FileExtension` (multi-part extensions).
- [Dependencies](../getting-started/dependencies.md#oihanaphp-core) — `formatDateTime` used by the timestamped helpers.
- [Overview](README.md).
