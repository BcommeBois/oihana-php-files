# Inspection

Five functions to analyse a path without modifying it.

- [`splitPath`](#splitpath) — splits *root* / *remainder*.
- [`directoryPath`](#directorypath) — extracts the parent directory (robust replacement for `dirname()`).
- [`isLocalPath`](#islocalpath) — detects remote paths (with `://` scheme).
- [`isBasePath`](#isbasepath) — checks whether one path is contained in another.
- [`realpathParent`](#realpathparent) — resolves a path's parent, keeps its last segment.

---

## `splitPath`

```php
splitPath( string $path ) : array  // [string $root, string $remainder]
```

Splits a **canonical** path into two parts: the **root** (protocol / drive letter / leading slash) and the **remainder**.

**Supported patterns:**

| Input                           | Returned root | Returned remainder      |
|---------------------------------|---------------|-------------------------|
| `/var/www/html`                 | `/`           | `var/www/html`          |
| `C:/Windows/System32`           | `C:/`         | `Windows/System32`      |
| `C:`                            | `C:/`         | *(empty)*               |
| `file:///home/user/docs`        | `file:///`    | `home/user/docs`        |
| `phar:///app/bundle.phar/src`   | `phar:///`    | `app/bundle.phar/src`   |

> ⚠ The input is assumed to be already **canonical** (uniform slashes, no `.` / `..`). For a raw path, canonicalise first with [`canonicalizePath`](joining-and-normalizing.md#canonicalizepath).

```php
use function oihana\files\path\splitPath;

[ $root , $rest ] = splitPath( '/etc/nginx/nginx.conf' );
// $root = '/' , $rest = 'etc/nginx/nginx.conf'

[ $root , $rest ] = splitPath( 'C:/Program Files' );
// $root = 'C:/' , $rest = 'Program Files'

[ $root , $rest ] = splitPath( 'C:' );
// $root = 'C:/' , $rest = ''

[ $root , $rest ] = splitPath( 'file:///var/log' );
// $root = 'file:///' , $rest = 'var/log'
```

**When to use it?** When you want to **manipulate root and remainder independently** — for example to preserve the scheme of a `phar://` through a custom transformation. This is what `canonicalizePath`, `makeAbsolute` and `relativePath` do internally.

---

## `directoryPath`

```php
directoryPath( string $path ) : string
```

**Robust and portable** replacement for native `dirname()`. Returns the parent directory in canonical form.

**Fixes the shortcomings of native `dirname()`:**

| Input             | Native `dirname()` | `directoryPath()` |
|-------------------|--------------------|-------------------|
| `'C:/symfony'`    | `'C:'`             | `'C:/'`           |
| `'C:/'`           | `'.'`              | `'C:/'`           |
| `'C:'`            | `'.'`              | `'C:/'`           |
| `'symfony'`       | `'.'`              | `''`              |
| `'\\back\\slash'` | (fails on Unix)    | `'\\back'`        |

**Also handles:**

- Schemes (`file:///home/user/doc.txt` → `'/home/user'`, `file://` scheme stripped).
- Non-`file://` schemes (`phar:///app/src/main.php` → `'phar:///app/src'`, scheme preserved).
- Windows UNC paths (`\\server\share\folder` → `\\server\share`).
- **Input separator preservation**: if the input contains `\`, the result uses `\`. Otherwise `/`.

```php
use function oihana\files\path\directoryPath;

directoryPath( '/var/www/html/file.txt' );
// → '/var/www/html'

directoryPath( 'C:\\Windows\\System32\\file.txt' );
// → 'C:\\Windows\\System32'

directoryPath( 'D:/Program Files/My App/file.txt' );
// → 'D:/Program Files/My App'

directoryPath( 'file:///home/user/doc.txt' );
// → '/home/user'  (the file:// scheme is stripped)

directoryPath( 'phar:///app/src/main.php' );
// → 'phar:///app/src'  (the phar:// scheme is preserved)

directoryPath( 'file.txt' );  // → ''
directoryPath( '' );          // → ''
```

> 💡 **Why is `file://` stripped but not `phar://`?** Because `file://` is semantically equivalent to *no scheme* (local filesystem), whereas `phar://` designates a distinct PHP wrapper. This convention follows what most Symfony-world libraries do.

---

## `isLocalPath`

```php
isLocalPath( string $path ) : bool
```

True if the path points to the **machine's filesystem** (not a remote URL).

**Detection:** simple presence of `://` in the string.

- `'/var/log'`, `'C:\\Users'`, `'./relative'` → `true`.
- `'https://example.com'`, `'s3://bucket'`, `'ftp://host'` → `false`.

**Edge case:** `'phar://'`, `'vfs://'`, `'file://'` also return `false` (they contain `://`). If you want to distinguish a *local PHP stream wrapper* from a remote URL, write your own scheme-based check.

```php
use function oihana\files\path\isLocalPath;

isLocalPath( '/var/log/app.log' );     // true
isLocalPath( 'C:\\Users\\Admin' );     // true
isLocalPath( './config.ini' );         // true

isLocalPath( 'https://example.com' );  // false
isLocalPath( 's3://my-bucket/file' );  // false
isLocalPath( 'phar://x.phar' );        // false (contains ://)
isLocalPath( '' );                     // false (empty)
```

---

## `isBasePath`

```php
isBasePath( string $basePath , string $childPath ) : bool
```

True if `$childPath` is **equal to** or **contained in** `$basePath`.

**Algorithm:**

1. Canonicalise both paths.
2. Append a trailing slash to `$basePath` and compare with `str_starts_with($childPath . '/', $basePath . '/')`.

The **trailing slash** trick avoids false positives like `/var/www-legacy` being considered as contained in `/var/www`.

```php
use function oihana\files\path\isBasePath;

isBasePath( '/var/www', '/var/www/site/index.php' ); // true
isBasePath( '/var/www', '/var/www' );                // true (exact match)
isBasePath( '/var/www', '/var/www-legacy' );         // false (partial prefix rejected)
isBasePath( 'C:/Users', 'C:/Users/Bob/file.txt' );   // true
```

> ⚠ **Security use case.** This function is the ideal antidote to *path traversal* attacks (`../../../etc/passwd`). Typical workflow:
>
> ```php
> $safe = makeAbsolute( $userInput, $allowedRoot );
> if ( !isBasePath( $allowedRoot, $safe ) ) {
>     throw new \RuntimeException("Refused: $safe escapes $allowedRoot");
> }
> ```

---

## `realpathParent`

```php
realpathParent( string $path ) : string
```

Resolves a path's **parent** and leaves its **last segment** as it stands.

It is the only function on this page that asks the disk. `realpath()` answers for the whole path, which is too much when the last segment is the thing being judged: a symbolic link would be replaced by what it points at, and a path that does not exist would answer `false`. Here everything above the last segment is resolved — so a linked ancestor cannot hide where a path really leads — and the last segment is kept verbatim.

When the parent does not exist, the path is canonicalized lexically instead: there is nothing on disk to ask.

```php
use function oihana\files\path\realpathParent;

// On macOS, where /var is a link to /private/var:
realpathParent( '/var/folders/x/T' ) ; // '/private/var/folders/x/T'

// The parent is the filesystem root, and the result carries one separator, not two:
realpathParent( '/tmp' ) ;             // '/tmp'

// A link is kept as itself, never followed:
symlink( '/elsewhere' , '/tmp/pointer' ) ;
realpathParent( '/tmp/pointer' ) ;     // '/tmp/pointer'
```

**What is it for?** Deciding whether one path is contained in another without being fooled. [`deleteTemporaryDirectory`](../files/deletion.md#deletetemporarydirectory) uses it to refuse anything outside the temporary directory: resolving the parent closes the linked ancestor, keeping the last segment allows a link sitting in the temp directory to be removed without touching its target.

---

## See also

- [Joining and normalising](joining-and-normalizing.md) — `canonicalizePath` (used upstream).
- [Absolute vs relative](absolute-vs-relative.md) — detection and conversion.
- [Namespace overview](README.md).
- Glossary: [Scheme](../getting-started/glossary.md#scheme), [Local (path)](../getting-started/glossary.md#local-path).
