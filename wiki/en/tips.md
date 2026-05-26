# Tips and pitfalls

Living page grouping the **golden rules**, recurring traps and useful conventions to know. To enrich over time with incidents and feedback.

## Golden rules

### 1. Always prefer the namespace helpers over native PHP functions

| Instead of... | Use... |
|---|---|
| `$a . '/' . $b` or `$a . DIRECTORY_SEPARATOR . $b` | [`joinPaths( $a , $b )`](path/joining-and-normalizing.md#joinpaths) |
| `realpath( $path )` (resolves symlinks + validates) | [`canonicalizePath( $path )`](path/joining-and-normalizing.md#canonicalizepath) (purely textual) |
| `dirname( $path )` (Windows quirks) | [`directoryPath( $path )`](path/inspection.md#directorypath) |
| `if ( !is_file( $p ) ) throw ...` | [`assertFile( $p )`](files/assertions.md#assertfile) |
| `if ( !is_dir( $p ) ) throw ...` | [`assertDirectory( $p )`](files/assertions.md#assertdirectory) |
| `glob()` without flags, manual manipulations | [`findFiles( $dir , ... )`](files/discovery.md#findfiles) |
| `array_merge()` on nested configs | `deepMerge()` (via `oihana/php-core`) or [`requireAndMergeArrays`](files/reading.md#requireandmergearrays) |

**Why:** uniform API, typed exceptions, consistent scheme handling (`phar://`, `file://`...) and cross-platform behaviour (Windows vs Unix).

### 2. Always canonicalise user-provided paths

```php
use function oihana\files\path\{ canonicalizePath , isBasePath } ;

$userPath = canonicalizePath( $_GET[ 'path' ] ) ;

// Refuse any path that escapes the allowed directory
if ( !isBasePath( '/var/www/uploads' , $userPath ) ) {
    throw new \RuntimeException( "Path traversal blocked: $userPath" ) ;
}
```

This is the standard antidote to **path traversal attacks** (`../../etc/passwd`). See [`isBasePath`](path/inspection.md#isbasepath).

### 3. Prefer `assertable: false` over a silent try/catch

```php
// ❌ Bad: masks all errors including real I/O ones
try { deleteFile( $maybe ) ; } catch ( FileException $e ) {}

// ✅ Good: explicit about the intent ("try, return false if useless")
deleteFile( $maybe , assertable: false ) ;
```

Available on `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory`, `getDirectory`, `getTemporaryDirectory`, etc.

### 4. Always use enums for option keys

```php
// ❌ Magic strings, IDE doesn't complete, silent typos
makeFile( $path , $content , [
    'permissons' => 0644 , // typo not detected → silently ignored
]) ;

// ✅ Typed constants, refactor-friendly, IDE completes
use oihana\files\enums\MakeFileOption;
makeFile( $path , $content , [
    MakeFileOption::PERMISSIONS => 0644 ,
]) ;
```

---

## Platform pitfalls

### Windows: paths and separators

- The module **normalises everything to `/`** in output (Unix-style). If you build a path for `exec()` on Windows and the command requires `\`, do the final conversion yourself.
- `directoryPath()` **preserves the input separator**: if you pass it `\`, it returns `\`. A detail to know.

### Windows: missing `ext-posix`

- [`getOwnershipInfos`](files/system.md#getownershipinfos) returns `owner` and `group` as `null` on Windows (no `ext-posix`). `uid`/`gid` remain available with PHP-emulated values.
- The `'owner'` / `'group'` options of `makeFile` / `makeDirectory` are **silently no-op** on Windows (`chown` / `chgrp` native PHP have no effect).

### macOS: `Darwin` !== `Mac`

- `PHP_OS` is `'Darwin'` on macOS, not `'Mac'`. The [`isMac()`](files/system.md#ismac) function handles this correctly — but if you do `PHP_OS === 'Mac'` manually, it does not work.

### Linux: symlinks in `findFiles`

- Default `followLinks: false`. If you enable `followLinks: true` in `recursive` mode, beware of **infinite loops** on circular symlinks (`a` → `b` → `a`).
- PHP with `FilesystemIterator::FOLLOW_SYMLINKS` detects cycles via inodes, but memory cost grows.

---

## Crypto & security pitfalls

### OpenSSL: current limitations

The [`OpenSSLFileEncryption`](openssl/README.md) module has several **known attack vectors**:

1. **CBC without HMAC**: no tampering detection. An attacker can alter the ciphertext and decryption returns silently corrupted data.
2. **Broken GCM**: if you instantiate with `cipher: 'aes-256-gcm'`, the code does not handle the tag — `encrypt` "works" but `decrypt` does not validate integrity.
3. **No KDF**: the passphrase is used directly as key. Short passphrase = weak key.
4. **`openssl_random_pseudo_bytes`**: deprecated in favour of `random_bytes`.

A refactor is planned — see the OpenSSL doc and the internal backlog.

> 💡 **For now**: use for files at rest on a trusted disk (local backups), not for files exchanged or stored on a disk accessible to a potential attacker.

### Tar: path traversal in default mode

[`untar`](archive/untar.md#untar) **detects `..`** in archive entries, but **only** if `overwrite: false` or `dryRun: true`. In default mode (`overwrite: true`), protection depends on `PharData::extractTo`.

**Recommendation** for archives from an external source (uploads, downloads):

```php
// 1. Pre-scan (throws if traversal)
untar( $archive , $dest , [ TarOption::DRY_RUN => true ] ) ;

// 2. Safe extraction
untar( $archive , $dest , [ TarOption::OVERWRITE => false ] ) ;
```

### Tar decompression bombs

A tar archive can be forged to explode at extraction (a few kilobytes → several gigabytes uncompressed). By default, `untar()` **imposes no cap** on the uncompressed size — this is opt-in to preserve historical compatibility.

**Recommendation**: for any externally-sourced archive, set `TarOption::MAX_EXTRACTED_SIZE` (in bytes). The pre-scan accumulates each entry's size and throws `RuntimeException` **before** any write if the total exceeds the cap.

```php
use function oihana\files\archive\tar\untar ;
use oihana\files\enums\TarOption ;

untar( $uploaded , $dest , [
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // 100 MiB cap
] ) ;
```

Full details: [archive/untar.md § Decompression-bomb protection](archive/untar.md#decompression-bomb-protection).

### Tar: symlinks and `chmod`

- `tar` **serialises symlinks as symlinks** (not their target). At extraction, they are recreated as-is.
- `untar` with `keepPermissions: true` restores the **mode** but **not** owner/group. For a faithful backup, do `chown` manually afterwards or use `rsync` / `cp -p`.

---

## Performance pitfalls

### `getFileLines` vs `getFileLinesGenerator`

- `getFileLines` loads everything in RAM (`iterator_to_array`). A 100 MB file = 100 MB RAM.
- `getFileLinesGenerator` is **streaming** — only one line in memory at a time.
- Past a few MB, prefer the generator.

### `canonicalizePath` LRU cache

Canonicalised paths are kept in a static cache in [`CanonicalizeBuffer`](enums.md#canonicalizebuffer) up to 1250 entries. If your load includes **millions of distinct paths** in a single process, the buffer can consume memory before cleanup.

**Mitigation**: `CanonicalizeBuffer::$buffer = [] ;` to manually clear.

### `findFiles` recursive + regex pattern

The pattern is tested on **every encountered file**. If you have 100k files and a complex regex, the cost is noticeable. Prefer glob (`fnmatch`) when possible — faster than `preg_match`.

### `copyFilteredFiles` on large volumes

PHP's native `copy()` loads the file in memory before writing. For **volumes > 1 GB** or thousands of files, prefer `rsync` via `exec()` or a dedicated tool. `copyFilteredFiles` is ideal for one-off snapshots < ~1 GB.

---

## Typing / API pitfalls

### `assertTar` returns `bool`, not `void`

Unlike other `assert*` functions in the module, [`assertTar`](archive/untar.md#asserttar) returns a boolean instead of throwing. **Watch out**:

```php
// ❌ Bad: dead code
assertTar( $path ) ;
// The function returns false without throwing — the rest still executes

// ✅ Good
if ( !assertTar( $path ) ) {
    throw new FileException( "Not a valid tar: $path" ) ;
}
```

See [Exceptions](exceptions.md) for the exact list of throwing functions.

### `FindFileOption` vs `FindFilesOption`

Both classes exist with **exactly the same constants**. `FindFilesOption` (plural) is used by [`findFiles`](files/discovery.md#findfiles). `FindFileOption` (singular) is not referenced anywhere internally — likely historic duplication to clarify.

→ **Always use `FindFilesOption`** by convention.

### `MakeFileOption` (key enum) vs `MakeFileOptions` (DTO)

- `oihana\files\enums\MakeFileOption` — **string constants** for the keys of an array (`['append' => true]`).
- `oihana\files\options\MakeFileOptions` — typed **DTO class** extending [`Options`](options/README.md).

Same trap for `OwnershipInfo` (singular, enum) vs `OwnershipInfos` (plural, DTO). See [Enums](enums.md#naming-conventions).

### No exception hierarchy

The 3 exceptions (`FileException`, `DirectoryException`, `UnsupportedCompressionException`) inherit **directly from `\Exception`** — no common parent to catch them in bulk other than via `\Exception` or `\Throwable`.

If you want a targeted "everything except the rest" catch:

```php
catch ( FileException | DirectoryException | UnsupportedCompressionException $e ) {
    // ...
}
```

See [Exceptions](exceptions.md#missing-hierarchy-to-know).

---

## Internal conventions

### `oihana/*` dependencies are on `dev-main`

`oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums` are versioned on `dev-main` (not `^1.0`). Consequence: a `composer update` can bring transitive changes. If you deploy to production, **pin versions** in your `composer.lock` or wait for stabilisation.

### No subprocess in the module

No function in the module does `exec()` or `shell_exec()`. **Everything is pure PHP**. This keeps the code portable and testable, but imposes limits on very large volumes (see [Performance](#performance-pitfalls)).

### Internal tests via `vfsStream`

The module is extensively tested with [`mikey179/vfsstream`](https://github.com/bovigo/vfsStream) which simulates an in-memory filesystem. Practical consequence: **tests pass in CI without real I/O**, and you can also test your own module consumers without touching the disk.

```php
use org\bovigo\vfs\vfsStream;

$root = vfsStream::setup( 'myapp' ) ;
vfsStream::create([ 'config.toml' => 'debug = true' ] , $root ) ;

$config = resolveTomlConfig( vfsStream::url( 'myapp/config.toml' ) ) ;
```

---

## See also

- [Introduction](getting-started/introduction.md) — the philosophy underlying these conventions.
- [Exceptions](exceptions.md) — details of the 3 exceptions.
- [Enums](enums.md) — full catalogue.
- [English TOC](README.md).
