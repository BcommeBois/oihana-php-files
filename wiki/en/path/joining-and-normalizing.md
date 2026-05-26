# Joining and normalising

Four functions to assemble and clean up a path without touching the filesystem.

- [`joinPaths`](#joinpaths) — concatenates fragments into a canonical path.
- [`normalizePath`](#normalizepath) — replaces `\` with `/`.
- [`canonicalizePath`](#canonicalizepath) — canonical form (resolves `.`, `..`, `~`, slashes, scheme).
- [`extractCanonicalParts`](#extractcanonicalparts) — low-level primitive used by the two above.

---

## `joinPaths`

```php
joinPaths( string ...$paths ) : string
```

Concatenates an arbitrary number of fragments into **a single canonical path**.

**Rules:**

1. Empty fragments (`''`) are ignored.
2. The **first** non-empty fragment is kept as-is (leading slash, drive letter, scheme preserved).
3. Subsequent fragments are joined with **exactly one** `/`. No double `//` even if the previous fragment already ends with `/` or `\`.
4. The result is passed through [`canonicalizePath`](#canonicalizepath) at the end (`.` and `..` resolved, slashes unified).
5. If all fragments are empty → empty string.

```php
use function oihana\files\path\joinPaths;

joinPaths( '/var', 'log', 'app.log' );
// → '/var/log/app.log'

joinPaths( 'C:\\', 'Temp', '..', 'Logs' );
// → 'C:/Logs'

joinPaths( 'phar://archive.phar', '/sub', '/file.php' );
// → 'phar://archive.phar/sub/file.php'

joinPaths( '', 'relative', 'path' );
// → 'relative/path'
```

> 💡 Always prefer this over `$a . '/' . $b` or `$a . DIRECTORY_SEPARATOR . $b`: no risk of double slash, no scheme surprises, result is always canonical.

---

## `normalizePath`

```php
normalizePath( string $path ) : string
```

**Only** replaces `\` with `/`. Does not resolve `.` / `..`, does not canonicalise, does not touch the filesystem.

```php
use function oihana\files\path\normalizePath;

normalizePath( 'C:\\Users\\myuser\\Documents' );
// → 'C:/Users/myuser/Documents'

normalizePath( '/var/www/html' );
// → '/var/www/html' (unchanged)
```

**Use when:** you just want to unify separators (e.g. before an `explode('/')`), without paying the cost of full canonicalisation.

**Do not confuse with:** PHP's native `realpath()`, which also resolves symlinks and checks existence — see the [glossary](../getting-started/glossary.md#canonical-path).

---

## `canonicalizePath`

```php
canonicalizePath( string $path ) : string
```

Converts a path into its **canonical absolute-style form**: uniform slashes, `.` and `..` segments resolved, scheme preserved, `~` expanded to the home directory.

**Algorithm:**

1. **Cache**: lookup in the static LRU buffer ([`CanonicalizeBuffer`](../enums.md#canonicalizebuffer)).
2. **`~` expansion**: replaced by home (`getHomeDirectory()`).
3. **Separators**: `\` → `/` (via `normalizePath`).
4. **Split root / remainder** (via `splitPath`).
5. **Cleanup `.` / `..`** (via `extractCanonicalParts`).
6. **Stores result** in the buffer; periodic LRU clean-up.

**No filesystem access.** Non-existent paths are accepted.

```php
use function oihana\files\path\canonicalizePath;

canonicalizePath( '~/projects/../site//index.php' );
// → '/home/alice/site/index.php' (Linux, user alice)

canonicalizePath( 'C:\\Temp\\..\\Logs\\.' );
// → 'C:/Logs'

canonicalizePath( 'phar:///app/bundle.phar/src/../config' );
// → 'phar:///app/bundle.phar/config'
```

> ⚠ **Difference with `realpath()`**: `realpath` **resolves symlinks** and **returns `false`** if the path does not exist. `canonicalizePath` is purely textual — it works on any string.

---

## `extractCanonicalParts`

```php
extractCanonicalParts( string $root , string $pathWithoutRoot ) : array
```

Low-level primitive used by `canonicalizePath`. Splits `$pathWithoutRoot` into segments and **drops/resolves**:

- empty segments;
- `.` (current directory);
- `..` (parent directory) — resolved by popping the last segment when `$root` is not empty.

**If `$root` is empty** (relative path), leading `..` are preserved (`['..', '..', 'folder']`).

```php
use function oihana\files\path\extractCanonicalParts;

extractCanonicalParts( '/var/www', 'project/../cache/./logs' );
// → ['cache', 'logs']

extractCanonicalParts( '', '../../folder' );
// → ['..', '..', 'folder']
```

**When to call it directly?** Rarely. Prefer `canonicalizePath` which orchestrates the whole thing. Only useful if you write code that interacts with `splitPath` at the engine level.

---

## See also

- [Absolute vs relative](absolute-vs-relative.md) — conversions and detection.
- [Inspection](inspection.md) — `splitPath` (used internally by `canonicalizePath`).
- [Namespace overview](README.md) — back to the index.
- Glossary: [Canonical path](../getting-started/glossary.md#canonical-path), [Scheme](../getting-started/glossary.md#scheme).
