# Enums catalogue

`oihana/php-files` exposes **18 enum classes** (typed constants) + **3 MIME traits** under `oihana\files\enums\`. They are **constant classes**, not native PHP `enum` types — they use the [`ConstantsTrait`](getting-started/dependencies.md#oihanaphp-reflect) which exposes `enum()`, `getAll()`, etc.

> 💡 Why constant classes rather than PHP 8.1 `enum`? Native `enum` does not allow **inheritance** or **composite constants** (arrays). Constant classes enable e.g. `FileMimeType::AI = ['application/postscript', 'application/illustrator']` (multiple MIME) — impossible with a native `enum`.

## Overview by category

| Category | Classes |
|---|---|
| **MIME types** | [`FileMimeType`](#filemimetype), [`ImageMimeType`](#imagemimetype), [`AudioMimeType`](#audiomimetype), [`VideoMimeType`](#videomimetype) |
| **File extensions** | [`FileExtension`](#fileextension), [`TarExtension`](#tarextension) |
| **Formats & compression** | [`CompressionType`](#compressiontype), [`ImageFormat`](#imageformat) |
| **Options (array keys)** | [`FindFilesOption`](#findfilesoption), [`FindFileOption`](#findfileoption), [`MakeFileOption`](#makefileoption), [`MakeDirectoryOption`](#makedirectoryoption), [`RecursiveFilePathsOption`](#recursivefilepathsoption), [`TarOption`](#taroption) |
| **Modes / domain enums** | [`FindMode`](#findmode) |
| **Structured results (keys)** | [`OwnershipInfo`](#ownershipinfo), [`TarInfo`](#tarinfo) |
| **Internal state** | [`CanonicalizeBuffer`](#canonicalizebuffer) |
| **Reusable traits** | [`ImageMimeTypeTrait`, `AudioMimeTypeTrait`, `VideoMimeTypeTrait`](#mime-traits) |

---

## MIME types

### `FileMimeType`

**Main catalogue** of MIME types — **56 constants** covering documents, images, audio, video, archives, specialised formats (`cbor`, `cose`, `cose.enc`).

Includes **composite** types (constant = array) for formats that may match multiple MIMEs: e.g. `FileMimeType::AI = ['application/postscript', 'application/illustrator']`.

```php
use oihana\files\enums\FileMimeType;

FileMimeType::PDF  ;  // 'application/pdf'
FileMimeType::JSON ;  // 'application/json'
FileMimeType::CBOR ;  // 'application/cbor'

// Multi-MIME
FileMimeType::AI ;   // ['application/postscript', 'application/illustrator']
```

Utility methods (inherited from `ConstantsTrait`): `getAll()`, `enum()`, etc.

### `ImageMimeType`

**14 constants** for images: `AVIF`, `BMP`, `CUR`, `GIF`, `HEIC`, `HEIF`, `ICO`, `JPEG`, `JPG`, `PNG`, `SVG`, `TIF`, `TIFF`, `WEBP`.

Delegates its constants to the [`ImageMimeTypeTrait`](#mime-traits) — lets any custom enum class inherit them.

```php
ImageMimeType::JPG  ;  // 'image/jpeg'
ImageMimeType::SVG  ;  // 'image/svg+xml'
ImageMimeType::AVIF ;  // 'image/avif'
```

### `AudioMimeType`

**7 constants**: `AAC`, `FLAC`, `M4A`, `MP3`, `OGG`, `WAV`, `WMA`. Via [`AudioMimeTypeTrait`](#mime-traits).

### `VideoMimeType`

**10 constants**: covers MP4, WebM, AVI, MKV, MOV, etc. Via [`VideoMimeTypeTrait`](#mime-traits).

### MIME traits

The **3 traits** (`AudioMimeTypeTrait`, `ImageMimeTypeTrait`, `VideoMimeTypeTrait`) decouple constant declarations from the choice of class. Enables **composition**:

```php
class MediaMimeType
{
    use ConstantsTrait , AudioMimeTypeTrait , VideoMimeTypeTrait ;
}

