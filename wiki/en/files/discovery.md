# Discovery

Six functions to list, filter and explore a directory's content.

- [`findFiles`](#findfiles) — the main API (filters, sort, mode, recursive).
- [`recursiveFilePaths`](#recursivefilepaths) — lighter variant, returns strings, extension filters.
- [`shouldExcludeFile`](#shouldexcludefile) — exclusion helper (glob or regex).
- [`sortFiles`](#sortfiles) — sort by criterion or callback.
- [`hasFiles`](#hasfiles) — does a directory contain at least one file?
- [`hasDirectories`](#hasdirectories) — does a directory contain at least one subdirectory?

---

## `findFiles`

```php
findFiles( ?string $directory , array $options = [] ) : array
```

**The richest API** to explore a directory. Returns an array of `SplFileInfo[]` (or mapped values if `filter` is set).

Throws `DirectoryException` via [`assertDirectory`](assertions.md#assertdirectory) if the path is invalid.

### Options (all optional)

| Key (string or enum)                | Type                              | Default        | Effect |
|-------------------------------------|-----------------------------------|----------------|--------|
| `'mode'` / `FindFilesOption::MODE`  | `'files'` / `'dirs'` / `'both'`   | `'files'`      | Type of entries returned. |
| `'recursive'` / `::RECURSIVE`       | `bool`                            | `false`        | Recursive walk. |
| `'followLinks'` / `::FOLLOW_LINKS`  | `bool`                            | `false`        | Follow *symlinks* (recursive mode only). |
| `'includeDots'` / `::INCLUDE_DOTS`  | `bool`                            | `false`        | Include files/dirs starting with `.`. |
| `'pattern'` / `::PATTERN`           | `string \| string[] \| null`      | `null`         | Glob (`*.php`) or regex (`/^foo$/i`), or mixed list. Auto-detected via `isRegexp`. |
| `'sort'` / `::SORT`                 | `callable \| string \| string[]`  | `false`        | Sort (see `sortFiles`). |
| `'order'` / `::ORDER`               | `'asc'` / `'desc'`                | `'asc'`        | Sort direction. |
| `'filter'` / `::FILTER`             | `?callable(SplFileInfo): mixed`   | `null`         | Final mapping via `array_map` (transforms each entry). |

### Walk modes

- `'files'` (default) — files only.
- `'dirs'` — directories only.
- `'both'` — both.

### Patterns: glob OR regex (auto-detected)

If the pattern looks like a regex (wrapped in delimiters like `/`, `#`, `~`, etc.), it is treated as such (`preg_match`). Otherwise it's a glob (`fnmatch`).

```php
// Glob
findFiles( '/var/www' , [ 'pattern' => '*.php' ] ) ;

// Regex
findFiles( '/var/www' , [ 'pattern' => '/^config\..+$/' ] ) ;

// Mixed list (logical OR: at least one pattern matches)
findFiles( '/var/www' , [ 'pattern' => [ '*.php' , '/^config\..+$/' ] ] ) ;
```

### Examples by use case

```php
use function oihana\files\findFiles;
use oihana\files\enums\FindFilesOption;
use oihana\files\enums\FindMode;
use oihana\enums\Order;
use SplFileInfo;

// 1. Basic
$files = findFiles( '/var/www' ) ;

// 2. Recursive
$files = findFiles( '/var/www' , [ FindFilesOption::RECURSIVE => true ] ) ;

// 3. With dotfiles
$files = findFiles( '/var/www' , [ FindFilesOption::INCLUDE_DOTS => true ] ) ;

// 4. Follow symlinks (recursive only)
$files = findFiles( '/var/www' , [
    FindFilesOption::RECURSIVE    => true ,
    FindFilesOption::FOLLOW_LINKS => true ,
]) ;

// 5. Pattern
$php = findFiles( '/var/www' , [ FindFilesOption::PATTERN => '*.php' ] ) ;

// 6. Directories only
$dirs = findFiles( '/var/www' , [ FindFilesOption::MODE => FindMode::DIRS ] ) ;

// 7. Sort by name descending
$files = findFiles( '/var/www' , [
    FindFilesOption::SORT  => 'name' ,
    FindFilesOption::ORDER => Order::desc ,
]) ;

// 8. Multi-criteria: type then name (dirs first)
$all = findFiles( '/var/www' , [
    FindFilesOption::MODE => FindMode::BOTH ,
    FindFilesOption::SORT => [ 'type' , 'name' ] ,
]) ;

// 9. Map to strings
$names = findFiles( '/var/www' , [
    FindFilesOption::FILTER => fn( SplFileInfo $f ) => $f->getBasename() ,
]) ;

// 10. Get only sizes
$sizes = findFiles( '/var/www' , [
    FindFilesOption::FILTER => fn( SplFileInfo $f ) => $f->getSize() ,
]) ;

// 11. All combined
$logs = findFiles( '/var/log' , [
    FindFilesOption::RECURSIVE    => true ,
    FindFilesOption::FOLLOW_LINKS => true ,
    FindFilesOption::INCLUDE_DOTS => true ,
    FindFilesOption::MODE         => FindMode::FILES ,
    FindFilesOption::PATTERN      => [ '*.log' , '*.txt' ] ,
    FindFilesOption::SORT         => 'ci_name' ,
    FindFilesOption::ORDER        => Order::asc ,
    FindFilesOption::FILTER       => fn( SplFileInfo $f ) => $f->getFilename() ,
]) ;
```

### Internal pipeline

`findFiles` performs, in order:

1. **Iterate** via `DirectoryIterator` (non-recursive) or `RecursiveIteratorIterator` (recursive).
2. **Filter by mode** (`isFile()` / `isDir()`).
3. **Filter dotfiles** unless `includeDots: true`.
4. **Filter by pattern** (glob or regex).
5. **Sort** via `sortFiles` if `sort` is set.
6. **Map** via `filter` if set.

---

## `recursiveFilePaths`

```php
recursiveFilePaths( string $directory , array $options = [] ) : array
```

**Simpler variant** of `findFiles` when you just want **string paths** (not `SplFileInfo`) with extension filter and exclude list.

**Throws `RuntimeException`** if the directory does not exist.

### Options

| Key (string or enum) | Type | Default | Effect |
|---|---|---|---|
| `'excludes'` / `RecursiveFilePathsOption::EXCLUDES` | `string[]` | `[]` | Names to exclude (exact filename comparison). |
| `'extensions'` / `::EXTENSIONS` | `string[]` | `null` | Allowed extensions (no dot). `null` or empty → all. |
| `'maxDepth'` / `::MAX_DEPTH` | `int` | `-1` | Max depth (`-1` = unlimited). |
| `'sortable'` / `::SORTABLE` | `bool` | `true` | Alphabetical sort of the result. |

### Examples

```php
use function oihana\files\recursiveFilePaths;
use oihana\files\enums\RecursiveFilePathsOption;

// All files recursively
$all = recursiveFilePaths( __DIR__ ) ;

// Only .php and .inc
$php = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::EXTENSIONS => [ 'php' , 'inc' ] ,
]) ;

// Exclude certain names
$clean = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::EXCLUDES => [ 'ignore.php' , 'test.php' ] ,
]) ;

// Limit to 2 levels
$shallow = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::MAX_DEPTH => 1 ,
]) ;

// Without sort (perf)
$unsorted = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::SORTABLE => false ,
]) ;
```

### `findFiles` vs `recursiveFilePaths`: when to pick which?

| Need                                                  | Prefer |
|-------------------------------------------------------|--------|
| Simple list of paths, filter by extension             | `recursiveFilePaths` |
| `SplFileInfo` objects, glob/regex, complex sort       | `findFiles` |
| Non-recursive mode                                    | `findFiles` (recursiveFilePaths is always recursive) |
| Complex pattern with regex                            | `findFiles` |
| Exclude by **exact filename**                         | `recursiveFilePaths` |
| Exclude by **pattern**                                | `findFiles` (with a callback) or [`copyFilteredFiles`](copying.md) |

---

## `shouldExcludeFile`

```php
shouldExcludeFile( string $filePath , array $excludePatterns ) : bool
```

**Exclusion** helper by pattern. Returns `true` if the file matches at least one exclude pattern.

**Auto-detection**:

- If the pattern looks like a regex (`'/^...$/i'`) → `preg_match`.
- Otherwise → `fnmatch` (glob, with `FNM_PATHNAME` flag → `*` does not cross `/`).

**Tries the match on two targets**: the full `$filePath` AND the `basename`. Matches if **at least one** matches.

```php
use function oihana\files\shouldExcludeFile;

$patterns = [
    '*.log' ,                // glob on basename
    '/^error_\d+/' ,         // regex on basename
    'config/db.php' ,        // glob on full path
] ;

shouldExcludeFile( '/var/www/app/logs/access.log' , $patterns ) ;     // true (*.log matches access.log)
shouldExcludeFile( '/tmp/error_12345.txt' , $patterns ) ;             // true (regex matches)
shouldExcludeFile( '/var/www/app/config/db.php' , $patterns ) ;       // true (config/db.php matches)
shouldExcludeFile( '/var/www/index.php' , $patterns ) ;               // false
```

Used internally by [`copyFilteredFiles`](copying.md). Reusable standalone for your own filters.

---

## `sortFiles`

```php
sortFiles( array &$files , callable|string|array $sort , ?string $order = 'asc' ) : void
```

Sorts an array of `SplFileInfo[]` **in-place** (modifies the array, returns nothing).

### Sort modes

**1. Custom callback:**

```php
sortFiles( $files , fn( SplFileInfo $a , SplFileInfo $b ) => $a->getMTime() <=> $b->getMTime() ) ;
sortFiles( $files , $callback , 'desc' ) ; // reverse via array_reverse at the end
```

**2. String criterion (built-in key):**

| Key          | Comparison |
|--------------|---|
| `'name'`     | `strcmp` on filename (case-sensitive). |
| `'ci_name'`  | `strcasecmp` (case-insensitive). |
| `'extension'`| `strcasecmp` on extension. |
| `'size'`     | `<=>` comparison on `getSize()`. |
| `'type'`     | `strcmp` of `getType()` ('file', 'dir', 'link', etc.). |
| `'atime'`    | Access time. |
| `'ctime'`    | Change time (inode). |
| `'mtime'`    | Modification time. |

**3. Criteria list (multi-criteria, first non-zero wins):**

```php
sortFiles( $files , [ 'type' , 'name' ] ) ;
// First by type (dir/file), then by name within each type
```

### Examples

```php
use function oihana\files\sortFiles;

// 1. Ascending name
sortFiles( $files , 'name' ) ;

// 2. Case-insensitive name descending
sortFiles( $files , 'ci_name' , 'desc' ) ;

// 3. Extension then size
sortFiles( $files , [ 'extension' , 'size' ] ) ;

// 4. Mtime descending (most recent first)
sortFiles( $files , fn( $a , $b ) => $a->getMTime() <=> $b->getMTime() , 'desc' ) ;

// 5. Type then case-insensitive name
sortFiles( $files , [ 'type' , 'ci_name' ] ) ;
```

> 💡 An unknown key in the list returns `0` (no error) — the sort continues with the next criteria.

---

## `hasFiles`

```php
hasFiles( ?string $dir , bool $strict = false ) : bool
```

Indicates whether a directory contains **at least one file**, or **only files** in strict mode.

**Throws `DirectoryException`** if the directory does not exist (via `assertDirectory`).

```php
use function oihana\files\hasFiles;

hasFiles( '/var/www' ) ;
// → true if at least one file (even if there are also directories)

hasFiles( '/var/www' , strict: true ) ;
// → true only if /var/www contains EXCLUSIVELY files
//   (no subdirectories, no symlinks, etc.)
```

---

## `hasDirectories`

```php
hasDirectories( ?string $dir , bool $strict = false ) : bool
```

Symmetric to `hasFiles`. Indicates whether a directory contains **at least one subdirectory**, or **only subdirectories** in strict mode.

```php
use function oihana\files\hasDirectories;

hasDirectories( '/var/www' ) ;
// → true if at least one subdirectory

hasDirectories( '/var/www' , strict: true ) ;
// → true only if EXCLUSIVELY subdirectories
```

### Use case: testing if a directory is "semantically empty"

```php
if ( !hasFiles( $dir ) && !hasDirectories( $dir ) ) {
    // Empty directory (apart from `.` and `..`)
    deleteDirectory( $dir ) ;
}
```

---

## See also

- [Filtered copy](copying.md) — `copyFilteredFiles` (uses `shouldExcludeFile`).
- [Assertions](assertions.md) — `assertDirectory` (used upstream).
- [Enums](../enums.md) — `FindFilesOption`, `FindMode`, `RecursiveFilePathsOption`.
- [Overview](README.md).
