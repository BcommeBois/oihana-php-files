# Oihana PHP Files OpenSource library - Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.2.0] - 2026-06-14

Backward-compatible release with two themes:

- a new `oihana\files\archive\zip` toolkit mirroring the existing `archive\tar` helpers;
- a "file toolkit complements" batch of standalone helpers — single-file copy/move
  (`copyFile`/`moveFile`/`renameFile`), whole-file read (`getFileContent`), atomic write
  (`writeFileAtomic`), `touchFile`, integrity (`fileChecksum`/`filesAreEqual`), single-file
  gzip/bzip2 (de)compression, file-size helpers (`getFileSize`/`formatFileSize`), disk-space
  helpers (`getFreeDiskSpace`/`getTotalDiskSpace`/`getDiskUsage`) and symbolic-link helpers
  (`createSymlink`/`isSymlink`/`readSymlink`), plus a shared `getMimeType` primitive.

It also deprecates `FindFileOption` in favour of `FindFilesOption`. No breaking changes:
existing files written by earlier versions and all public APIs remain compatible.

### Added

- **MIME primitive** — `oihana\files\getMimeType( string $file ): ?string`,
  the low-level `finfo` detector (returns `null` on failure, never throws)
  shared by the MIME helpers.
- **Single-file compression** — `oihana\files\gzipFile`/`gunzipFile` (via `ext-zlib`) and
  `oihana\files\bzip2File`/`bunzip2File` (via `ext-bz2`): chunked, streaming, no subprocess.
  `ext-zlib`/`ext-bz2` added to `suggest`.
- **Disk space** — `oihana\files\getFreeDiskSpace`, `oihana\files\getTotalDiskSpace` and
  `oihana\files\getDiskUsage` (typed wrappers around `disk_free_space`/`disk_total_space`),
  handy as a pre-flight guard before extracting archives.
- **Symbolic links** — `oihana\files\createSymlink` (with overwrite, allows dangling targets),
  `oihana\files\isSymlink`, and `oihana\files\readSymlink`.
- **Touch** — `oihana\files\touchFile( string $file , ?int $mtime = null , ?int $atime = null , bool $createDirectory = true )`,
  a typed `touch` wrapper that creates the file (and parent) on demand.
- **Atomic write** — `oihana\files\writeFileAtomic( string $file , string $content , int $permissions = 0644 )`,
  a temp-then-`rename` writer so concurrent readers never observe a partial file.
- **File size** — `oihana\files\getFileSize( string $file )` (typed wrapper around
  `filesize`) and `oihana\files\formatFileSize( int $bytes , int $precision = 2 )`
  (human-readable, base-1024 `B`…`PB`, units from the new `oihana\files\enums\FileSizeUnit`).
  Complements `tarFileInfo`/`zipFileInfo` `totalSize`.
- **Whole-file read** — `oihana\files\getFileContent( ?string $file , ?int $maxBytes = null )`,
  the read counterpart of `makeFile` (returns the full content as a string, with an
  optional defensive size cap).
- **File integrity** — `oihana\files\fileChecksum( string $file , string $algorithm = 'sha256' )`
  (streamed `hash_file`, validated algorithm) and
  `oihana\files\filesAreEqual( string $a , string $b , string $algorithm = 'sha256' )`
  (same-file / size short-circuits, then checksum comparison).
- **Single-file copy/move** — `oihana\files\copyFile`, `oihana\files\moveFile`
  and `oihana\files\renameFile` (overwrite control, directory destination,
  on-demand parent creation, typed `FileException`/`DirectoryException`,
  cross-filesystem move fallback). Complements the directory-level
  `copyFilteredFiles`.
- **MIME helper** — `oihana\files\hasMimeType( string $filePath , string|array $mimeTypes )`,
  a boolean substring-match MIME-type check (accepts a single type or a list) factored
  out of the tar/zip detection helpers.
- **Zip detection & validation** — `oihana\files\archive\zip\hasZipExtension`,
  `oihana\files\archive\zip\hasZipMimeType`, `oihana\files\archive\zip\validateZipStructure`,
  `oihana\files\archive\zip\assertZip`, `oihana\files\archive\zip\zipFileInfo`.
- **Zip creation** — `oihana\files\archive\zip\zip` (files/dirs, preserved root,
  empty directories, DEFLATE/STORE per-entry compression) and
  `oihana\files\archive\zip\zipDirectory` (exclude / filter / metadata),
  mirroring the `archive\tar` creation helpers.
