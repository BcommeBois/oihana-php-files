# Archives — `oihana\files\archive`

The `oihana\files\archive` namespace bundles two symmetric toolkits of standalone functions:

- **`oihana\files\archive\tar`** — **tar** archives (with or without `gzip` / `bzip2` compression), built on native **`PharData`** (`ext-phar`).
- **`oihana\files\archive\zip`** — **zip** archives, built on native **`ZipArchive`** (`ext-zip`).

Both APIs are deliberately **parallel**: `zip`/`unzip` mirror `tar`/`untar`, `zipDirectory` mirrors `tarDirectory`, and so on.

## Catalogue

| Category | tar (`PharData`) | zip (`ZipArchive`) |
|---|---|---|
| **Creation** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) | [`zip`](zip.md#zip), [`zipDirectory`](zip.md#zipdirectory) |
| **Extraction** | [`untar`](untar.md#untar) | [`unzip`](unzip.md#unzip) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) | [`zipFileInfo`](zip.md#zipfileinfo) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) | [`assertZip`](unzip.md#assertzip), [`hasZipExtension`](unzip.md#haszipextension), [`hasZipMimeType`](unzip.md#haszipmimetype), [`validateZipStructure`](unzip.md#validatezipstructure) |

> ℹ️ There is no `zipIsCompressed`: a zip is a container whose compression is decided **per entry** (DEFLATE or STORE), so a global "compressed or not" notion is meaningless.

## Supported formats

| Format       | Recognised extensions          | Compression       | Backend |
|--------------|--------------------------------|-------------------|---------|
| **tar**      | `.tar`                         | none              | `PharData` |
| **tar.gz**   | `.tar.gz`, `.tgz`              | gzip (`ext-zlib`) | `PharData` |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`            | bzip2 (`ext-bz2`) | `PharData` |
| **zip**      | `.zip`                         | DEFLATE or STORE (per entry) | `ZipArchive` (`ext-zip`) |

The [`CompressionType`](../enums.md#compressiontype) enum lists the canonical values (`gzip`, `bzip2`, `none`, `zip`).

## Shared principles

1. **No subprocess.** Everything goes through `PharData` / `ZipArchive` — no `exec('tar ...')` / `exec('zip ...')`. Portable, scriptable, testable. Downside: size limits (PHP memory/time).
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

- **Large archives** (> a few GB): `PharData` / `ZipArchive` load indexes in memory — prefer streaming CLI tools for very large volumes.
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
