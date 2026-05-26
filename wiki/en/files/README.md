# Files — `oihana\files`

The `oihana\files` namespace bundles **~45 standalone functions** for common file and directory operations. They live at the root of [`src/oihana/files/`](../../../src/oihana/files/) (the sub-namespaces `path`, `archive`, `phar`, `openssl`, `toml`, `images` are documented separately).

> 💡 **All these functions are autoloaded** through `composer.autoload.files`. They all throw typed exceptions (`FileException`, `DirectoryException`) on errors — see [exceptions.md](../exceptions.md).

## Layout

Functions are grouped into **9 categories** documented separately:

| Category | Pages | Functions |
|---|---|---|
| **Assertions** | [assertions.md](assertions.md) | `assertFile`, `assertDirectory`, `assertWritableDirectory` |
| **Creation** | [creation.md](creation.md) | `makeFile`, `makeDirectory`, `makeTimestampedFile`, `makeTimestampedDirectory`, `makeTemporaryDirectory` |
| **Deletion** | [deletion.md](deletion.md) | `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory` |
| **Temporary directories** | [temporary.md](temporary.md) | `getTemporaryDirectory`, `makeTemporaryDirectory`, `deleteTemporaryDirectory` (workflow) |
| **Reading** | [reading.md](reading.md) | `getFileLines`, `getFileLinesGenerator`, `countFileLines`, `requireAndMergeArrays` |
| **Discovery** | [discovery.md](discovery.md) | `findFiles`, `recursiveFilePaths`, `shouldExcludeFile`, `sortFiles`, `hasFiles`, `hasDirectories` |
| **Filtered copy** | [copying.md](copying.md) | `copyFilteredFiles` |
| **System** | [system.md](system.md) | `isLinux`, `isMac`, `isWindows`, `isOtherOS`, `getHomeDirectory`, `getRoot`, `getSchemeAndHierarchy`, `getOwnershipInfos`, `getDirectory`, `getBaseFileName`, `getFileExtension`, `getTimestampedFile`, `getTimestampedDirectory` |
| **MIME** | [mime.md](mime.md) | `validateMimeType`, `getImageMimeType` |

## Cross-cutting conventions

### 1. Assertion-based validation

All destructive functions (`delete*`, `clear*`) accept an `$assertable` parameter (default `true`) controlling the upstream call to [`assertFile`](assertions.md#assertfile) or [`assertDirectory`](assertions.md#assertdirectory). When `false`, the function tries the operation directly and returns `false` on failure instead of throwing.

```php
deleteFile( '/path/maybe-missing.txt' , assertable: false ) ; // false if absent, no exception
deleteFile( '/path/exists.txt' ) ;                            // throws FileException on issue
```

### 2. Options: associative array OR positional parameters

Complex functions (`makeFile`, `makeDirectory`) accept **two equivalent signatures**:

```php
// Positional style
makeFile( '/path/to/file.txt' , 'content' , [ 'permissions' => 0600 ] ) ;

// Options-as-array style (with enum keys)
makeFile([
    MakeFileOption::FILE        => '/path/to/file.txt' ,
    MakeFileOption::CONTENT     => 'content' ,
    MakeFileOption::PERMISSIONS => 0600 ,
]) ;
```

Pick whichever fits the context; the options-as-array style is preferred when you build options dynamically.

### 3. Typed exceptions

| Exception | Thrown by |
|---|---|
| [`FileException`](../exceptions.md) | Anything related to files (`assertFile`, `makeFile`, `deleteFile`, `clearFile`, reading, MIME, ownership). |
| [`DirectoryException`](../exceptions.md) | Anything related to directories (`assertDirectory`, `makeDirectory`, `deleteDirectory`, `*TemporaryDirectory`, `*TimestampedDirectory`). |
| [`UnsupportedCompressionException`](../exceptions.md) | Tar archive only (see [archive/](../archive/README.md)). |

### 4. No hidden I/O

No function performs network I/O, spawns subprocesses (except `posix_*` calls for ownership), or touches files outside the path you pass. **No surprising side effects.**

## Typical example: backup workflow

```php
use function oihana\files\{ makeTemporaryDirectory , makeTimestampedFile , copyFilteredFiles , deleteTemporaryDirectory } ;
use function oihana\files\path\joinPaths ;

// 1. Prepare a temp directory
$workDir = makeTemporaryDirectory( [ 'backup' , 'staging' ] ) ;

// 2. Copy files to back up with filtering
$copied = copyFilteredFiles( '/var/www/site' , $workDir , [
    'excludes' => [ '.git' , 'node_modules' , 'vendor' ] ,
]) ;

// 3. Create a timestamped archive file next to it
$archive = makeTimestampedFile(
    basePath  : '/backups' ,
    extension : '.tar.gz'  ,
    prefix    : 'site-'
) ;
// → e.g. /backups/site-2026-05-26T15:30:12.tar.gz

// (produce the archive with tar() — see wiki/en/archive/)

// 4. Cleanup
deleteTemporaryDirectory( [ 'backup' , 'staging' ] ) ;
```

## See also

- [Path namespace](../path/README.md) — for path manipulation before file operations.
- [Archive](../archive/README.md) — to produce a `.tar.gz` from a directory.
- [OpenSSL](../openssl/README.md) — to encrypt a backup file.
- [Enums](../enums.md) — catalogue of `MakeFileOption`, `MakeDirectoryOption`, etc.
- [Exceptions](../exceptions.md) — full hierarchy.
- [English TOC](../README.md).