- **Zip extraction** — `oihana\files\archive\zip\unzip` (dry-run, overwrite control,
  optional Unix-permission restore via `keepPermissions`, Zip Slip and
  decompression-bomb guards via `maxEntries` / `maxSize`),
  mirroring `oihana\files\archive\tar\untar`.
- **Directory staging helper** — `oihana\files\copyFilteredFilesWithMetadata`,
  the filtered-copy + optional `.metadata.json` + non-empty guard shared by
  `tarDirectory` and `zipDirectory`.
- **Enums** — `oihana\files\enums\ZipOption`, `oihana\files\enums\ZipInfo`.

### Changed

- `oihana\files\archive\tar\hasTarMimeType` and `oihana\files\archive\zip\hasZipMimeType`
  now delegate to `oihana\files\hasMimeType` (no behavior change).
- `oihana\files\hasMimeType`, `images\getImageMimeType`, `archive\tar\tarFileInfo`
  and `archive\zip\zipFileInfo` now delegate MIME detection to
  `oihana\files\getMimeType` (no behavior change).
- `oihana\files\archive\tar\tarDirectory` now delegates its staging step to
  `oihana\files\copyFilteredFilesWithMetadata` (no behavior change).
- `composer.json` now requires `ext-zip`.

### Deprecated

- `oihana\files\enums\FindFileOption` is deprecated in favour of
  `oihana\files\enums\FindFilesOption` (the one used by `findFiles`). It is now a
  value-identical alias whose constants mirror `FindFilesOption`, and will be
  removed in a future major release.

### Fixed

- Failure paths no longer emit a stray native `E_WARNING` before throwing their
  typed exception. `makeDirectory`, `makeTimestampedDirectory`, `makeFile`,
  `getFileLinesGenerator` and `archive\zip\zip` now `@`-suppress their fallible
  native call (`mkdir`/`chmod`/`fopen`/`ZipArchive::open`) — the error is already
  reported by the `DirectoryException`/`FileException` that follows. This keeps the
  warning from breaking strict downstream test suites (`failOnWarning=true`) that
  exercise these error paths, and aligns these calls with the `@`-suppression
  already used elsewhere in the library (e.g. `makeTemporaryDirectory`, `writeFileAtomic`).

## [1.1.0] - 2026-06-09

Backward-compatible release: new helpers, security hardening, and a test suite
brought to 100% line coverage. Files written by 1.0.0 remain readable.

### Added

- **File reading** — `oihana\files\clearFile`, `oihana\files\countFileLines`, `oihana\files\getFileLines`, `oihana\files\getFileLinesGenerator`.
- **MIME / images** — `oihana\files\images\getImageMimeType`, plus the enums `oihana\files\enums\ImageFormat`, `oihana\files\enums\AudioMimeType`, `oihana\files\enums\ImageMimeType`, `oihana\files\enums\VideoMimeType`.
- **OpenSSL V2 building blocks** — `oihana\files\openssl\deriveKey`, `oihana\files\openssl\bestAvailableKdf`, `oihana\files\openssl\isAeadCipher`, and the format-constants class `oihana\files\openssl\enums\EncryptionFormat`.
- **Discovery options** — `oihana\files\enums\FindFilesOption`, `oihana\files\enums\RecursiveFilePathsOption`.
- **Extensions / MIME types** — `.cbor` (`application/cbor`); `.cose` (`application/cose`, `application/cose.enc`).

### Changed

- `oihana\files\openssl\OpenSSLFileEncryption::encrypt()` now writes the **V2** on-disk format (AES-256-GCM) by default. The constructor's `$cipher` parameter now only governs **reading legacy V1** files (see *Security*).
- `oihana\files\openssl\OpenSSLFileEncryption::hasEncryptedFileSize()` now uses `filesize()` instead of loading the whole file into memory.
- `oihana\files\makeFile` / `oihana\files\makeDirectory` no longer emit a stray PHP warning before throwing when `chown()` / `chgrp()` fails.
- The test suite is raised to **100% line coverage** (1416/1416 lines, 38/38 methods). Adds a GitHub Actions CI workflow (PHP 8.4) and coverage tooling (`composer coverage` / `composer coverage:md`, `tools/clover-to-markdown.php`).

### Security

