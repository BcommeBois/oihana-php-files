# Creation

Five functions to create files and directories, with options for permissions, ownership, and timestamped naming.

- [`makeFile`](#makefile) — create or update a file with content.
- [`makeDirectory`](#makedirectory) — create a directory (recursive by default).
- [`makeTimestampedFile`](#maketimestampedfile) — create a file whose name embeds a formatted timestamp.
- [`makeTimestampedDirectory`](#maketimestampeddirectory) — directory variant.
- [`makeTemporaryDirectory`](#maketemporarydirectory) — create a subdirectory inside the system temp.

> 💡 The matching deletion is documented in [deletion.md](deletion.md). The full temp workflow (create + use + delete) is documented in [temporary.md](temporary.md).

---

## `makeFile`

```php
makeFile(
    array|string|null $fileOrOptions ,
    ?string           $content = null ,
    array             $options = []
) : string
```

Creates (or updates) a file with its content. **Two equivalent signatures**:

### Positional style

```php
use function oihana\files\makeFile;

makeFile( '/path/to/file.txt' , 'Hello World' ) ;
// Creates the file (and parent directory if needed)

makeFile( '/log/app.log' , "\nNew line" , [ 'append' => true ] ) ;
// Append instead of overwrite
```

### Options-as-array style

```php
use oihana\files\enums\MakeFileOption;

makeFile([
    MakeFileOption::FILE        => '/path/to/file.txt' ,
    MakeFileOption::CONTENT     => 'Hello World' ,
    MakeFileOption::APPEND      => true ,
    MakeFileOption::PERMISSIONS => 0600 ,
]) ;
```

### Available options

| Key (string or enum)              | Type           | Default | Effect |
|-----------------------------------|----------------|---------|--------|
| `'file'` / `MakeFileOption::FILE` | `string`       | —       | File path (mandatory in array style). |
| `'content'` / `::CONTENT`         | `string`       | `''`    | Content to write. |
| `'append'` / `::APPEND`           | `bool`         | `false` | Append (`FILE_APPEND`) instead of overwriting. |
| `'force'` / `::FORCE`             | `bool`         | `true`  | Create missing parent directories. |
| `'lock'` / `::LOCK`               | `bool`         | `true`  | Exclusive lock during write (`LOCK_EX`). |
| `'overwrite'` / `::OVERWRITE`     | `bool`         | `false` | Allow overwriting an existing file. |
| `'permissions'` / `::PERMISSIONS` | `int` octal    | `0644`  | Final `chmod` mode. |
| `'owner'` / `::OWNER`             | `?string`      | `null`  | User (`chown`) — requires privileges. |
| `'group'` / `::GROUP`             | `?string`      | `null`  | Group (`chgrp`) — requires privileges. |

### Behaviour against an existing file

| Situation                              | Behaviour |
|----------------------------------------|---|
| File does not exist                    | Creation. |
| File exists + `overwrite: true`        | Overwrite. |
| File exists + `append: true`           | Append. |
| File exists + neither overwrite nor append | **No write**: returns the path as-is if writable, else `FileException`. |

### Exceptions

- **`FileException`**: invalid path, write failure, `chmod`/`chown`/`chgrp` failure.
- **`DirectoryException`**: failure to create a parent directory (if `force: true`).

### Examples

```php
// Restricted permissions, no lock
makeFile( '/etc/myapp/secret.key' , $key , [
    MakeFileOption::PERMISSIONS => 0600 ,
    MakeFileOption::LOCK        => false ,
]) ;

// With ownership (requires root)
makeFile( '/var/www/site/upload/file.bin' , $data , [
    MakeFileOption::OWNER => 'www-data' ,
    MakeFileOption::GROUP => 'www-data' ,
]) ;

// Without creating parent directories (fails if missing)
makeFile( '/var/log/app.log' , $line , [
    MakeFileOption::APPEND => true ,
    MakeFileOption::FORCE  => false ,
]) ;
```

---

## `makeDirectory`

```php
makeDirectory(
    null|array|string $pathOrOptions ,
    int               $permissions = 0755 ,
    bool              $recursive   = true ,
    ?string           $owner       = null ,
    ?string           $group       = null
) : ?string
```

Creates a directory if it does not exist. Returns the path (useful for chaining).

**If the directory already exists**: no error — it is simply checked to be writable (else `DirectoryException`).

### Positional style

```php
use function oihana\files\makeDirectory;

makeDirectory( '/var/log/myapp' ) ;
// → '/var/log/myapp'  (created at 0755, recursive)

makeDirectory( '/var/log/myapp/debug' , 0700 , true , 'www-data' , 'www-data' ) ;
```

### Options-as-array style

```php
use oihana\files\enums\MakeDirectoryOption;

makeDirectory([
    MakeDirectoryOption::PATH        => '/var/www/mydir' ,
    MakeDirectoryOption::PERMISSIONS => 0775 ,
    MakeDirectoryOption::RECURSIVE   => true ,
    MakeDirectoryOption::OWNER       => 'www-data' ,
    MakeDirectoryOption::GROUP       => 'www-data' ,
]) ;
```

### Available options

| Key (string or enum)                   | Type      | Default | Effect |
|----------------------------------------|-----------|---------|--------|
| `'path'` / `MakeDirectoryOption::PATH` | `string`  | —       | Path (mandatory in array style). |
| `'permissions'` / `::PERMISSIONS`      | `int`     | `0755`  | `mkdir` mode. |
| `'recursive'` / `::RECURSIVE`          | `bool`    | `true`  | Create parent directories. |
| `'owner'` / `::OWNER`                  | `?string` | `null`  | `chown`. |
| `'group'` / `::GROUP`                  | `?string` | `null`  | `chgrp`. |

### Typical chaining

`makeDirectory` returns the path, which enables:

```php
$path = makeDirectory( '/tmp/myapp/cache' ) ;
file_put_contents( $path . '/data.json' , json_encode( $data ) ) ;

// Or combined with joinPaths
use function oihana\files\path\joinPaths;
$logs = makeDirectory( joinPaths( $base , 'var' , 'logs' ) ) ;
```

---

## `makeTimestampedFile`

```php
makeTimestampedFile(
    ?string $date      = null ,
     string $basePath  = '' ,
    ?string $extension = null ,
     string $prefix    = '' ,
     string $suffix    = '' ,
    ?string $timezone  = 'Europe/Paris' ,
    ?string $format    = 'Y-m-d\TH:i:s' ,
       bool $mustExist = false
) : ?string
```

Creates an **empty** file (via `touch`) whose name is built from a formatted timestamp + optional prefix/suffix/extension.

**All arguments are nameable** — named-argument usage strongly recommended.

### Examples

```php
use function oihana\files\makeTimestampedFile;

makeTimestampedFile() ;
// → './2026-05-26T15:30:12'

makeTimestampedFile(
    basePath  : '/tmp' ,
    extension : '.log' ,
) ;
// → '/tmp/2026-05-26T15:30:12.log'

makeTimestampedFile(
    date     : '2025-12-01 14:00:00' ,
    basePath : '/backups' ,
    extension: '.tar.gz' ,
    prefix   : 'site-' ,
) ;
// → '/backups/site-2025-12-01T14:00:00.tar.gz'

makeTimestampedFile(
    prefix  : 'backup_' ,
    suffix  : '_final' ,
    timezone: 'UTC' ,
    format  : 'Ymd_His' ,
) ;
// → './backup_20260526_133012_final'
```

### Special case: `$mustExist`

If `mustExist: true`, the function throws `FileException` if the generated file **does not exist** after `touch`. Useful to validate that you actually have write permissions on `$basePath`.

### Date format

The `$format` parameter accepts any `DateTime::format()` format. See [`getTimestampedFile`](system.md#gettimestampedfile) for the version that **does not create the file** but just returns the formatted path.

---

## `makeTimestampedDirectory`

```php
makeTimestampedDirectory(
    ?string $date     = null ,
     string $basePath = '' ,
     string $prefix   = '' ,
     string $suffix   = '' ,
    ?string $timezone = 'Europe/Paris' ,
    ?string $format   = 'Y-m-d\TH:i:s'
) : ?string
```

Same idea as `makeTimestampedFile`, but creates a **directory** (mode `0755`, recursive).

```php
use function oihana\files\makeTimestampedDirectory;

makeTimestampedDirectory(
    basePath: '/backups' ,
    prefix  : 'snapshot_' ,
) ;
// → '/backups/snapshot_2026-05-26T15:30:12'

makeTimestampedDirectory(
    date    : '2025-12-01 14:00:00' ,
    basePath: '/tmp' ,
    prefix  : 'backup_' ,
    suffix  : '_v1' ,
) ;
// → '/tmp/backup_2025-12-01T14:00:00_v1'
```

> ⚠ No permission / owner / group options here — use `makeDirectory` afterwards if you need finer-grained control. Otherwise, the mode is fixed at `0755`.

---

## `makeTemporaryDirectory`

```php
makeTemporaryDirectory(
    string|array|null $path ,
    int               $permission = 0755
) : string
```

Creates (or returns, if it already exists) a subdirectory inside `sys_get_temp_dir()`.

**The `$path` parameter:**

- `null` → returns `sys_get_temp_dir()` itself (creates nothing).
- `string` → subdirectory: `'cache'` → `/tmp/cache`.
- `array` → joined segments: `['my', 'app']` → `/tmp/my/app`.

```php
use function oihana\files\makeTemporaryDirectory;

$reports = makeTemporaryDirectory( 'reports' ) ;
// → '/tmp/reports'

$cache = makeTemporaryDirectory( [ 'my' , 'app' , 'cache' ] , 0700 ) ;
// → '/tmp/my/app/cache' (mode 0700)

$tmp = makeTemporaryDirectory( null ) ;
// → '/tmp' (existing, just returned)
```

**Throws `DirectoryException`** if creation fails.

See the [full temporary workflow](temporary.md).

---

## See also

- [Deletion](deletion.md) — all mirror functions (`deleteFile`, `deleteDirectory`, `deleteTemporaryDirectory`).
- [Temporary directories](temporary.md) — create/use/delete workflow.
- [System](system.md) — `getTimestampedFile`, `getTimestampedDirectory` (generate the path without creating the file).
- [Enums](../enums.md) — `MakeFileOption`, `MakeDirectoryOption`.
- [Overview](README.md).
