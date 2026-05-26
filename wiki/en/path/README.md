# Paths — `oihana\files\path`

The `oihana\files\path` namespace bundles **14 standalone functions** for portable file-path manipulation (Unix, Windows, PHP URL schemes like `phar://`).

> 💡 **All these functions are autoloaded** through `composer.autoload.files` — no need for `use function`, but the IDE appreciates it. None touch the filesystem (except `canonicalizePath` which resolves `~` via `getHomeDirectory`).

## Principles

1. **Purely textual manipulation.** No existence checking (except `~` expansion). You can normalise a non-existent path — useful for path generation, tests, etc.
2. **Scheme preservation.** URL-style paths (`phar://`, `file://`, `vfs://`) are preserved across operations.
3. **Uniform slashes.** All functions return paths with `/` even if the input uses `\` (Windows). Exception: `directoryPath` rebuilds with the input separator if the input contains `\`.
4. **LRU cache on `canonicalizePath`.** Canonicalised paths are memoised (see [`CanonicalizeBuffer`](../enums.md#canonicalizebuffer)) — useful in tight loops over the same paths.

## Catalogue

| Category | Functions |
|---|---|
| **Joining and normalising** | [`joinPaths`](joining-and-normalizing.md#joinpaths), [`normalizePath`](joining-and-normalizing.md#normalizepath), [`canonicalizePath`](joining-and-normalizing.md#canonicalizepath), [`extractCanonicalParts`](joining-and-normalizing.md#extractcanonicalparts) |
| **Absolute / relative** | [`isAbsolutePath`](absolute-vs-relative.md#isabsolutepath), [`isRelativePath`](absolute-vs-relative.md#isrelativepath), [`makeAbsolute`](absolute-vs-relative.md#makeabsolute), [`makeRelative`](absolute-vs-relative.md#makerelative), [`computeRelativePath`](absolute-vs-relative.md#computerelativepath), [`relativePath`](absolute-vs-relative.md#relativepath) |
| **Inspection** | [`splitPath`](inspection.md#splitpath), [`directoryPath`](inspection.md#directorypath), [`isLocalPath`](inspection.md#islocalpath), [`isBasePath`](inspection.md#isbasepath) |

## Typical usage

```php
use function oihana\files\path\joinPaths;
use function oihana\files\path\makeAbsolute;
use function oihana\files\path\isBasePath;

$base = '/var/www';

// Build a clean path from fragments
$logFile = joinPaths( $base, 'logs', '..', 'logs/app.log' );
// → '/var/www/logs/app.log'

// Force absolute from possibly-relative user input
$absolute = makeAbsolute( $userInput, $base );

// Security: refuse to write outside the allowed root directory
if ( !isBasePath( $base, $absolute ) ) {
    throw new \RuntimeException("Path escape attempt: $absolute");
}
```

## See also

- [Joining and normalising](joining-and-normalizing.md) — `joinPaths`, `normalizePath`, `canonicalizePath`, `extractCanonicalParts`.
- [Absolute vs relative](absolute-vs-relative.md) — detection (`isAbsolutePath`/`isRelativePath`) and conversion (`makeAbsolute`/`makeRelative`/`computeRelativePath`/`relativePath`).
- [Inspection](inspection.md) — `splitPath`, `directoryPath`, `isLocalPath`, `isBasePath`.
- [English TOC](../README.md) — back to the table of contents.