- Refactors `oihana\files\openssl\OpenSSLFileEncryption` to the **V2 on-disk format**: AES-256-GCM (AEAD) with a built-in integrity tag, a KDF (Argon2id via `ext-sodium`, PBKDF2-SHA256 600 000-iteration fallback), a per-file random salt + IV, `random_bytes` randomness and a magic header (`OPHE\x02`). **Backward-compatible**: legacy V1 files (CBC, no MAC) are still readable by `decrypt()` via auto-detection. Tampering on V2 ciphertext is now detected and reported as a `RuntimeException`.
- Hardens `oihana\files\requireAndMergeArrays` with a per-file validation pipeline (non-empty string, realpath-resolved file, case-insensitive `.php` extension) and an optional `$allowedBase` parameter to constrain paths under a trusted root. Mitigates arbitrary file inclusion (RCE) when paths come from untrusted sources. Backward-compatible for legitimate usages.
- Adds opt-in **decompression-bomb** protection to `oihana\files\archive\tar\untar` via the new `TarOption::MAX_EXTRACTED_SIZE` option: the archive is pre-scanned and a `RuntimeException` is thrown **before** any file is written if the sum of the entries' uncompressed sizes exceeds the cap. Default `null` preserves the historical unbounded behaviour.
- Adds opt-in **read size caps** to guard against OOM on untrusted inputs: a `?int $maxBytes` parameter on `oihana\files\getFileLines` and `oihana\files\requireAndMergeArrays` (per file), and a `?int $maxInputBytes` constructor parameter on `oihana\files\openssl\OpenSSLFileEncryption` (applied at the start of `encrypt()` / `decrypt()`). A `RuntimeException` is thrown **before** the file is read. Default `null` preserves the historical behaviour.
- Documents the **ReDoS** (Regular Expression Denial of Service) attack surface on `oihana\files\findFiles`, `oihana\files\shouldExcludeFile` and `oihana\files\copyFilteredFiles` — these are designed for trusted patterns (configuration, internal code). Adds `@security` DocBlock notes, a dedicated section in `wiki/{fr,en}/security.md`, and inline warnings in `wiki/{fr,en}/files/discovery.md`. No code change.
- Adds `wiki/{fr,en}/security.md` as the global security rubric (covered / not-covered threats, user best practices, vulnerability reporting policy).

### Fixed

- `oihana\options\Options::toArray()` LSP signature conflict (and documents the deferral of `Options::clone`).

### Documentation

- Adds the bilingual `wiki/{fr,en}/testing.md` guide: running the PHPUnit suite, measuring coverage, and the `@codeCoverageIgnore` policy.
- Fixes the `oihana\files\path\computeRelativePath` docblock example (`'../..'`, not `'../../'`).
- Aligns the wiki with the V2 OpenSSL behaviour and the TOML path-resolution logic.

## [1.0.0] - 2025-08-13

Initial public release. Entries are grouped by namespace; each function or class
is listed with a one-line summary.

### Added

#### `oihana\files` — file & directory operations

Core, framework-agnostic helpers to inspect, create, copy, delete and discover files and directories, with explicit validation.

- `assertDirectory` — throws a `DirectoryException` unless the path is an existing (optionally readable/writable) directory.
- `assertFile` — throws a `FileException` unless the path is an existing readable file (optional MIME / writable checks).
- `assertWritableDirectory` — asserts a directory exists and is writable.
- `copyFilteredFiles` — recursively copies a tree, with exclude patterns and an optional filter callback.
- `deleteDirectory` — recursively removes a directory and its contents.
- `deleteFile` — removes a single file (optional existence / permission assertions).
- `deleteTemporaryDirectory` — removes a directory resolved under the system temp directory.
- `findFiles` — lists entries with mode / pattern / recursion / sort options (returns `SplFileInfo[]`).
- `getBaseFileName` — returns a file name without its (possibly multi-part) extension.
- `getDirectory` — normalizes a path to a directory string (optionally asserting it).
- `getFileExtension` — returns a file's extension (multi-part aware, e.g. `.tar.gz`).
- `getHomeDirectory` — returns the current user's home directory as a canonical path.
- `getOwnershipInfos` — returns owner / group ownership information for a path.
- `getRoot` — returns the root segment of a path.
- `getSchemeAndHierarchy` — splits a path into its scheme and its hierarchical part.
- `getTemporaryDirectory` — resolves a path under the system temp directory (without creating it).
- `getTimestampedDirectory` — builds a timestamped directory path.
- `getTimestampedFile` — builds a timestamped file path.
- `hasDirectories` — tells whether a directory contains sub-directories.
- `hasFiles` — tells whether a directory contains files.
- `isLinux` / `isMac` / `isWindows` / `isOtherOS` — OS-family predicates (memoized).
- `makeDirectory` — creates a directory (recursive, permissions, owner / group).
- `makeFile` — writes a file (content, permissions, owner / group, append / overwrite options).
- `makeTemporaryDirectory` — creates a directory under the system temp directory.
- `makeTimestampedDirectory` — creates a timestamped directory.
- `makeTimestampedFile` — creates a timestamped file.
- `recursiveFilePaths` — returns file paths under a tree (extension filters, excludes, max depth).
- `requireAndMergeArrays` — `require`s several PHP array files and deep-merges them.
- `shouldExcludeFile` — tells whether a path matches any exclusion pattern.
- `sortFiles` — sorts an `SplFileInfo[]` by name / size / type / time criteria.
- `validateMimeType` — validates a file's MIME type against an allow-list.

