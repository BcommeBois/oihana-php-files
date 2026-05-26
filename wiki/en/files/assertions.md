# Assertions

Three functions that **throw an exception** if a file or directory is not in the expected state. Use them at the start of a function to guarantee preconditions and avoid chaining `if (!is_file(...)) throw ...`.

- [`assertFile`](#assertfile) — file exists + accessibility + optional MIME.
- [`assertDirectory`](#assertdirectory) — directory exists + accessibility + optional permissions.
- [`assertWritableDirectory`](#assertwritabledirectory) — shorthand for `assertDirectory( ..., isWritable: true )`.

> 💡 All assertions **return nothing** (`void`) — their side effect is **state guarantee** after the call. If they return normally, you can proceed without re-checking.

---

## `assertFile`

```php
assertFile(
    ?string $file ,
    ?array  $expectedMimeTypes = null ,
    bool    $isReadable        = true ,
    bool    $isWritable        = false
) : void
```

Checks that a file:

1. **is neither `null` nor empty** (after `trim`);
2. **exists and is a file** (not a directory, not a device) — `is_file()`;
3. **is readable** (if `$isReadable`, default `true`);
4. **is writable** (if `$isWritable`, default `false`);
5. **has an acceptable MIME type** (if `$expectedMimeTypes` is not empty).

**Throws `FileException`** as soon as a check fails, with an explicit message naming the path.

### Basic usage

```php
use function oihana\files\assertFile;

assertFile( '/etc/hosts' ) ;
// OK: returns nothing

assertFile( '/path/missing.txt' ) ;
// FileException: The file path "/path/missing.txt" is not a valid file.
```

### MIME type check

```php
assertFile( '/upload/document.pdf' , [ 'application/pdf' ] ) ;
// OK if the file really is a PDF

assertFile( '/upload/document.pdf' , [ 'image/png' , 'image/jpeg' ] ) ;
// FileException: MIME does not match
```

> 💡 The MIME check uses [`validateMimeType`](mime.md#validatemimetype) internally, which relies on the `ext-fileinfo` extension. See [mime.md](mime.md) for details.

### Write check

```php
assertFile( '/etc/config.ini' , null , isReadable: true , isWritable: true ) ;
// Throws FileException if you lack write permissions
```

### Explicit error cases

```php
assertFile( null ) ;
// FileException: The file path must not be null.

assertFile( '' ) ;
// FileException: The file path must not be empty.

assertFile( '   ' ) ;
// FileException: The file path must not be empty. (trim applied)

assertFile( '/var/log' ) ;
// FileException: The file path "/var/log" is not a valid file.
//   (it's a directory, not a file)
```

---

## `assertDirectory`

```php
assertDirectory(
    ?string $path ,
    bool    $isReadable          = true ,
    bool    $isWritable          = false ,
    ?int    $expectedPermissions = null
) : void
```

Checks that a directory:

1. **is neither `null` nor empty** (after `trim`);
2. **exists and is a directory** — `is_dir()`;
3. **is readable** (if `$isReadable`, default `true`);
4. **is writable** (if `$isWritable`, default `false`);
5. **has the exact expected permissions** (if `$expectedPermissions` is given — mask `0o777`).

**Throws `DirectoryException`** as soon as a check fails.

### Basic usage

```php
use function oihana\files\assertDirectory;

assertDirectory( '/var/www' ) ;
// OK

assertDirectory( '/var/www' , isWritable: true ) ;
// Throws DirectoryException if not writable
```

### Permission check

```php
assertDirectory( '/var/log/myapp' , true , true , 0755 ) ;
// Checks: readable + writable + permissions exactly 0755

// If /var/log/myapp is 0777:
// DirectoryException: The directory "..." has permissions "777", expected "755".
```

> ⚠ The comparison is **strict on the `0o777` mask** (SUID/SGID/sticky bits are ignored). Useful for security audits.

### Typical workflow

```php
try {
    assertDirectory( $userInput , isWritable: true ) ;
    // From here, $userInput is guaranteed to be a writable directory
    file_put_contents( $userInput . '/output.txt' , $data ) ;
}
catch ( DirectoryException $e ) {
    http_response_code( 403 ) ;
    echo $e->getMessage() ;
}
```

---

## `assertWritableDirectory`

```php
assertWritableDirectory( ?string $directory ) : void
```

**Shorthand**, strictly equivalent to:

```php
assertDirectory( $directory , isWritable: true ) ;
```

Use it when the intent is *"I'll write here, refuse if I can't"* — the code reads more clearly.

```php
use function oihana\files\assertWritableDirectory;

assertWritableDirectory( sys_get_temp_dir() ) ;
// OK (the temp dir is always writable)

assertWritableDirectory( '/etc' ) ;
// DirectoryException unless root
```

---

## Combinations and anti-patterns

### ✅ Combine assertions before an operation

```php
// To copy a file into a directory:
assertFile( $src , isReadable: true ) ;
assertWritableDirectory( $destDir ) ;
copy( $src , $destDir . '/' . basename( $src ) ) ;
```

### ❌ Anti-pattern: internal try/catch to swallow

```php
// BAD: loses the error info without even logging
try { assertFile( $path ) ; } catch ( FileException $e ) {}
```

→ Prefer the `assertable: false` branch on destructive functions (`deleteFile`, `clearFile`) if you want to *attempt* without throwing.

---

## See also

- [Creation](creation.md) — `makeFile`, `makeDirectory` (which validate internally too).
- [Deletion](deletion.md) — `deleteFile`, `deleteDirectory` (which accept an `$assertable` parameter).
- [MIME](mime.md) — `validateMimeType` used by `assertFile`.
- [Exceptions](../exceptions.md) — details on `FileException` and `DirectoryException`.
- [Overview](README.md).
