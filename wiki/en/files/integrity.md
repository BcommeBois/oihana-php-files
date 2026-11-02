# Integrity

Four functions to check file **integrity** and **equality** (checksums, deduplication, archive-extraction verification), and to keep a checksum beside its file.

- [`fileChecksum`](#filechecksum) — computes the hash of a file's contents.
- [`filesAreEqual`](#filesareequal) — tells whether two files have identical contents.
- [`checksumLine` and `readChecksumLine`](#checksumline-and-readchecksumline) — write a checksum beside its file as `sha256sum` does, and read it back.

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

## `checksumLine` and `readChecksumLine`

```php
checksumLine( string $hash , string $filename ) : string
readChecksumLine( string $content , string $filename , string $algorithm = 'sha256' ) : ?string
```

A checksum is only worth something if it is kept: these two write it **beside the file**, in the format `sha256sum` writes, and read it back before the file is trusted.

**`checksumLine`** writes the line: the hash in lowercase, two spaces, the name, a line feed. `sha256sum -c` (or `md5sum -c`, `shasum -c`…) checks it by hand, without PHP. Give the name alone, with no directory: the line still checks once both files are copied elsewhere.

**`readChecksumLine`** reads it back, for one file:

- the hash must have the length of the algorithm's — 64 characters for `sha256` (the default, as in `fileChecksum`), 32 for `md5`, 128 for `sha512`;
- the binary mode of `sha256sum` (a space and a star before the name) is read too, and so are Windows line ends;
- **only a line naming this file counts**: a checksum file copied beside the wrong file does not vouch for it. A file listing several files (`SHA256SUMS`) is read line by line, and the first line naming this one is taken;
- **returns** the hash in lowercase, or `null` when no line names the file with a well-formed hash.

| Case | Result |
|---|---|
| No line names the file, or its hash has the wrong length | `null` |
| Unsupported algorithm | `FileException` |

```php
use function oihana\files\checksumLine;
use function oihana\files\fileChecksum;
use function oihana\files\readChecksumLine;

// When the file is written
file_put_contents( '/data/report.pdf.sha256' , checksumLine( fileChecksum( '/data/report.pdf' ) , 'report.pdf' ) ) ;

// Before it is read
$recorded = readChecksumLine( file_get_contents( '/data/report.pdf.sha256' ) , 'report.pdf' ) ;

if ( $recorded === null || $recorded !== fileChecksum( '/data/report.pdf' ) )
{
    // no checksum for this file, or the file changed since it was written
}
```

> ⚠️ `sha256sum` writes a name holding a line feed or a backslash another way (escaped, with a backslash before the line). Such a name is not supported.

---

## See also

- [Assertions](assertions.md#assertfile) — `assertFile`, used upstream.
- [Archive — unzip](../archive/unzip.md) / [untar](../archive/untar.md) — post-extraction verification.
- [Exceptions](../exceptions.md) — `FileException`.
- [Overview](README.md).
