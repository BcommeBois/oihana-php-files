# Temporary directories — workflow

Three functions that together form a clean workflow for working in the **system temp directory** (`sys_get_temp_dir()` → `/tmp` on Unix, `C:\Windows\Temp` on Windows):

- [`getTemporaryDirectory`](#gettemporarydirectory) — computes the path without creating anything.
- [`makeTemporaryDirectory`](#maketemporarydirectory) — creates the directory (or returns the path if it already exists).
- [`deleteTemporaryDirectory`](#deletetemporarydirectory) — deletes the directory (with safeguards).

> 💡 This page documents the **workflow** and the details of `getTemporaryDirectory`. The other two are also listed in [creation.md](creation.md#maketemporarydirectory) and [deletion.md](deletion.md#deletetemporarydirectory) — the canonical doc is here.

---

## Why this trio?

Native PHP's temp API is minimal:

- `sys_get_temp_dir()` → returns the temp directory path.
- `tempnam()` → creates a unique-named file inside a directory.

But no native function:

- accepts a **structured subpath** (`['my', 'app', 'cache']`);
- combines **compute + create** in a single call;
- safeguards against **accidentally deleting** the temp dir itself.

`oihana/php-files` fills that gap with a coherent trio.

---

## `getTemporaryDirectory`

```php
getTemporaryDirectory(
    string|array|null $path       = null ,
    bool              $assertable = false ,
    bool              $isReadable = true ,
    bool              $isWritable = false
) : string
```

**Computes** the path of a subdirectory inside the system temp. **Creates nothing**.

### `$path` resolution rules

| `$path`                | Result on Unix                  | Result on Windows              |
|------------------------|---------------------------------|--------------------------------|
| `null` or `''`         | `/tmp`                          | `C:\Windows\Temp`              |
| `'cache'`              | `/tmp/cache`                    | `C:\Windows\Temp\cache`        |
| `['my', 'app']`        | `/tmp/my/app`                   | `C:\Windows\Temp\my\app`       |
| `'/var/tmp/myapp'` (Unix absolute) | `/var/tmp/myapp` (as-is) | n/a |
| `'C:\Temp\custom'` (Windows absolute) | n/a | `C:\Temp\custom` (as-is) |

> 💡 **Absolute path = bypass.** If you pass an already-absolute path (Unix with `/`, Windows with `C:\`), it is returned as-is — `sys_get_temp_dir()` is ignored. Handy to redirect to a custom temp (`/var/tmp` instead of `/tmp`).

### Validation options

| Parameter        | Effect when `true` |
|------------------|---|
| `$assertable`    | Validates the directory via `assertDirectory` (exists + accessible). |
| `$isReadable`    | Enables readability check (only if `$assertable: true`). |
| `$isWritable`    | Enables writability check (only if `$assertable: true`). |

**Throws `DirectoryException`** only if `$assertable: true` and the directory does not pass the checks.

### Examples

```php
use function oihana\files\getTemporaryDirectory;

// 1. Get the temp dir itself
echo getTemporaryDirectory() ;
// → /tmp

// 2. Build a subpath
echo getTemporaryDirectory( 'myapp/cache' ) ;
// → /tmp/myapp/cache  (may not exist — not checked)

// 3. With segments
echo getTemporaryDirectory( [ 'myapp' , 'logs' , 'errors' ] ) ;
// → /tmp/myapp/logs/errors

// 4. With validation: throws if /tmp/uploads does not exist
$dir = getTemporaryDirectory( 'uploads' , assertable: true , isWritable: true ) ;

// 5. Bypass sys_get_temp_dir
echo getTemporaryDirectory( '/var/tmp/myapp' ) ;
// → /var/tmp/myapp (as-is)
```

---

## Workflow type 1: disposable work directory

```php
use function oihana\files\{ makeTemporaryDirectory , deleteTemporaryDirectory } ;
use function oihana\files\path\joinPaths ;

$workDir = makeTemporaryDirectory( [ 'myapp' , 'process-' . uniqid() ] ) ;
// → /tmp/myapp/process-6f8c1a4b

try {
    // Work inside $workDir
    file_put_contents( joinPaths( $workDir , 'data.json' ) , json_encode( $payload ) ) ;
    runHeavyProcess( $workDir ) ;
}
finally {
    // Cleanup guaranteed even on exception
    deleteTemporaryDirectory( [ 'myapp' , basename( $workDir ) ] ) ;
}
```

> 💡 The `try/finally` guarantees the directory is cleaned up even if `runHeavyProcess` throws.

---

## Workflow type 2: persistent per-environment subdirectories

For a cache or a file pool that survives across requests/sessions:

```php
use function oihana\files\{ makeTemporaryDirectory , deleteTemporaryDirectory } ;

// On application boot
$cacheDir = makeTemporaryDirectory( [ 'myapp' , 'cache' , 'v2' ] , 0700 ) ;

// Usage across requests
file_put_contents( $cacheDir . '/key.dat' , $value ) ;

// On v3 deploy: wipe v2
deleteTemporaryDirectory( [ 'myapp' , 'cache' , 'v2' ] ) ;
```

> 💡 Mode `0700` (owner only) is recommended if the cache contains sensitive data; otherwise any user on the machine can read it from `/tmp`.

---

## `makeTemporaryDirectory`

See [creation.md#maketemporarydirectory](creation.md#maketemporarydirectory) — the signature is recapped here for quick reference:

```php
makeTemporaryDirectory(
    string|array|null $path ,
    int               $permission = 0755
) : string
```

Internally: calls `getTemporaryDirectory($path)` then `mkdir($dir, $permission, recursive: true)` if needed.

---

## `deleteTemporaryDirectory`

See [deletion.md#deletetemporarydirectory](deletion.md#deletetemporarydirectory). Safeguards recap:

1. `null` or `''` → `false` (refused).
2. Attempt to delete the temp dir itself → `false` (refused, `realpath` comparison).
3. Non-existent directory → `true` (idempotent).
4. Otherwise → delegates to `deleteDirectory`.

```php
deleteTemporaryDirectory( null ) ;        // false (refused)
deleteTemporaryDirectory( '' ) ;          // false (refused)
deleteTemporaryDirectory( 'myapp' ) ;     // /tmp/myapp deleted (or true if absent)
```

---

## Best practices

- **Always use an application prefix** (`['myapp', ...]`) to avoid polluting `/tmp` with anonymous directories.
- **Cleanup in `finally`** for transactional workflows.
- **Permissions `0700`** if content is sensitive (default `0755` is *world-readable*).
- **Do not rely on persistence**: the system may wipe `/tmp` on reboot (most Linux distros), or via `tmpfiles.d`.
- **Do not store absolute paths** to `/tmp/...` in a database — not portable across machines.

## See also

- [Creation](creation.md) — other creation functions.
- [Deletion](deletion.md) — `deleteDirectory` (used internally by `deleteTemporaryDirectory`).
- [System](system.md) — `getDirectory`, `getHomeDirectory`.
- Glossary: [Temporary](../getting-started/glossary.md#temporary-filedirectory).
- [Overview](README.md).
