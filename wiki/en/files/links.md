# Symbolic links

Three functions to create, test and read symbolic links — the blind spot noted in the archive docs.

- [`createSymlink`](#createsymlink) — create a link (with `overwrite`).
- [`isSymlink`](#issymlink) — test whether a path is a link (`bool`, never throws).
- [`readSymlink`](#readsymlink) — read a link's target.

> 💡 On **Windows**, creating symlinks requires special privileges. These functions target POSIX environments (Linux, macOS).

---

## `createSymlink`

```php
createSymlink( string $target , string $link , bool $overwrite = false ) : bool
```

Creates a symbolic link `$link` pointing to `$target`. The target **does not need to exist** — dangling links are valid (standard POSIX behaviour). When an entry already exists at `$link`, it is replaced only if `$overwrite` is `true`.

| Case | Exception |
|---|---|
| Existing entry with `$overwrite = false` | `FileException` |
| Link creation failure | `FileException` |

```php
use function oihana\files\createSymlink;

createSymlink( '/var/www/releases/42' , '/var/www/current' , overwrite: true ) ;
```

---

## `isSymlink`

```php
isSymlink( string $path ) : bool
```

Wrapper around `is_link()`: returns `false` (never throws) for a regular file, a directory, or a non-existent path.

```php
use function oihana\files\isSymlink;

isSymlink( '/var/www/current' ) ; // true
isSymlink( '/etc/hosts' ) ;       // false
```

---

## `readSymlink`

```php
readSymlink( string $link ) : string
```

Returns the target a symbolic link points to. **Throws `FileException`** if `$link` is not a link.

```php
use function oihana\files\readSymlink;

echo readSymlink( '/var/www/current' ) ; // '/var/www/releases/42'
```

---

## See also

- [Creation](creation.md) — `makeFile`, `touchFile`.
- [Discovery](discovery.md) — `findFiles` (`followLinks` option).
- [Exceptions](../exceptions.md) — `FileException`.
- [Overview](README.md).
