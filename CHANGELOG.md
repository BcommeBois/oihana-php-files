# Oihana PHP Files OpenSource library - Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [1.5.0] - 2026-10-24

Encryption no longer reads the file it encrypts. `encrypt()` and `decrypt()` each loaded the whole
payload into memory, and the measurement is the reason this release exists: on 2026-10-24, in a VM
with 3.8 GB of RAM, **a 300 MB file peaked at 1.18 GB — four times its size** (the ciphertext, a
copy of it, the plaintext, and the write), and **a 1 GB file had the process killed by the kernel's
OOM killer**: `Out of memory: Killed process (php)` in `dmesg`, and nothing anywhere else. No
exception, no stack trace, no log line — an operation that neither succeeded nor failed. For a
caller archiving WordPress sites, a 2.45 GB archive asked for about 10 GB of RAM, which made
backup, restore and archive impossible on exactly the sites that most needed them. `maxInputBytes`
existed but defaulted to `null`, so nothing stood in the way.

The new **V3** format cuts the plaintext into blocks and seals each one on its own. Measured after
the change: a **100 MiB round trip under `memory_limit=48M` peaks at 30 MiB** and takes 1.3 s.

### Added

- **Format V3**, written by `encrypt()` from now on: a 31-byte header
  (`OPHE` + version + KDF + block-size exponent + 16-byte salt + 8-byte nonce prefix) followed by
  frames of `[encrypted block][16-byte GCM tag]`. The nonce of block *i* is the per-file prefix
  followed by `pack('N', i)`, so no two blocks of a file share one.
- **The associated data that makes the format whole** — `encryptionBlockAad( $header, $index,
  $isFinal )`. A GCM tag alone proves a block was sealed with the key and nothing more; sealed with
  no associated data, every frame authenticates on its own, and four rewrites performed **without
  the key** go undetected: cutting the file short, swapping two frames, dropping one from the
  middle, and splicing two files encrypted with the same passphrase. Binding the header, the block
  index and a final-block flag refuses all four. The flag is decided **at read time** from the
  cursor against the file size, which is what makes a truncation present a frame sealed as
  non-final where a final one belongs.
- **`encryptionBlockNonce( $noncePrefix, $index )`** — the counter is bounded, because
  `pack('N', 2**32)` wraps silently to zero, and a repeated nonce under one AES-GCM key does not
  degrade security, it collapses it.
- **`assertBlockSizeExponent( $exponent )`** — the exponent is one byte of a header the reader
  meets before anything has been authenticated, so a crafted file can ask for 1-byte blocks
  (millions of tag verifications) or a 2^255-byte allocation. Bounded to 2^16–2^30 on write and on
  read.
- **`availableMemoryBytes()`** and **`parseMemoryLimit()`** — the memory budget of the current
  process: `memory_limit` first, `/proc/meminfo`'s `MemAvailable` only when it is `-1`. `null`
  means *unknown*, never *unlimited*.
- **`kdfIsAvailable()`** — whether the KDF a file names can be run on this host. `ext-sodium` is a
  suggestion of this package, not a requirement, so the two ends of an archive's life do not have
  to agree on it.
- **`OpenSSLFileEncryption::$blockSizeExponent`** (fourth constructor argument) — the block size
  this instance writes, 8 MiB by default, validated in the constructor.

### Changed

- **`encrypt()` streams and produces V3.** An empty input still writes one frame — an empty block
  and its tag. Were an empty file just a header, stripping every frame from a one-block file would
  produce something that decrypts to nothing, in silence, which is the one truncation a per-block
  tag cannot catch.
- **`decrypt()` dispatches on the version** it finds, and checks the output path **before** reading
  anything: a streaming read has already written part of its output by the time the old checks ran.
- **Both directions write to `<destination>.part` and `rename()` it into place.** The V2
  `decrypt()` held the whole plaintext before writing a byte, so it could not leave a partial file;
  a streaming one can, and half a plaintext looks exactly like a whole one.
- **`isEncryptedFile()` reads the first bytes, not the file.** Deciding what a file is takes 31
  bytes; the previous version spent the file's size in memory to answer a yes-or-no question.
- **A file bearing the `OPHE` magic with an unknown version is refused by name**, instead of being
  run through the V1 path — which has no integrity check and would emit whatever AES-CBC made of it.

