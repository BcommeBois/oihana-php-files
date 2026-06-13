# Integrity

Two functions to check file **integrity** and **equality** (checksums, deduplication, archive-extraction verification).

- [`fileChecksum`](#filechecksum) — computes the hash of a file's contents.
- [`filesAreEqual`](#filesareequal) — tells whether two files have identical contents.

---

## `fileChecksum`

```php
fileChecksum( string $file , string $algorithm = 'sha256' ) : string
```

Computes the hash of a file's contents via `hash_file()` — the file is **not** loaded entirely into memory. Useful for integrity checks, deduplication, and verifying extracted archives.

- the source is validated with [`assertFile`](assertions.md#assertfile) (must exist and be readable);
- `$algorithm` must be one of `hash_algos()` (otherwise `FileException`), which avoids PHP's raw `ValueError`;
- **returns** the hash as lowercase hexadecimal.

| Case | Exception |
|---|---|
| Missing / unreadable file | `FileException` (via `assertFile`) |
| Unsupported algorithm | `FileException` |

```php
use function oihana\files\fileChecksum;

$sha256 = fileChecksum( '/data/report.pdf' ) ;         // default sha256
$md5    = fileChecksum( '/data/report.pdf' , 'md5' ) ; // explicit algorithm
```

---

## `filesAreEqual`

```php
filesAreEqual( string $a , string $b , string $algorithm = 'sha256' ) : bool
```

Tells whether two files have **identical contents**. The comparison is short-circuited for speed:

1. if both paths resolve to the **same file** on disk → returns `true` without reading;
2. if the **sizes differ** → returns `false` without hashing;
3. otherwise → compared via [`fileChecksum`](#filechecksum).

**Throws `FileException`** if either file is missing/unreadable or the algorithm is unsupported.

```php
use function oihana\files\filesAreEqual;

if ( filesAreEqual( '/data/a.bin' , '/backup/a.bin' ) )
{
    // contents match — safe to deduplicate
}
```

> 💡 **Extraction verification**: after [`unzip`](../archive/unzip.md) or [`untar`](../archive/untar.md), `filesAreEqual` confirms an extracted file matches its original.

---

## See also

- [Assertions](assertions.md#assertfile) — `assertFile`, used upstream.
- [Archive — unzip](../archive/unzip.md) / [untar](../archive/untar.md) — post-extraction verification.
- [Exceptions](../exceptions.md) — `FileException`.
- [Overview](README.md).
