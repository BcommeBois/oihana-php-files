# Archives — `oihana\files\archive`

The `oihana\files\archive` namespace bundles two symmetric toolkits of standalone functions:

- **`oihana\files\archive\tar`** — **tar** archives (with or without `gzip` / `bzip2` compression), built by the system **GNU tar** where there is one, and by native **`PharData`** (`ext-phar`) otherwise. See [how a tar archive gets built](tar-engine.md).
- **`oihana\files\archive\zip`** — **zip** archives, built on native **`ZipArchive`** (`ext-zip`).

Both APIs are deliberately **parallel**: `zip`/`unzip` mirror `tar`/`untar`, `zipDirectory` mirrors `tarDirectory`, and so on.

## Catalogue

| Category | tar (`PharData`) | zip (`ZipArchive`) |
|---|---|---|
| **Creation** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory), [`tarBinary`](tar-engine.md) | [`zip`](zip.md#zip), [`zipDirectory`](zip.md#zipdirectory) |
| **Extraction** | [`untar`](untar.md#untar) | [`unzip`](unzip.md#unzip) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) | [`zipFileInfo`](zip.md#zipfileinfo) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) | [`assertZip`](unzip.md#assertzip), [`hasZipExtension`](unzip.md#haszipextension), [`hasZipMimeType`](unzip.md#haszipmimetype), [`validateZipStructure`](unzip.md#validatezipstructure) |

> ℹ️ There is no `zipIsCompressed`: a zip is a container whose compression is decided **per entry** (DEFLATE or STORE), so a global "compressed or not" notion is meaningless.

## Supported formats

| Format       | Recognised extensions          | Compression       | Backend |
|--------------|--------------------------------|-------------------|---------|
| **tar**      | `.tar`                         | none              | GNU tar, else `PharData` |
| **tar.gz**   | `.tar.gz`, `.tgz`              | gzip (`ext-zlib`) | GNU tar, else `PharData` |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`            | bzip2 (`ext-bz2`) | GNU tar, else `PharData` |
| **zip**      | `.zip`                         | DEFLATE or STORE (per entry) | `ZipArchive` (`ext-zip`) |

The [`CompressionType`](../enums.md#compressiontype) enum lists the canonical values (`gzip`, `bzip2`, `none`, `zip`).

## Shared principles

1. **No subprocess, with one measured exception.** `zip` goes through `ZipArchive`, and so did `tar` through `PharData` — portable, scriptable, testable, at the cost of size limits. For tar that cost turned out to be prohibitive: 311.8 seconds against 2.1 on the same 96 MB tree, and an outright refusal of any filename past 100 bytes. So **tar creation** uses the system GNU tar when one is there, and falls back to `PharData` everywhere else, producing the same archives either way. Everything else — extraction, inspection, validation, and all of `zip` — is still pure PHP. See [how a tar archive gets built](tar-engine.md).
2. **Empty directories preserved.** Both `tar` and `zip` preserve empty sub-directories via `addEmptyDir`.
3. **Extraction safety.** `untar` and `unzip` detect **path traversal** (`..`) attempts — *Zip Slip* / *Tar Slip* protection. `unzip` adds **bomb guards** (`maxEntries`, `maxSize`).
4. **Multi-level validation.** Extension (fast) → MIME via `finfo` → structure (parse).

## Typical use case

```php
use function oihana\files\archive\zip\{ zipDirectory , unzip , zipFileInfo } ;
use oihana\files\enums\ZipOption;

// 1. Create an archive from a directory
$archive = zipDirectory( '/var/www/site' , null , '/backups/site.zip' ) ;

// 2. Inspect it
$info = zipFileInfo( $archive ) ;
echo "Files: {$info['fileCount']}, size: {$info['totalSize']} bytes" ;

// 3. Extract elsewhere, with bomb guards
unzip( $archive , '/tmp/restored' , [
    ZipOption::MAX_SIZE => 200 * 1024 * 1024 ,
]) ;
```

## ⚠ Known limits

- **Large archives** (> a few GB): `ZipArchive`, and `PharData` where it is still the engine, load indexes in memory. Creating a tar no longer has that ceiling where a GNU tar is available — [check which engine is in place](tar-engine.md#asking-which-engine-is-in-place).
- **Symlinks (tar)**: `PharData` recreates the symlink, not the target.
- **`validateTarStructure`** does not support compressed tars (decompress first).
- **Windows permissions (zip)**: a zip without `OPSYS_UNIX` attributes has no mode to restore — `unzip(..., keepPermissions: true)` then leaves the default mode.

## See also

- [Create a tar](tar.md) · [Extract a tar](untar.md)
- [Create a zip](zip.md) · [Extract a zip](unzip.md)
- [Enumerations](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`, `ZipOption`, `ZipInfo`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`, `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — Phar helpers used internally by tar.
- [EN index](../README.md).