### Fixed

- **An archive whose key was derived with Argon2id is now refused by name where `ext-sodium` is
  absent.** Measured on 2026-10-24 with `disable_functions=sodium_crypto_pwhash`: a file written
  on a host that has the extension, read on one that does not, reported *"Decryption failed due to
  incorrect passphrase or corrupted data"* — on an intact archive, with the right passphrase,
  because the real cause (`deriveKey: Argon2id requested but the sodium extension is not loaded`)
  sat in a wrapped exception that no command line prints. For someone restoring a backup, the most
  misleading report available: it sends them after a lost secret or a corrupt file instead of an
  extension that installs in a minute. The reader now names the extension and states that the
  passphrase is not in question. Naming it gives nothing away — a missing extension is a property
  of the reader's own machine — whereas *why* a tag failed stays generic, and an unknown KDF byte
  keeps the generic message too, since that is a forged or corrupt header rather than a missing
  implementation. Files written without sodium use PBKDF2-SHA256, which is native and reads
  anywhere; this was never a one-way format, only a one-way message.
- **A V1 or V2 file past the memory budget is now refused with a sentence** naming the format, the
  size, what reading it would cost, the budget it was measured against, and what the operator can
  do. PHP exposes no incremental AEAD API, so a single-tag blob genuinely cannot be verified in
  pieces — what it can do is say so instead of disappearing. The guard is inert when the budget is
  unknown, and does not apply to V3.

### Compatibility

- **V1 and V2 files remain readable.** `encrypt()` then `decrypt()` migrates one.
- **A file's KDF must be runnable where it is read.** The header names the KDF, which is what lets
  a reader pick the right one; it cannot make it available. An archive written with Argon2id needs
  `ext-sodium` on the host that restores it.
- **A V3 file is not readable by an earlier release of this library.** Writers and readers must be
  upgraded together; archives already written in V2 are unaffected.

## [1.4.0] - 2026-10-18

A format is recognized under every name it carries. `finfo` reports `audio/x-wav` for a WAV file where
the catalogue knows `audio/wav`, `text/xml` for an XML document where it knows `application/xml`:
read as different strings, the same file got **no extension** from `FileMimeType::getExtension()`, and
`validateMimeType()` **refused** it against a list naming the format the other way — a list allowing
`audio/wav` refused every WAV file. Measured on real files (PHP 8.4, fileinfo 8.4): WAV, raw AAC and XML
were the three formats affected; ogg, opus, mp3, flac, m4a, webm, mp4, mov, png, jpeg, gif, webp, bmp,
ico, tiff, heic, avif, svg, pdf, zip, gz, json, csv, txt, html and php already matched.

### Added

- **`normalizeMimeType( string $mimeType ) : string`** — brings a type name back to the reference name
  of its format: lower-cased, parameters dropped (`audio/wav; codecs=1`), then an alias replaced by the
  name the catalogue uses (`audio/x-wav` → `audio/wav`). A name without alias comes back cleaned.
- **`enums\MimeTypeAlias`** — the table it reads, each alias naming the same format as its reference:
  `audio/x-wav`, `audio/x-hx-aac-adts`, `text/xml` (reported by `finfo`); `audio/webm` → `video/webm`
  (declared by browsers for a voice recording, the container `finfo` reports as `video/webm`); and older
  names (`audio/wave`, `audio/vnd.wave`, `audio/x-aac`, `audio/x-flac`, `image/x-ms-bmp`,
  `application/x-gzip`). It is a class of its own: a constant of `FileMimeType` would be read as a type
  by `ConstantsTrait`. `MimeTypeAlias::UNKNOWN_BINARY` names `application/octet-stream`.

### Fixed

- **`FileMimeType::getExtension()` reads the aliases**, the case and the parameters of a type:
  `audio/x-wav` gives `.wav`, `Text/XML; charset=utf-8` gives `.xml`.
- **`validateMimeType()` accepts a format under any of its names.** Both the detected type and the
  allowed ones are normalized before the comparison: `['audio/wav']` accepts a WAV reported as
  `audio/x-wav`, and `['audio/x-wav']` one reported as `audio/wav`. Only names of the same format meet —
  another format is still refused. Allowing `audio/webm` allows a WebM video too, the container being the
  same. The error message still quotes the type as `finfo` reported it.

