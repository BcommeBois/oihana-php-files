# Reading

Four functions to read a file's content idiomatically and efficiently, plus to load several PHP files returning arrays.

- [`getFileLines`](#getfilelines) — all lines as an `array`.
- [`getFileLinesGenerator`](#getfilelinesgenerator) — all lines as a `Generator` (memory-friendly).
- [`countFileLines`](#countfilelines) — fast line count in 8 KB chunks.
- [`requireAndMergeArrays`](#requireandmergearrays) — `require` multiple files returning `array`, then merge.

> 💡 All reading functions call [`assertFile`](assertions.md#assertfile) upstream — no need to check existence beforehand.

---

## `getFileLines`

```php
getFileLines(
    ?string   $file ,
    ?callable $map = null
) : ?array
```

Reads **all lines** of a file into an array. Each line is `rtrim`'d (strips `\r\n` or `\n`).

**Implementation:** calls `getFileLinesGenerator` internally then converts via `iterator_to_array` — handy but loads everything in memory. For very large files, prefer the generator.

**Parameters:**

- `$file`: path (passed to `assertFile`).
- `$map`: optional `fn(string $line): mixed` callback applied to each line.

**Returns:** array of lines (or mapped values). **Empty** array if the file exists but is size 0.

```php
use function oihana\files\getFileLines;

// Simple reading
$lines = getFileLines( '/var/log/myapp.log' ) ;
// → ['line 1', 'line 2', ...]

// With mapping: on-the-fly CSV parse
$rows = getFileLines( '/data/users.csv' , fn( string $line ) => str_getcsv( $line ) ) ;
// → [['name','age'], ['alice','30'], ...]

// Filter + transform
$errors = getFileLines( '/var/log/app.log' , function( string $line ) {
    return str_contains( $line , 'ERROR' ) ? trim( $line ) : null ;
}) ;
$errors = array_filter( $errors ) ;
```

> ⚠ **Memory**: a 100 MB file = 100 MB RAM. See `getFileLinesGenerator` for streaming.

---

## `getFileLinesGenerator`

```php
getFileLinesGenerator(
    ?string   $file ,
    ?callable $map = null
) : Generator
```

**Memory-friendly** version of `getFileLines`. Yields each line as you iterate — memory holds only **one line at a time** plus `fopen`'s internal buffer.

**Guarantees:**

- The handle is closed in a `finally`, so even if you break out of the `foreach` early.
- No speculative reading — if you break after 10 lines, the next 999,990 are never touched.

```php
use function oihana\files\getFileLinesGenerator;

// Simple traversal
foreach ( getFileLinesGenerator( '/var/log/huge.log' ) as $line ) {
    echo $line , PHP_EOL ;
}

// Parse CSV line by line
foreach ( getFileLinesGenerator( '/data/users.csv' , fn( $l ) => str_getcsv( $l ) ) as $row ) {
    print_r( $row ) ;
}

// Stop as soon as we find an error line
foreach ( getFileLinesGenerator( '/var/log/app.log' ) as $line ) {
    if ( str_contains( $line , 'FATAL' ) ) {
        echo "Fatal error detected: $line" ;
        break ; // handle closed cleanly
    }
}
```

### Which to pick?

| Case                                          | Prefer                    |
|-----------------------------------------------|---------------------------|
| File < 10 MB, vectorised processing           | `getFileLines`            |
| File > 10 MB                                  | `getFileLinesGenerator`   |
| Single streaming iteration                    | `getFileLinesGenerator`   |
| Need `count`/`array_slice`                    | `getFileLines` (generator is not an array) |

---

## `countFileLines`

```php
countFileLines( ?string $file ) : int
```

Counts a file's lines in **8 KB chunks** using `substr_count($chunk, "\n")`. Much faster than `count(file($path))` (which loads everything) or a line-by-line `fgets` loop.

**Details:**

- Size 0 file → returns `0` immediately.
- Counts `\n` characters — a file with no trailing `\n` may count one less than your editor shows.

```php
use function oihana\files\countFileLines;

$total = countFileLines( '/var/log/access.log' ) ;
// → 1283491

if ( countFileLines( '/var/log/errors.log' ) > 100 ) {
    sendAlert() ;
}
```

> ⚠ The counter depends on the **`\n` character**. For files with `\r`-only terminators (old Macs), the result is `0`. Rare in 2026 — good to know.

---

## `requireAndMergeArrays`

```php
requireAndMergeArrays(
    array   $filePaths ,
    bool    $recursive   = true ,
    ?string $allowedBase = null
) : array
```

Loads several PHP files via `require`, **each must return an `array`**, then merges them in order.

### Validation pipeline (per file)

Each path goes through a defensive validation pipeline before `require`:

1. Must be a **non-empty** `string` (otherwise `RuntimeException`).
2. Must resolve via `realpath()` to an **existing regular file** (otherwise `RuntimeException`).
3. Extension must be `.php` **case-insensitive** (otherwise `RuntimeException`).
4. If `$allowedBase` is provided: the resolved file must be **inside** that directory (otherwise `RuntimeException`).

This is **defense in depth**: even when you pass dynamically discovered paths (e.g. via [`recursiveFilePaths`](discovery.md#recursivefilepaths)), an escaped symlink or a maliciously renamed file is rejected.

> ⚠ **`require` executes PHP code.** This function only protects against **unexpected paths** — not against malicious code **inside** a legitimate `.php` file located in `$allowedBase`. The content remains the caller's responsibility.

### Parameters

| Parameter        | Type        | Default | Effect |
|------------------|-------------|---------|--------|
| `$filePaths`     | `array`     | —       | List of paths to load. |
| `$recursive`     | `bool`      | `true`  | `true` → `deepMerge` (recursive). `false` → `array_merge` (flat, top-level overwrite). |
| `$allowedBase`   | `?string`   | `null`  | If provided, every file must be under this root. **Strongly recommended** when paths are not 100% trusted. Throws `InvalidArgumentException` if it is not a valid directory. |

### Recommended pattern: with `$allowedBase`

When loading dynamically discovered files (DI definitions, plugins, etc.):

```php
use function oihana\files\{ requireAndMergeArrays , recursiveFilePaths } ;
use oihana\files\enums\RecursiveFilePathsOption;

$baseDir = __DIR__ . '/definitions' ;

$definitions = requireAndMergeArrays(
    recursiveFilePaths( $baseDir , [ RecursiveFilePathsOption::EXTENSIONS => [ 'php' ] ] ) ,
    true ,
    $baseDir ,  // ← allowed root: any file outside is refused
) ;
```

Benefit: if an attacker manages to drop a file or create a symlink outside `$baseDir`, the function refuses to include it.

### Typical usage: layered config

```php
use function oihana\files\requireAndMergeArrays;

$config = requireAndMergeArrays([
    __DIR__ . '/config/defaults.php' ,
    __DIR__ . '/config/env/' . $env . '.php' ,
    __DIR__ . '/config/local.php' ,
]) ;
```

With `config/defaults.php`:

```php
return [
    'app' => [
        'debug'    => false ,
        'timezone' => 'UTC' ,
        'logs'     => '/var/log/app' ,
    ] ,
] ;
```

And `config/env/dev.php`:

```php
return [
    'app' => [
        'debug' => true ,
    ] ,
] ;
```

Result (recursive merge):

```php
[
    'app' => [
        'debug'    => true ,   // overridden
        'timezone' => 'UTC' ,  // kept
        'logs'     => '/var/log/app' , // kept
    ] ,
]
```

### Recursive vs flat merge

```php
// recursive: true  → deep merge of nested arrays
$a = [ 'app' => [ 'debug' => false , 'tz' => 'UTC' ] ] ;
$b = [ 'app' => [ 'debug' => true ] ] ;
// → [ 'app' => [ 'debug' => true , 'tz' => 'UTC' ] ]

// recursive: false → array_merge overwrites at top level
// → [ 'app' => [ 'debug' => true ] ]   ← 'tz' lost!
```

> 💡 **Always `recursive: true` for config files** unless you specifically want a complete override of a section.

---

## See also

- [Assertions](assertions.md) — `assertFile` (used upstream).
- [TOML](../toml/README.md) — `resolveTomlConfig` for the same layered config pattern, but in TOML.
- [Discovery](discovery.md) — `findFiles` to get the paths to read.
- Dependencies: [`deepMerge`](../getting-started/dependencies.md#oihanaphp-core).
- [Overview](README.md).
