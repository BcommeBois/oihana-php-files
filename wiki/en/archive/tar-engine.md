# How a tar archive gets built, and extracted

[`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) and [`untar`](untar.md#untar) hand the
work to the system `tar` when they find a **GNU tar**, and fall back to `PharData` otherwise.

The archives are the same either way — same entries, same names, interchangeable with anything
written by an earlier version of this library — and so are the extracted trees. Nothing changes
in the API, and nothing has to be configured.

> The engine choice on writing dates from **1.3.0**, the one on extraction from **1.5.0**, and a
> defect forced it: in between, the library could no longer read back some of the archives it had
> just written. [See below](#and-on-extraction-it-is-not-about-speed).

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

## And on extraction, it is not about speed

On extraction `PharData` holds up perfectly well: **0.98 s against 0.82 s** for GNU tar on the
same 57 MB archive. If extraction changed engine all the same, it was for another reason, and it
comes down to one line — `PharData::decompress()` has `PharData` **write** a `.tar` before
reading it.

So the 100-byte limit above, which is a **writing** limit, applied to **reading** a compressed
archive as well. Measured on 2026-10-24: a `.tar.gz` holding a 154-byte name refused to extract,
with `filename ... is too long for tar file format`, while the **same archive uncompressed**
extracted without trouble.

Which is to say: since 1.3.0, `tar()` wrote through GNU tar archives that `untar()` could no
longer read back. And since a stock WordPress holds such a path, a backup could be written and not
restored — while the message itself read as a damaged archive.

Three further costs came from the same rebuild:

| Cost | Measurement |
|---|---|
| Memory | **×2.26** of a 60 MB archive, **×2.04** of a 200 MB one — some 5 GB for a 2.45 GB backup |
| Disk | an **uncompressed** copy written beside the archive for the length of the extraction |
| Repetition | extracting the same compressed archive twice in one process failed, the `.tar` staying registered in an internal `Phar` list |

The last one is avoided rather than cleaned up: [`untarUniqueAlias`](#the-engine-functions) gives
the archive a uniquely named hard link — no bytes copied — whose name the `.tar` inherits.
`Phar::unlinkArchive()`, the documented way to clear that list, **corrupts the Zend heap** when
called from `untar()`.

## Which binary, and why not all of them

| Platform | Engine | Why |
|---|---|---|
| **Debian / Ubuntu / most Linux** | GNU tar | Stores names as raw bytes, exactly as `PharData` does |
| **macOS** | `PharData` | `/usr/bin/tar` is `bsdtar` — unless `gtar` is installed, [see below](#on-a-mac) |
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

### On a Mac

```shell
brew install gnu-tar
```

Homebrew installs a real GNU tar under the name `gtar`, leaving the system `/usr/bin/tar`
untouched — in `/opt/homebrew/bin/gtar` on Apple Silicon, in `/usr/local/bin/gtar` on Intel. Both
paths are searched: there is nothing to configure, the binary engine takes over.

This is a development convenience — archives built in seconds rather than minutes, and a
[test suite](../testing.md#running-the-tests) that no longer skips — not a change of doctrine: a
Mac remains a development machine rather than a backup target.

## When the binary engine declines

The engine is chosen **before anything is written**, and never revisited afterwards. It steps
aside when:

- there is no GNU tar;
- **on writing only**, several paths are archived together and share no common parent — one `tar`
  invocation covers one base directory, and a compressed archive cannot be appended to;
- the compressor is missing. `tar` neither compresses nor decompresses: it pipes through `gzip`,
  `bzip2`, `xz` or `zstd`, where `PharData` uses the PHP extensions. A host with `ext-zlib` and no
  `gzip` program therefore has a perfectly good GNU tar that cannot open a `.tar.gz`, and keeps the
  engine that works for it.

For `xz` and `zstd` there is no fallback: `PharData` knows neither. Those two formats exist **only**
through the system `tar`, on writing as on reading, and both ends say so plainly rather than letting
`Phar` speak about a format nobody mentioned. It is the same cross-host trap as an Argon2id archive
on a host without `ext-sodium`.

> ⚠ `proc_open()` given an environment **replaces** the process's own. Since `tar` spawns its
> compressor through a shell, a child with no `PATH` fell back to the shell's default —
> `/usr/bin:/bin` on a Mac — and found `gzip` while missing an `xz` installed elsewhere, on a host
> where `tarCompressorExists()` had just found it on **this** process's `PATH`. The check looked at
> one environment and the work ran in another. Fixed in 1.5.0 by `tarProcessEnvironment()`.

A binary that is present and **fails** raises instead of falling back. It is reporting something
`PharData` would meet as well — a full disk, a path it may not read — and retrying in PHP would
spend minutes reaching the same wall with the original reason discarded.

## What PharData cannot write

Two things, and in both cases it **refuses** rather than writing something else.

**A name longer than 100 bytes** — the `ustar` limit seen above.

**A symbolic link.** Phar exposes no way to write a link entry: there is no `addSymlink`, and the
`ustar` type flag is out of reach of the API. Before 1.6.0 this branch stored something else in its
place — an empty directory for a link to a directory, the target's bytes for a link to a file, a
`phar` error for a dangling link. The same tree therefore gave one archive through the binary and
another through `PharData`, without a word. `tar()` now reads the tree **before anything is
written** and refuses, naming the link, with the three ways out: install GNU tar, archive the paths
one at a time so it can be used, or go through [`zip`](zip.md#zip) — all three keep the link as a
link.

> ⚠ The third case in the list above is the trap: **several paths with no common parent** send the
> work to `PharData` on any host, GNU tar installed or not. A link archived on its own is kept; the
> same link archived alongside a path from another parent is refused.

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

## The engine functions

Rarely called directly — `tar()` and `untar()` use them — but public, and useful in a health check.

| Function | Answer |
|---|---|
| [`tarBinary()`](#asking-which-engine-is-in-place) | the GNU tar that was found, or `null` |
| `tarWritableCompressions()` | what **this host** can write — `['none','gzip','bzip2']` with no binary, plus `xz` and `zstd` with one |
| `tarProcessEnvironment()` | the environment every `tar` child is given: `LC_ALL=C` and this process's `PATH` |
| `tarBinaryIsUsable( $path )` | is this binary an executable GNU tar — asked by running `--version` |
| `tarBinaryHandles( $entries , $compression )` | can this binary **write** that archive |
| `tarCompressorExists( $compression )` | is the compression program present |
| `tarCompressionOfFile( $archive )` | how this archive is compressed, read from its **bytes** rather than its name |
| `untarBinaryHandles( $archive )` | can this binary **extract** that archive, decompressor included |
| `untarEntries( $binary , $archive )` | what the archive holds — names, sizes — in one streaming pass |
| `untarAssertExtractable( $entries , $output , $overwrite , $max )` | the guards both engines share: traversal, overwrite, bomb |
| `untarWithBinary( $binary , $archive , $output , $permissions )` | the extraction itself |
| `untarUniqueAlias( $archive , $compression )` | a uniquely named hard link, so `PharData` can decompress twice |

## Forcing a choice

`OIHANA_TAR_BINARY` overrides the search:

| Value | Effect |
|---|---|
| unset | Look for a GNU tar in the usual places |
| a path | Use that binary, if it is a usable GNU tar |
| empty | Build in PHP with `PharData` |

The empty form is what this library's own test suite uses to run the same fixtures through both
engines and compare what comes out — on writing as on extraction: the whole suite runs twice, with
and without a binary.

## See also

- [Creating a tar archive](tar.md)
- [Extracting a tar archive](untar.md)
- [Archives overview](README.md)