### Changed

- **`FileMimeType::getExtension( 'application/octet-stream' )` returns `null`**, no longer
  `['.app', '.db', '.enc', '.tar.gz.enc']`. It is the type `finfo` reports for a content it does not
  recognize: no extension can be told from it, and a caller taking the first one named any unknown file
  `….app`. The four formats keep their type the other way: `getFromExtension( '.db' )` still returns
  `application/octet-stream`.
- **The test suite is back to 100% line coverage.** The tar engine of 1.3.0 had left 21 lines no test
  reached, all in `archive/tar/`: on a machine with a GNU tar the binary compresses, so PharData never
  compressed under test; and nothing made the system tar fail, fail to start, or step aside. Tests now
  force PharData to write `.tar.gz` and `.tar.bz2`, run a fake tar that exits with an error (the
  half-written archive is removed, the error carries what tar said), point the engine at a tar that
  cannot start, and empty the `PATH` of the compressor. Tests only: no behavior changes, and no line is
  marked `@codeCoverageIgnore` to get there.

## [1.3.0] - 2026-08-19

`tar()` hands the work to the system `tar` when there is one worth trusting, and keeps
`PharData` for everywhere else. Same archives, same names, same API.

**Why.** `PharData` writes tar archives in pure PHP. On the same 96 MB / 7 554-file tree,
producing the same archive, it took **311.8 seconds where GNU tar took 2.1** — and the gap
widens with size, since it writes the tar and then reads all of it back to compress it. It also
refuses any path component past the 100-byte `ustar` limit: one file of a stock WordPress
plugin set is over it, so a backup of an ordinary site could not be produced at all.

**Which binary.** GNU tar only. It stores names as raw bytes, exactly as `PharData` does —
verified on a tree of accented, CJK, quoted and spaced names, an empty directory, a symlink and
a deep path: the entry lists are identical. `bsdtar`, which macOS ships as `/usr/bin/tar`,
normalises filenames to Unicode NFD (`été.txt` becomes `e´te´.txt`), so an archive written on a
Mac would carry different names than the originals. Slower and identical beats faster and
subtly different, so macOS keeps `PharData` — unless a GNU tar is installed beside it
(`brew install gnu-tar`), which the search finds as `gtar` under either Homebrew prefix.

**When it declines.** The engine is chosen before anything is written and never revisited: no
GNU tar, several paths with no common parent, or a compression whose external compressor is
absent — `tar` pipes through `gzip`/`bzip2`/`xz` where `PharData` uses the PHP extensions, so a
host with `ext-bz2` and no `bzip2` program keeps the engine that works for it. A binary that is
present and *fails* raises instead of falling back: it is reporting something `PharData` would
meet as well, and retrying would spend minutes reaching the same wall with the reason discarded.

### Added

- `tarBinary()` — the system `tar` this library will use, or `null`. Public so that an
  application can report which engine is in place: a host that silently falls back to
  `PharData` is a host whose backups may stop fitting in their window, and a README is read
  once while a health check is read every day. `OIHANA_TAR_BINARY` overrides the search — a
  path to force one, an empty value to force `PharData`.
- `tarEntries()` / `tarEntriesByBase()` — what an archive is to contain and under which names,
  computed once for whichever engine writes it. The naming is the compatibility contract with
  every archive already written; two copies of those rules would drift, and the drift would
  only show the day someone tried to restore an old backup.
- `tarWithBinary()`, `tarCompressionFlag()`, `tarCompressorExists()`, `tarBinaryIsUsable()`.

### Changed

- `tar()` builds through the system binary where it can. Entry names are computed in PHP and
  handed over with `--no-recursion` on standard input, NUL-separated, so `tar` walks nothing
  and decides nothing — and so that sixteen thousand paths do not run into `ARG_MAX`.

### Fixed

- `tarEntries()` derives a member's name from the item rather than calling `getSubPathName()`
  on `RecursiveIteratorIterator`, which does not declare it: it worked through the forwarding
  of unknown calls to the inner iterator — valid at runtime, invisible to static analysis, and
  one refactor away from quietly returning something else.

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
