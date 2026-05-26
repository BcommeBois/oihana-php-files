# Deletion

Four functions to empty, delete, and clean up files and directories.

- [`deleteFile`](#deletefile) — delete a file.
- [`deleteDirectory`](#deletedirectory) — recursively delete a directory (with its contents).
- [`clearFile`](#clearfile) — empty a file without deleting it.
- [`deleteTemporaryDirectory`](#deletetemporarydirectory) — delete a directory inside `sys_get_temp_dir()` (safeguarded).

> ⚠ **All these operations are destructive.** Prefer the `assertable` pattern (cf. [Assertions](assertions.md)) to validate expected state before deleting.

---

## `deleteFile`

```php
deleteFile(
    string $filePath ,
    bool   $assertable = true ,
    bool   $isReadable = true ,
    bool   $isWritable = true
) : bool
```

Deletes a file via `unlink()`.

**Default pre-validation** (`assertable: true`): calls [`assertFile`](assertions.md#assertfile) to guarantee the file exists and is readable+writable. Can be disabled.

**Throws `FileException`** if:
- assertions fail (in `assertable` mode);
- `unlink` fails (permissions, file open on Windows, etc.).

### Usage

```php
use function oihana\files\deleteFile;

// Strict mode: validate first, throw on issue
deleteFile( '/tmp/output.txt' ) ;

// Permissive mode: skip validation, but still throw if unlink fails
deleteFile( '/tmp/maybe.txt' , assertable: false ) ;
```

> 💡 `assertable: false` is **not fully silent** — if `unlink` fails, the exception is thrown. For *true silent*, wrap in `try/catch`.

---

## `deleteDirectory`

```php
deleteDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = true
) : bool
```

Recursively deletes a directory (files + subdirectories).

**Traversal:** uses `RecursiveIteratorIterator` in `CHILD_FIRST` mode — leaf files and directories are removed before their parents. Lets you delete a tree in a single call.

**The `$path` parameter accepts:**

- `string`: direct path (`'/tmp/old-cache'`).
- `array`: segments to join (`['/tmp', 'old-cache']` → `/tmp/old-cache`).
- `null`: equivalent to `sys_get_temp_dir()` via `getDirectory()`.

### Usage

```php
use function oihana\files\deleteDirectory;

// Direct deletion
deleteDirectory( '/tmp/build-artifacts' ) ;
// → true (entire tree gone)

// With segments
deleteDirectory( [ '/tmp' , 'cache' , 'images' ] ) ;
// → /tmp/cache/images deleted

// Permissive mode (directory may not exist)
deleteDirectory( '/tmp/maybe' , assertable: false ) ;
```

### Exceptions

- **`DirectoryException`**: assertions, failure of `rmdir`/`unlink` on an inner file/directory.

### ⚠ Gotchas

- **Symlinks**: `unlink` removes them (not their target). `rmdir` only removes *real directories*, not symlinks to a directory.
- **Permissions**: if you lack rights on an inner file, deletion stops mid-way — the directory ends up in a partially-deleted state.
- **Windows + open file**: `unlink` fails while any handle is open. Close all handles first.

---

## `clearFile`

```php
clearFile(
    ?string $file ,
    bool    $assertable = true
) : bool
```

**Empties** a file (truncates to 0 bytes) **without deleting it**. Useful to:
- empty a log file without having to recreate it (processes that hold a handle can keep writing to it);
- reset a cache file.

**Implementation:** `file_put_contents($file, '')`.

### Modes

| Mode                  | Behaviour against a missing or non-writable file |
|-----------------------|---|
| `assertable: true` (default) | Throws `FileException` via `assertFile`. |
| `assertable: false`           | Silently returns `false`. |

### Usage

```php
use function oihana\files\clearFile;

// Strict: throws FileException on issue
clearFile( '/var/log/myapp.log' ) ;
// → true

// Permissive: returns false on missing file
$ok = clearFile( '/var/log/maybe.log' , assertable: false ) ;
if ( !$ok ) {
    // Log or ignore
}
```

> 💡 **When `clearFile` vs `deleteFile` + `makeFile`?**
> - `clearFile`: preserves the inode → processes holding an open handle keep working.
> - `deleteFile` + `makeFile`: new inode → *open handles* write into the void (a common problem with syslog/logrotate). Use `copytruncate` on the logrotate side or `clearFile` on the app side.

---

## `deleteTemporaryDirectory`

```php
deleteTemporaryDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = true
) : bool
```

Deletes a directory **inside `sys_get_temp_dir()`**. A safeguarded wrapper around `deleteDirectory`.

### Safety guardrails

To avoid accidentally wiping things outside the temp:

1. **`null` or empty path** → returns `false`, no error.
2. **Attempt to delete the temp dir itself** → returns `false` (via `realpath()` comparison).
3. **Directory does not exist** → returns `true` (nothing to do, idempotent success).
4. **Otherwise** → delegates to `deleteDirectory`.

### Usage

```php
use function oihana\files\deleteTemporaryDirectory;

// Removes /tmp/old_reports and its contents
deleteTemporaryDirectory( 'old_reports' ) ;

// With segments
deleteTemporaryDirectory( [ 'tmp123' , 'cache' , 'images' ] ) ;

// Idempotent: no exception if already gone
deleteTemporaryDirectory( 'maybe' ) ;

// Blocked: cannot delete the temp dir itself
deleteTemporaryDirectory( null ) ;
// → false (refused, no exception)
```

> 💡 **The recommended pattern**: use `makeTemporaryDirectory` to create + `deleteTemporaryDirectory` to clean up. See [temporary.md](temporary.md) for the full workflow.

---

## Summary table

| Function                     | Target              | Default validation         | Permissive mode      | Idempotent |
|------------------------------|---------------------|----------------------------|----------------------|------------|
| `deleteFile`                 | File                | `assertFile`               | `assertable: false`  | No |
| `deleteDirectory`            | Directory + content | `assertDirectory`          | `assertable: false`  | No |
| `clearFile`                  | File (content)      | `assertFile`               | `assertable: false`  | Yes (truncating an empty file is a no-op) |
| `deleteTemporaryDirectory`   | Directory in temp   | Indirect (via `deleteDirectory`) | Built-in guardrails | Yes |

---

## See also

- [Assertions](assertions.md) — `assertFile`, `assertDirectory` (used upstream by all these functions).
- [Creation](creation.md) — mirror functions.
- [Temporary directories](temporary.md) — full workflow.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Overview](README.md).
