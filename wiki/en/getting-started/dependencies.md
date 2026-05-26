# Dependencies

`oihana/php-files` is intentionally **lightweight**. Its `composer.json` lists only four *runtime* dependencies — all essential, none optional.

## Overview

| Package | Version | Role | Used in |
|---|---|---|---|
| [`oihana/php-core`](https://github.com/BcommeBois/oihana-php-core) | `dev-main` | Functional helpers and base enums | Path, files, options, archive, openssl, toml |
| [`oihana/php-reflect`](https://github.com/BcommeBois/oihana-php-reflect) | `dev-main` | Reflection and trait-based hydration | `oihana\options\Options`, `tar()` |
| [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) | `dev-main` | Cross-cutting interfaces (`Arrayable`, `Cloneable`, ...) | `oihana\options\Options` |
| [`devium/toml`](https://github.com/vanodevium/toml) | `^1.0` | TOML file decoding | `oihana\files\toml\resolveTomlConfig` |

The source code of the `oihana/*` dependencies lives in the same GitHub *organization* as `oihana/php-files`. They are **versioned on `dev-main`** because the ecosystem evolves together — see [tips.md](../tips.md) for implications.

## Per-dependency detail

### `oihana/php-core`

The foundational package. `oihana/php-files` consumes two kinds of items from it:

**Enums** (`oihana\enums\*`):

- [`Char`](https://github.com/BcommeBois/oihana-php-core) — character constants (`Char::SLASH`, `Char::BACKSLASH`, `Char::DOT`, `Char::EMPTY`, etc.). Used **everywhere** in the library to avoid *magic strings* like `'/'`, `'\\'`, `'.'`.
- `Order` — `Order::ASC`, `Order::DESC`. Used by `findFiles` and `sortFiles`.

**Functions** (`oihana\core\*`):

| Function | Namespace | Used by |
|---|---|---|
| `deepMerge` | `oihana\core\arrays` | `resolveTomlConfig`, `requireAndMergeArrays` |
| `formatDateTime` | `oihana\core\date` | `getTimestampedFile`, `getTimestampedDirectory` |
| `formatDocument` | `oihana\core\documents` | `oihana\options\Options::format()` |
| `formatFromDocument` | `oihana\core\strings` | `oihana\options\Options::formatFromDocument()` |
| `hyphenate` | `oihana\core\strings` | `oihana\options\Options::getOptions()` (CLI generation) |
| `isRegexp` | `oihana\core\strings` | `findFiles` (regex vs glob detection), `shouldExcludeFile` |
| `lower` | `oihana\core\strings` | MIME / extension normalisation |

### `oihana/php-reflect`

Reflection and introspection. `oihana/php-files` uses three items:

- **`oihana\reflect\traits\ReflectionTrait`** — used by `oihana\options\Options` to enumerate public properties of an object, serialise them, hydrate them from an array.
- **`oihana\reflect\traits\ConstantsTrait`** — used by some *constants-based* enums in the namespace (`FindFilesOption`, `FindFileOption`, etc.) which need to expose `enum()`, `getAll()`, etc.
- **`oihana\reflect\helpers\getFunctionInfo`** — used by `tar()` to produce traceable error messages including the caller function name.

### `oihana/php-enums`

Contains **cross-cutting interfaces** consumed by the `oihana\options\Options` class:

- `oihana\interfaces\Arrayable` — contract `toArray(): array`.
- `oihana\interfaces\ClearableArrayable` — extension with `clear(): void`.
- `oihana\interfaces\Cloneable` — contract `clone(): static`.

Those interfaces let generic code manipulate any `Options` (or descendant) without coupling to a concrete implementation.

### `devium/toml`

Lightweight TOML decoder, compliant with the [TOML 1.0](https://toml.io/) specification. Used exclusively by `oihana\files\toml\resolveTomlConfig`.

- **Exposed class**: `Devium\Toml\TomlError` (exception thrown on invalid TOML).
- **Function used**: `Devium\Toml\Toml::decode()` (in practice wrapped inside `resolveTomlConfig`).

`devium/toml` was chosen over alternatives (`vlucas/phpdotenv`, `yosymfony/toml`) for: strict spec compliance, zero transitive dependencies, *active maintenance*.

## Development dependencies

Declared in `require-dev` of `composer.json` — only present when you install the library via `composer install` on the clone, **not** when you run `composer require oihana/php-files` in your project:

| Package | Version | Role |
|---|---|---|
| [`phpunit/phpunit`](https://github.com/sebastianbergmann/phpunit) | `^12` | Unit test framework. |
| [`nunomaduro/collision`](https://github.com/nunomaduro/collision) | `^8.8` | Pretty-printed PHPUnit error output. |
| [`mikey179/vfsstream`](https://github.com/bovigo/vfsStream) | `^1.6` | Virtual filesystem simulation (tests without real I/O). |
| [`phpdocumentor/shim`](https://github.com/phpDocumentor/shim) | `^3.8` | Wrapper to run phpDocumentor via Composer. |
| `ext-posix`, `ext-zlib` | — | PHP extensions required only for some tests (ownership, gzip). |

## Namespace → package mapping

| Namespace seen in the docs | Source package |
|---|---|
| `oihana\files\*` | `oihana/php-files` (this library) |
| `oihana\options\*` | `oihana/php-files` (this library) |
| `oihana\core\*` | `oihana/php-core` |
| `oihana\enums\*` (`Char`, `Order`) | `oihana/php-core` |
| `oihana\reflect\*` | `oihana/php-reflect` |
| `oihana\interfaces\*` | `oihana/php-enums` |
| `Devium\Toml\*` | `devium/toml` |

## What's next?

- [Glossary](glossary.md) — recurring terms.
- [Introduction](introduction.md) — overview.
- [English TOC](../README.md) — full table of contents.