MediaMimeType::MP3 ; // 'audio/mpeg'
MediaMimeType::MP4 ; // 'video/mp4'
```

---

## File extensions

### `FileExtension`

**89 constants** — the largest class. Covers all standard extensions (images, audio, video, documents, archives, source code) **with the leading dot**:

```php
FileExtension::PNG    ;  // '.png'
FileExtension::TAR_GZ ;  // '.tar.gz'
FileExtension::CBOR   ;  // '.cbor'
FileExtension::COSE   ;  // '.cose'
FileExtension::ENCRYPTED ; // '.enc'
```

#### Utility methods

| Method | Role |
|---|---|
| `getFromMimeType( string $mimeType ): array\|string\|null` | Extension(s) matching a given MIME. |
| `getMimeType( string $extension ): string\|array\|null` | MIME matching an extension (inverse). |
| `getMultiplePartExtensions( ?array $customs = [] ): array` | List of compound extensions (`.tar.gz`, `.blade.php`...). Used by [`getBaseFileName`](files/system.md#getbasefilename) and [`getFileExtension`](files/system.md#getfileextension). |
| `resetCaches(): void` | Clears internal caches (extension ↔ MIME mappings). |

### `TarExtension`

**12 constants** specific to tar archives:

```
.tar, .tar.gz, .tar.bz2, .tar.xz, .tar.lz, .tar.lzo, .tar.lzma, .tar.zst, .tar.Z, .tbz2, .txz, .tgz
```

#### Utility methods

| Method | Role |
|---|---|
| `getExtensionForCompression( string $compression ): string` | Returns the full extension for a compression type (`gzip` → `.tar.gz`). Throws `UnsupportedCompressionException`. |
| `getCompressionExtension( string $compression ): string` | Returns only the compression suffix (`gzip` → `.gz`). |

---

## Formats & compression

### `CompressionType`

**8 constants** + 3 utility methods:

| Constant | Value |
|---|---|
| `NONE` | `'none'` |
| `GZIP` | `'gzip'` |
| `BZIP2` | `'bzip2'` |
| `ZIP` | `'zip'` |
| `LZ4` | `'lz4'` |
| `LZMA` | `'lzma'` |
| `XZ` | `'xz'` |
| `ZSTD` | `'zstd'` |

> ⚠ The 8 constants are defined but **only `NONE`, `GZIP` and `BZIP2`** are supported by the [`tar`](archive/tar.md) / [`untar`](archive/untar.md) functions. The others throw `UnsupportedCompressionException`.

| Method | Returns |
|---|---|
| `getDefault()` | `'gzip'` |
| `getFastCompressionTypes()` | `[NONE, LZ4, ZSTD]` |
| `getHighRatioCompressionTypes()` | `[LZMA, XZ, BZIP2]` |

### `ImageFormat`

**14 image formats** **without MIME prefix** (just the extension without dot) — `avif`, `bmp`, `cur`, `gif`, `heic`, `heif`, `ico`, `jpeg`, `jpg`, `png`, `svg`, `tif`, `tiff`, `webp`.

Used as a **key** in format → MIME mappings, notably by [`getImageMimeType`](files/mime.md#getimagemimetype).

---

## Options (array keys)

These classes define the **keys accepted** by functions taking an options array. Convention: **singular** class name ending in `Option` (capitalised).

### `FindFilesOption`

8 keys for [`findFiles`](files/discovery.md#findfiles): `FILTER`, `FOLLOW_LINKS`, `INCLUDE_DOTS`, `MODE`, `ORDER`, `PATTERN`, `RECURSIVE`, `SORT`.

### `FindFileOption`

⚠ **Near-duplicate of `FindFilesOption`** (same 8 keys, same constants class). To watch — possible technical debt to clarify.

### `MakeFileOption`

9 keys for [`makeFile`](files/creation.md#makefile): `APPEND`, `CONTENT`, `FILE`, `FORCE`, `GROUP`, `LOCK`, `OVERWRITE`, `OWNER`, `PERMISSIONS`.

> ⚠ Not to be confused with [`MakeFileOptions`](options/make-file-options.md#makefileoptions) (plural, DTO class).

### `MakeDirectoryOption`

5 keys for [`makeDirectory`](files/creation.md#makedirectory): `GROUP`, `OWNER`, `PATH`, `PERMISSIONS`, `RECURSIVE`.

### `RecursiveFilePathsOption`

4 keys for [`recursiveFilePaths`](files/discovery.md#recursivefilepaths): `EXCLUDES`, `EXTENSIONS`, `MAX_DEPTH`, `SORTABLE`.

### `TarOption`

6 keys for [`tar`](archive/tar.md#tar) / [`tarDirectory`](archive/tar.md#tardirectory) / [`untar`](archive/untar.md#untar): `DRY_RUN`, `EXCLUDE`, `FILTER`, `KEEP_PERMISSIONS`, `OVERWRITE`, `METADATA`.

---

## Modes / domain enums

### `FindMode`

3 modes for [`findFiles`](files/discovery.md#findfiles):

| Constant | Value | Effect |
|---|---|---|
| `BOTH` | `'both'` | Files + directories |
| `FILES` | `'files'` | Files only (default) |
| `DIRS` | `'dirs'` | Directories only |

---

## Structured results (keys)

These classes define the **keys of arrays returned** by some functions.

### `OwnershipInfo`

4 keys of the array returned by [`getOwnershipInfos`](files/system.md#getownershipinfos) — `GROUP`, `GID`, `OWNER`, `UID`.

> ⚠ Not to be confused with [`OwnershipInfos`](options/make-file-options.md#ownershipinfos) (plural, DTO class).

### `TarInfo`

6 keys of the array returned by [`tarFileInfo`](archive/tar.md#tarfileinfo): `COMPRESSION`, `EXTENSION`, `FILE_COUNT`, `IS_VALID`, `TOTAL_SIZE`, `MIME_TYPE`.

---

## Internal state

### `CanonicalizeBuffer`

**Static LRU buffer** used by [`canonicalizePath`](path/joining-and-normalizing.md#canonicalizepath) to memoise already-canonicalised paths.

| Constant / property | Value | Role |
|---|---|---|
| `CLEANUP_THRESHOLD` | `1250` | Cleanup trigger threshold. |
| `CLEANUP_SIZE` | `1000` | Target size after cleanup (the 1000 most recent are kept). |
| `$buffer` (static array) | `[]` | Map `path → canonical`. |
| `$bufferSize` (static int) | `0` | Counter. |

> 💡 You can **inspect or wipe** this buffer for debug: `CanonicalizeBuffer::$buffer = []`. Useful in tests for deterministic measurements.

---

## Naming conventions

Recap of the two recurring traps:

| `Singular` (keys class) | `Plural` (DTO class extending `Options`) |
|---|---|
| `oihana\files\enums\MakeFileOption` | `oihana\files\options\MakeFileOptions` |
| `oihana\files\enums\OwnershipInfo` | `oihana\files\options\OwnershipInfos` |

→ The first are **string constants** (keys of an associative array). The second are **typed objects** with public properties.

## See also

- [Options pattern](options/README.md) — the abstract `Options` class used by plural DTOs.
- [Concrete options](options/make-file-options.md) — `MakeFileOptions` and `OwnershipInfos`.
- [Exceptions](exceptions.md) — `UnsupportedCompressionException` thrown by `TarExtension::*` and `CompressionType` beyond gzip/bzip2/none.
- [Tips](tips.md) — pitfalls and conventions.
- [English TOC](README.md).
