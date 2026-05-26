# Archives — `oihana\files\archive\tar`

The `oihana\files\archive\tar` namespace bundles **9 standalone functions** to create, extract and inspect **tar** archives (with or without `gzip` / `bzip2` compression).

> 💡 Implementation based on native **`PharData`** (the `ext-phar` extension, enabled by default in PHP). No external dependencies.

## Catalogue

| Category | Functions |
|---|---|
| **Creation** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) |
| **Extraction** | [`untar`](untar.md#untar) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) |

## Supported formats

| Format       | Recognised extensions          | Compression       | Write mode |
|--------------|--------------------------------|-------------------|------------|
| **tar**      | `.tar`                         | none              | native     |
| **tar.gz**   | `.tar.gz`, `.tgz`              | gzip              | native (`ext-zlib`) |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`            | bzip2             | native (`ext-bz2`)  |

The [`CompressionType`](../enums.md#compressiontype) enum lists the canonical values (`gz`, `bz2`, `none`).

## Principles

1. **No subprocesses.** Everything goes through `PharData` — no `exec('tar ...')`. Pros: portable, scriptable, testable. Cons: size limits (PHP memory/time).
2. **Empty directories preserved.** Unlike a naive `cp -r`, `tar` preserves empty subdirectories via `addEmptyDir`.
3. **Extraction safety.** `untar` detects **path traversal** attempts (`..`) in archive entry names — protection against *Zip Slip* / *Tar Slip* attacks.
4. **Multi-level validation.** `hasTarExtension` (fast, name only), `hasTarMimeType` (reads first bytes via `finfo`), `validateTarStructure` (parse + iteration via `PharData`).

## Typical use case

```php
use function oihana\files\archive\tar\{ tarDirectory , untar , tarFileInfo } ;
use oihana\files\enums\CompressionType;

// 1. Create a compressed archive from a directory
$archive = tarDirectory(
    '/var/www/site' ,
    CompressionType::GZIP ,
    '/backups/site.tar.gz' ,
) ;

// 2. Inspect
$info = tarFileInfo( $archive ) ;
echo "Files: {$info['fileCount']}, size: {$info['totalSize']} bytes" ;

// 3. Extract elsewhere (with path-traversal protection)
untar( $archive , '/tmp/restored' ) ;
```

## ⚠ Known limitations

- **`validateTarStructure` does not support compressed tars** — must decompress first (which `untar` does internally).
- **Symlinks**: `PharData` serialises them as symlinks — extraction recreates the symlink, **not the target**. Keep in mind for portable archives.
- **Large archives** (> a few GB): `PharData` loads indexes in memory — prefer CLI `tar` or streaming tools for very large volumes.
- **Real compression**: `PharData::compress()` may fail silently if the corresponding extension (`ext-zlib`, `ext-bz2`) is not loaded. `tar()` then throws `UnsupportedCompressionException`.

## See also

- [Creating an archive](tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, `tarIsCompressed`.
- [Extracting an archive](untar.md) — `untar` and validation functions.
- [Enums](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`, `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — Phar helpers used internally (`getPharCompressionType`, `preservePharFilePermissions`).
- [English TOC](../README.md).
