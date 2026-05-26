# Absolute vs relative

Six functions to detect and convert between absolute and relative paths.

- **Detection**: [`isAbsolutePath`](#isabsolutepath), [`isRelativePath`](#isrelativepath).
- **Conversion**: [`makeAbsolute`](#makeabsolute), [`makeRelative`](#makerelative), [`computeRelativePath`](#computerelativepath), [`relativePath`](#relativepath).

> 💡 See the [glossary](../getting-started/glossary.md#absolute-path) for the formal definition of an absolute path (starts with `/`, a Windows drive letter, or a scheme like `phar://` / `file://`).

---

## `isAbsolutePath`

```php
isAbsolutePath( string $path ) : bool
```

True if `$path` is absolute (Unix, Windows, UNC, or URL scheme).

**Recognises:**

- Unix paths: `/var/www`, `/`.
- Windows paths with drive letter: `C:\Users\Test`, `D:/folder`, `C:` (special case), `C:/`.
- Windows UNC paths: `\\server\share\folder`.
- Paths with scheme: `file:///c/Users/`, `phar:///app/bundle.phar`.

**Edge cases:**

- `''` → `false` (empty).
- `'C:'` → `true` (drive letter alone).
- `'documents/report.pdf'` → `false`.
- `'../images/pic.jpg'` → `false`.

```php
use function oihana\files\path\isAbsolutePath;

isAbsolutePath( '/var/www' );           // true
isAbsolutePath( 'C:\\Users\\Test' );    // true
isAbsolutePath( 'D:/folder/file.txt' ); // true
isAbsolutePath( '\\\\server\\share' );  // true (UNC)
isAbsolutePath( 'file:///c/Users/' );   // true

isAbsolutePath( 'documents/x.pdf' );    // false
isAbsolutePath( '' );                   // false
```

---

## `isRelativePath`

```php
isRelativePath( string $path ) : bool
```

Exact inverse of `isAbsolutePath`. True if **not** absolute.

```php
use function oihana\files\path\isRelativePath;

isRelativePath( 'documents/report.pdf' ); // true
isRelativePath( '../images/pic.jpg' );    // true
isRelativePath( '' );                     // true (empty is considered relative)

isRelativePath( '/var/www' );             // false
isRelativePath( 'C:\\Users' );            // false
```

> ⚠ An **empty** string is considered *relative* by this function (consistent with `!isAbsolutePath('')`).

---

## `makeAbsolute`

```php
makeAbsolute( string $path , string $basePath ) : string
```

Turns `$path` into a **canonical absolute path** by joining it with `$basePath` (which must be absolute).

**Behaviour:**

- If `$path` is **already absolute** → simply canonicalised (`$basePath` ignored).
- If `$path` is relative → joined to `$basePath`, then canonicalised.
- `$basePath`'s scheme (`phar://`, etc.) is **preserved** in the result.

**Throws `InvalidArgumentException`** if:
- `$basePath` is empty;
- `$basePath` is not absolute.

```php
use function oihana\files\path\makeAbsolute;

makeAbsolute( 'documents/../project/file.txt', '/home/user' );
// → '/home/user/project/file.txt'

makeAbsolute( 'data\\.\\config.ini', 'C:\\Users\\Test' );
// → 'C:/Users/Test/data/config.ini'

// Path is already absolute: basePath ignored
makeAbsolute( '/etc/app.conf', '/var/www' );
// → '/etc/app.conf'

// Scheme preserved
makeAbsolute( 'src/bootstrap.php', 'phar:///usr/local/bin/composer.phar' );
// → 'phar:///usr/local/bin/composer.phar/src/bootstrap.php'
```

---

## `makeRelative`

```php
makeRelative( string $path , string $basePath ) : string
```

Turns an **absolute** path into a **relative** path against another absolute path.

**Preconditions:** both paths must be absolute and share the same root (same drive letter, same scheme).

**Throws `InvalidArgumentException`** if:
- one of the two paths is not absolute;
- roots differ (e.g. `C:/` vs `D:/`, or `phar://` vs `/`).

```php
use function oihana\files\path\makeRelative;

// Subdirectory
makeRelative( '/var/www/project/app', '/var/www/project' );
// → 'app'

// To a sibling
makeRelative( '/var/www/assets', '/var/www/project/app' );
// → '../../assets'

// Identical
makeRelative( '/var/www', '/var/www' );
// → ''  (empty string)

// From the root
makeRelative( '/home/user/documents', '/' );
// → 'home/user/documents'

// Windows
makeRelative( 'C:/Users/Test/Documents', 'C:/Users/Test/Downloads' );
// → '../Documents'

// Phar
makeRelative( 'phar:///app/src/controller', 'phar:///app/src/model' );
// → '../controller'
```

---

## `computeRelativePath`

```php
computeRelativePath( string $targetPath , string $basePath ) : string
```

Computes **the relativity between two already-normalised paths** (typically, two relative paths or the *post-root parts* already extracted).

**Difference with `relativePath`**: does not canonicalise, does not validate types, assumes you pass clean strings. Low-level primitive.

```php
use function oihana\files\path\computeRelativePath;

computeRelativePath( 'foo/bar/baz', 'foo'     ); // 'bar/baz'
computeRelativePath( 'foo/baz',     'foo/bar' ); // '../baz'
computeRelativePath( 'foo/bar',     'foo/bar' ); // '.'
computeRelativePath( 'a/b',         'a/b/c/d' ); // '../../'
computeRelativePath( 'a/b/c',       'a'       ); // 'b/c'
computeRelativePath( 'a/x/y',       'a/b/c'   ); // '../../x/y'
```

**When to use directly?** When you already have clean relative paths and want to skip canonicalisation overhead. Otherwise, `relativePath` is safer.

---

## `relativePath`

```php
relativePath( string $path , string $basePath ) : string
```

**Public and robust** version: canonicalises first, validates root consistency, handles schemes, then calls `computeRelativePath` internally.

**Preconditions:**
- Both paths must be of the same kind (both absolute OR both relative).
- If absolute: same root (drive letter or matching scheme).

**Throws `InvalidArgumentException`** if the combination is invalid (an absolute + a relative, or differing roots).

```php
use function oihana\files\path\relativePath;

// Absolute
relativePath( '/foo/bar/baz', '/foo'     ); // 'bar/baz'
relativePath( '/foo/baz',     '/foo/bar' ); // '../baz'
relativePath( '/foo/bar',     '/foo/bar' ); // '.'
relativePath( '/a/b',         '/a/b/c/d' ); // '../../'

// Relative
relativePath( 'foo/bar/baz', 'foo'     ); // 'bar/baz'
relativePath( 'foo/baz',     'foo/bar' ); // '../baz'
```

> 💡 **When to pick `makeRelative` vs `relativePath`?**
> - `makeRelative`: strictly absolute → absolute. More restrictive, sharper error message.
> - `relativePath`: also accepts relative → relative. More flexible.

---

## See also

- [Joining and normalising](joining-and-normalizing.md) — `joinPaths`, `canonicalizePath` (used upstream).
- [Inspection](inspection.md) — `splitPath`, `isBasePath`.
- [Namespace overview](README.md).
- Glossary: [Absolute](../getting-started/glossary.md#absolute-path), [Relative](../getting-started/glossary.md#relative-path).
