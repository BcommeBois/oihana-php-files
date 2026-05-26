# Concrete options — `oihana\files\options`

Two classes in the `oihana\files\options` namespace that extend [`Options`](README.md) to provide **specialised configuration types**:

- [`MakeFileOptions`](#makefileoptions) — typed wrapper for `makeFile` options.
- [`OwnershipInfos`](#ownershipinfos) — owner/group info returned by `getOwnershipInfos`.

> 💡 These classes are **canonical examples** of the `Options` pattern in `oihana/php-files`. The generic mechanics live in [`oihana\options\Options`](README.md); the files below show **how to specialise it**.

---

## `MakeFileOptions`

```php
namespace oihana\files\options ;

class MakeFileOptions extends Options
{
    public bool    $append      = false ;
    public ?string $content     ;        // no default
    public ?string $file        = null ;
    public bool    $force       = true ;
    public ?string $group       = null ;
    public bool    $lock        = true ;
    public ?bool   $overwrite   = true ;
    public ?string $owner       = null ;
    public ?int    $permissions = null ;

    public function __toString() : string
    {
        return $this->file ?? '' ;
    }
}
```

### Role

Options wrapper for the [`makeFile`](../files/creation.md#makefile) function. The **9 public properties** match exactly the keys accepted by `makeFile`'s options-as-array signature.

### Difference with `MakeFileOption` (the constants enum)

Don't confuse:

| | `MakeFileOption` (singular) | `MakeFileOptions` (plural) |
|---|---|---|
| Namespace | `oihana\files\enums` | `oihana\files\options` |
| Type | constants class (`Option` of the enumeration) | DTO class (extends `Options`) |
| Usage | `[ MakeFileOption::APPEND => true ]` (array keys) | `(new MakeFileOptions())->append = true` (object properties) |
| When to use | ad-hoc options array | reusable DTO, formattable, serialisable |

### Usage: equivalence of both styles

```php
use function oihana\files\makeFile;
use oihana\files\options\MakeFileOptions;
use oihana\files\enums\MakeFileOption;

// Style 1 — array + key enum (quick)
makeFile( '/path/file.txt' , 'Hello' , [
    MakeFileOption::PERMISSIONS => 0600 ,
    MakeFileOption::LOCK        => false ,
]) ;

// Style 2 — typed DTO (more structured, more verbose)
$opts = new MakeFileOptions([
    'file'        => '/path/file.txt' ,
    'content'     => 'Hello' ,
    'permissions' => 0600 ,
    'lock'        => false ,
]) ;
makeFile( $opts->toArray() ) ;

// Style 3 — DTO + programmatic override
$base = new MakeFileOptions([ 'force' => true , 'permissions' => 0644 ]) ;
$file = $base->clone() ;
$file->file        = '/log/app.log' ;
$file->content     = $logLine ;
$file->permissions = 0640 ;
makeFile( $file->toArray() ) ;
```

### `__toString()`

Returns `$this->file ?? ''` — handy for logs or quick display.

```php
$opts = new MakeFileOptions([ 'file' => '/var/log/app.log' ]) ;
echo "Creating: $opts" ;
// → Creating: /var/log/app.log
```

### Why a DTO instead of an array?

The options array remains **the default mode** for `makeFile`. The `MakeFileOptions` DTO is useful for:

- **Reusing a configuration** across several calls (clone + partial override);
- **Passing the config across application layers** (services, controllers, commands) without losing typing;
- **Serialise/deserialise** the config (JSON for APIs, persistence);
- **Format paths dynamically** via `format()`:

```php
$opts = new MakeFileOptions([
    'file'    => '/logs/{{component}}/{{date}}.log' ,
    'content' => '...' ,
]) ;
$opts->file = $opts->format( $opts->file ) ;
// Or with formatFromDocument from a runtime context
```

### Links

- [Creation](../files/creation.md#makefile) — the function that consumes this option.
- [`MakeFileOption`](../enums.md) — the equivalent constants enum.

---

## `OwnershipInfos`

```php
namespace oihana\files\options ;

class OwnershipInfos extends Options
{
    public ?string $owner = null ;  // e.g. 'www-data'
    public ?string $group = null ;  // e.g. 'www-data'
    public ?int    $uid   = null ;  // e.g. 33
    public ?int    $gid   = null ;  // e.g. 33

    public function equalsTo( OwnershipInfos $other ): bool ;
    public function __toString(): string ; // "owner:group (uid:gid)"
}
```

### Role

DTO returned by [`getOwnershipInfos`](../files/system.md#getownershipinfos). Represents the **POSIX identity** (owner + group) of a file or directory in a typed, comparable form.

### Fields

| Field   | Type     | Source |
|---------|----------|--------|
| `owner` | `?string`| `posix_getpwuid($uid)['name']` — `null` if `ext-posix` unavailable. |
| `group` | `?string`| `posix_getgrgid($gid)['name']` — `null` if `ext-posix` unavailable. |
| `uid`   | `?int`   | `fileowner($path)` — always available. |
| `gid`   | `?int`   | `filegroup($path)` — always available. |

> 💡 On Windows (without `ext-posix`), only `uid` / `gid` are populated — `owner` / `group` remain `null`. UID/GID values are also less meaningful than on POSIX, but consistent (always `0` or values emulated by the runtime).

### Usage

```php
use function oihana\files\getOwnershipInfos;

$info = getOwnershipInfos( '/var/www/html' ) ;

echo $info->owner ;   // 'www-data'
echo $info->group ;   // 'www-data'
echo $info->uid ;     // 33
echo $info->gid ;     // 33
echo $info ;          // 'www-data:www-data (33:33)'
```

### `equalsTo( OwnershipInfos $other ) : bool`

**Strict comparison** of the 4 fields (`uid`, `gid`, `owner`, `group`).

```php
$expected = new OwnershipInfos([
    'owner' => 'www-data' ,
    'group' => 'www-data' ,
    'uid'   => 33 ,
    'gid'   => 33 ,
]) ;

$actual = getOwnershipInfos( '/var/www/html' ) ;

if ( !$actual->equalsTo( $expected ) ) {
    throw new \RuntimeException( "Ownership mismatch on /var/www/html: got $actual" ) ;
}
```

### `__toString()`: `owner:group (uid:gid)` format

Readable display for logs, debug, error messages. `null` becomes `'?'` to remain unambiguous.

```php
echo new OwnershipInfos([ 'uid' => 1000 , 'gid' => 1000 ]) ;
// → '?:? (1000:1000)'  (without posix)

echo new OwnershipInfos([ 'owner' => 'alice' , 'group' => 'devs' , 'uid' => 1000 , 'gid' => 100 ]) ;
// → 'alice:devs (1000:100)'
```

### Use case: permission audit

```php
use function oihana\files\getOwnershipInfos;
use oihana\files\options\OwnershipInfos;

$expected = new OwnershipInfos([ 'owner' => 'www-data' , 'group' => 'www-data' ]) ;

$paths = [
    '/var/www/html' ,
    '/var/www/html/uploads' ,
    '/var/www/html/cache' ,
] ;

foreach ( $paths as $path ) {
    $actual = getOwnershipInfos( $path ) ;
    if ( !$actual->equalsTo( $expected ) ) {
        echo "[WARN] $path : owner mismatch, got $actual" , PHP_EOL ;
    }
}
```

### Use case: API response serialisation

```php
$info = getOwnershipInfos( $path ) ;

return new JsonResponse( $info ) ;
// → {"owner":"www-data","group":"www-data","uid":33,"gid":33}
//   (via inherited Options jsonSerialize())
```

### Links

- [System](../files/system.md#getownershipinfos) — the `getOwnershipInfos` function that produces this object.
- [`OwnershipInfo`](../enums.md) (singular) — the associated key enum.

---

## Side-by-side comparison

| | `MakeFileOptions` | `OwnershipInfos` |
|---|---|---|
| Extends | `Options` | `Options` |
| Role | **Input**: config passed to a function. | **Output**: data returned by a function. |
| Mutable? | Yes — built progressively. | Theoretically yes, but used read-only in practice. |
| Own method | (none) | `equalsTo()` |
| `__toString()` | returns the file path. | returns `owner:group (uid:gid)`. |
| Equivalent key enum | `MakeFileOption` (in `enums/`) | `OwnershipInfo` (in `enums/`) |

These two examples illustrate **two canonical uses** of the `Options` pattern: **passing a config** (`Make*Options`) and **representing a structured result** (`*Infos`). All `Options` classes in the oihana codebase fall into one or the other.

## See also

- [Options pattern](README.md) — the abstract `Options` class and its methods.
- [Creation](../files/creation.md#makefile) — usage of `MakeFileOptions`.
- [System](../files/system.md#getownershipinfos) — usage of `OwnershipInfos`.
- [Enums](../enums.md) — `MakeFileOption`, `OwnershipInfo`.
- [English TOC](../README.md).
