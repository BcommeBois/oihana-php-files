# Compression (single file)

Compress and decompress a **single file** outside of tar archives, **streaming in chunks** (no subprocess, the file is never fully loaded into memory).

- [`gzipFile`](#gzipfile) / [`gunzipFile`](#gunzipfile) — gzip (DEFLATE), via `ext-zlib`.
- [`bzip2File`](#bzip2file) / [`bunzip2File`](#bunzip2file) — bzip2, via `ext-bz2`.

> 💡 To compress **multiple** files / directories, see the [tar](../archive/tar.md) and [zip](../archive/zip.md) archives. These helpers target the "one file → one compressed file" case.
>
> ⚠ `ext-zlib` and `ext-bz2` are declared under `suggest` in `composer.json`. If the extension is missing, the function throws an explicit `FileException`.

---

## `gzipFile`

```php
gzipFile(
    string  $source ,
    ?string $destination = null ,
    int     $level       = -1 ,
    bool    $overwrite   = true
) : string
```

Compresses `$source` with gzip. Default destination: `$source` + `.gz`. The `$level` ranges from `0` to `9`; `-1` (default) uses zlib's default level.

**Returns** the destination path.

| Case | Exception |
|---|---|
| Missing / unreadable source | `FileException` (via `assertFile`) |
| `ext-zlib` unavailable | `FileException` |
| Existing destination with `$overwrite = false` | `FileException` |
| Failure to open the destination | `FileException` |

```php
use function oihana\files\gzipFile;

gzipFile( '/var/log/app.log' ) ;                    // -> /var/log/app.log.gz
gzipFile( '/data/dump.sql' , '/data/dump.gz' , 9 ) ; // maximum level
```

---

## `gunzipFile`

```php
gunzipFile(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Decompresses a gzip file. Default destination: `$source` **without** its `.gz` suffix, or `$source` + `.out` when the suffix is absent.

```php
use function oihana\files\gunzipFile;

gunzipFile( '/var/log/app.log.gz' ) ; // -> /var/log/app.log
```

---

## `bzip2File`

```php
bzip2File(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Compresses `$source` with bzip2. Default destination: `$source` + `.bz2`. Unlike `gzipFile`, **no level** is exposed: `bzopen()` does not accept one in streaming mode.

```php
use function oihana\files\bzip2File;

bzip2File( '/data/dump.sql' ) ; // -> /data/dump.sql.bz2
```

---

## `bunzip2File`

```php
bunzip2File(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Decompresses a bzip2 file. Default destination: `$source` **without** its `.bz2` suffix, or `$source` + `.out` when the suffix is absent.

```php
use function oihana\files\bunzip2File;

bunzip2File( '/data/dump.sql.bz2' ) ; // -> /data/dump.sql
```

---

## See also

- [tar archives](../archive/tar.md) / [zip](../archive/zip.md) — multi-file compression.
- [Enums](../enums.md#compressiontype) — `CompressionType`.
- [Exceptions](../exceptions.md) — `FileException`.
- [Overview](README.md).