#### `oihana\files\path` — path manipulation

Pure, filesystem-agnostic path helpers (Unix, Windows, URL and `phar://` aware).

- `canonicalizePath` — resolves `.` / `..` segments, normalizes separators and expands `~`.
- `computeRelativePath` — computes the relative path between two already-normalized relative paths.
- `directoryPath` — returns the directory portion of a path.
- `extractCanonicalParts` — splits a path into its canonical segments (helper of `canonicalizePath`).
- `isAbsolutePath` — tells whether a path is absolute.
- `isBasePath` — tells whether a path lies inside a base directory.
- `isLocalPath` — tells whether a path is local (no remote scheme).
- `isRelativePath` — tells whether a path is relative.
- `makeAbsolute` — turns a relative path into an absolute one against a base path.
- `makeRelative` — turns an absolute path into one relative to a base path.
- `joinPaths` — joins segments into a single normalized path.
- `normalizePath` — unifies separators and collapses redundant ones.
- `relativePath` — convenience wrapper returning the relative path between two paths.
- `splitPath` — splits a path into its root and the remainder.

#### `oihana\files\archive\tar` — tar archives

Create, inspect and extract `.tar` / `.tar.gz` / `.tar.bz2` archives via the `phar` extension.

- `assertTar` — throws unless the file is a valid tar archive.
- `hasTarExtension` — tells whether a filename carries a tar-family extension.
- `hasTarMimeType` — tells whether a file's MIME type is a tar type.
- `tar` — creates an (optionally compressed) tar archive from a set of paths.
- `tarDirectory` — convenience wrapper to archive a whole directory.
- `tarFileInfo` — returns metadata about a tar file (validity, compression, file count, total size).
- `tarIsCompressed` — tells whether a tar file is compressed.
- `untar` — extracts a tar archive (overwrite / dry-run options).
- `validateTarStructure` — lightweight structural validity check of a tar file.

#### `oihana\files\phar` — Phar helpers

- `assertPhar` — throws unless the `phar` extension and `PharData` are available.
- `getPharBasePath` — returns the on-disk base path of a `PharData` instance.
- `getPharCompressionType` — maps a `CompressionType` to the matching `Phar::*` constant.
- `preservePharFilePermissions` — re-applies archived file permissions after extraction.

#### `oihana\files\openssl` — file encryption

- `OpenSSLFileEncryption` — encrypts / decrypts files with OpenSSL, IV embedded in the output.

#### `oihana\files\toml` — TOML configuration

- `resolveTomlConfig` — loads a TOML config file, deep-merges it with defaults, and runs an optional init callback.

#### `oihana\files\options` — typed options objects

- `MakeFileOptions` — typed options object consumed by `makeFile()`.
- `OwnershipInfos` — typed object describing file ownership (owner / group).

#### `oihana\files\enums` — strongly-typed enumerations

Constant classes that replace magic strings across the library.

- `CanonicalizeBuffer` — internal static cache backing `canonicalizePath()`.
- `CompressionType` — supported compression algorithms (`none`, `gzip`, `bzip2`, …).
- `FileExtension` — catalogue of file extensions and their MIME mapping.
- `FileMimeType` — catalogue of MIME types and their extension mapping.
- `FindFileOption` — option keys for `findFiles()`.
- `FindMode` — discovery mode (`files`, `dirs`, `both`).
- `MakeDirectoryOption` — option keys for `makeDirectory()`.
- `MakeFileOption` — option keys for `makeFile()`.
- `OwnershipInfo` — keys describing ownership information.
- `TarExtension` — tar-family extensions and their compression mapping.
- `TarInfo` — keys of the `tarFileInfo()` result.
- `TarOption` — option keys for `untar()`.

#### `oihana\files\exceptions` — typed exceptions

- `DirectoryException` — directory-related failures.
- `FileException` — file-related failures.
- `UnsupportedCompressionException` — an unsupported compression type was requested.

#### `oihana\options` — generic options base

Cross-cutting base used by the typed options objects above.

- `Option` — maps public property names to command-line option names.
- `Options` — abstract base for hydratable / serializable options objects.
