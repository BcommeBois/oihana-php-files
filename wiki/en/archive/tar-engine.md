# How a tar archive gets built

[`tar`](tar.md#tar) and [`tarDirectory`](tar.md#tardirectory) hand the work to the system
`tar` when they find a **GNU tar**, and build the archive in PHP with `PharData` otherwise.

The archives are the same either way — same entries, same names, interchangeable with anything
written by an earlier version of this library. Nothing changes in the API, and nothing has to
be configured.

## Why

`PharData` writes tar archives in pure PHP. On a small tree that is invisible. On a real one it
is not:

| Tree | GNU tar | `PharData` |
|---|---|---|
| 2.8 MB / 365 files | 0.03 s | 0.60 s |
| 96 MB / 7 554 files | **2.1 s** | **311.8 s** |

The same archive comes out of both. The gap is not constant — it grows with size, because
`PharData` writes the tar and then reads all of it back to compress it.

There is a correctness side too. `PharData` emits the `ustar` format, whose header holds a
155-byte directory prefix and a 100-byte final component. A **path** may therefore be long, but
a single **filename** may not: past 100 bytes `PharData` refuses the archive rather than
truncating the name.

That is not a corner case. A stock WordPress installation has one such file, 103 bytes, inside
a widely used plugin — enough that the whole site could not be archived. GNU tar writes it
without comment, using the extensions the format grew for exactly this.

## Which binary, and why not all of them

| Platform | Engine | Why |
|---|---|---|
| **Debian / Ubuntu / most Linux** | GNU tar | Stores names as raw bytes, exactly as `PharData` does |
| **macOS** | `PharData` | `/usr/bin/tar` is `bsdtar` |
| **Windows** | `PharData` | `tar.exe` is `bsdtar` as well |
| **Alpine / BusyBox** | `PharData` | Reduced implementation, fidelity unverified |

Only GNU tar is accepted, and it is asked rather than assumed: the binary is run with
`--version` and has to say so.

The reason is filename encoding. Verified on a tree of accented, CJK, quoted and spaced names,
an empty directory, a symlink and a deep path, GNU tar and `PharData` produce **identical entry
lists**. `bsdtar` does not: on macOS it normalises filenames to Unicode **NFD**, so `été.txt`
enters the archive as `e´te´.txt`. An archive written on a Mac and restored on a server would
carry different names than the originals — for a site with accented media, different URLs.

Slower and identical beats faster and subtly different.

## When the binary engine declines

The engine is chosen **before anything is written**, and never revisited afterwards. It steps
aside when:

- there is no GNU tar;
- several paths are archived together and share no common parent — one `tar` invocation covers
  one base directory, and a compressed archive cannot be appended to;
- the compressor is missing. `tar` does not compress: it pipes through `gzip`, `bzip2` or `xz`,
  where `PharData` uses the PHP extensions. A host with `ext-bz2` and no `bzip2` program keeps
  the engine that works for it.

A binary that is present and **fails** raises instead of falling back. It is reporting something
`PharData` would meet as well — a full disk, a path it may not read — and retrying in PHP would
spend minutes reaching the same wall with the original reason discarded.

## Asking which engine is in place

```php
use function oihana\files\archive\tar\tarBinary ;

$binary = tarBinary() ;

echo $binary === null
    ? 'archives are built in PHP — slow on large trees'
    : sprintf( 'archives are built by %s' , $binary ) ;
```

Worth surfacing somewhere an operator actually looks — a health check, a `doctor` command, a
startup log. A host that falls back has archives that may stop fitting in the window they are
given, and nothing else will mention it. A page like this one is read once; a health check is
read every day.

`tarBinary()` caches its answer; pass `refresh: true` to look again.

## Forcing a choice

`OIHANA_TAR_BINARY` overrides the search:

| Value | Effect |
|---|---|
| unset | Look for a GNU tar in the usual places |
| a path | Use that binary, if it is a usable GNU tar |
| empty | Build in PHP with `PharData` |

The empty form is what this library's own test suite uses to run the same fixtures through both
engines and compare what comes out.

## See also

- [Creating a tar archive](tar.md)
- [Extracting a tar archive](untar.md)
- [Archives overview](README.md)
