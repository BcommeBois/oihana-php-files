# Dépendances

`oihana/php-files` est volontairement **léger**. Sa `composer.json` ne liste que quatre dépendances *runtime* — toutes essentielles, aucune optionnelle.

## Vue d'ensemble

| Package | Version | Rôle | Utilisé dans |
|---|---|---|---|
| [`oihana/php-core`](https://github.com/BcommeBois/oihana-php-core) | `dev-main` | Helpers fonctionnels et énumérations de base | Path, files, options, archive, openssl, toml |
| [`oihana/php-reflect`](https://github.com/BcommeBois/oihana-php-reflect) | `dev-main` | Réflexion et hydratation par traits | `oihana\options\Options`, `tar()` |
| [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) | `dev-main` | Interfaces transverses (`Arrayable`, `Cloneable`, ...) | `oihana\options\Options` |
| [`devium/toml`](https://github.com/vanodevium/toml) | `^1.0` | Décodage de fichiers TOML | `oihana\files\toml\resolveTomlConfig` |

Le code source des dépendances `oihana/*` vit dans le même *organization* GitHub que `oihana/php-files`. Elles sont **versionnées sur `dev-main`** car l'écosystème évolue de pair — voir [tips.md](../tips.md) pour les implications.

## Détail par dépendance

### `oihana/php-core`

Le paquet fondateur. `oihana/php-files` en consomme deux types d'éléments :

**Énumérations** (`oihana\enums\*`) :

- [`Char`](https://github.com/BcommeBois/oihana-php-core) — constantes de caractères (`Char::SLASH`, `Char::BACKSLASH`, `Char::DOT`, `Char::EMPTY`, etc.). Utilisée **partout** dans la librairie pour éviter les *magic strings* `'/'`, `'\\'`, `'.'`.
- `Order` — `Order::ASC`, `Order::DESC`. Utilisée par `findFiles` et `sortFiles`.

**Fonctions** (`oihana\core\*`) :

| Fonction | Namespace | Utilisée par |
|---|---|---|
| `deepMerge` | `oihana\core\arrays` | `resolveTomlConfig`, `requireAndMergeArrays` |
| `formatDateTime` | `oihana\core\date` | `getTimestampedFile`, `getTimestampedDirectory` |
| `formatDocument` | `oihana\core\documents` | `oihana\options\Options::format()` |
| `formatFromDocument` | `oihana\core\strings` | `oihana\options\Options::formatFromDocument()` |
| `hyphenate` | `oihana\core\strings` | `oihana\options\Options::getOptions()` (génération CLI) |
| `isRegexp` | `oihana\core\strings` | `findFiles` (détection regex vs glob), `shouldExcludeFile` |
| `lower` | `oihana\core\strings` | normalisation MIME / extensions |

### `oihana/php-reflect`

Réflexion et introspection. `oihana/php-files` en utilise deux éléments :

- **`oihana\reflect\traits\ReflectionTrait`** — utilisé par `oihana\options\Options` pour énumérer les propriétés publiques d'un objet, les sérialiser, les hydrater depuis un tableau.
- **`oihana\reflect\traits\ConstantsTrait`** — utilisé par certaines énumérations *à constantes* du namespace (`FindFilesOption`, `FindFileOption`, etc.) qui ont besoin d'exposer `enum()`, `getAll()`, etc.
- **`oihana\reflect\helpers\getFunctionInfo`** — utilisée par `tar()` pour produire des messages d'erreur traçables avec le nom de fonction appelant.

### `oihana/php-enums`

Contient des **interfaces transverses** consommées par la classe `oihana\options\Options` :

- `oihana\interfaces\Arrayable` — contrat `toArray(): array`.
- `oihana\interfaces\ClearableArrayable` — extension avec `clear(): void`.
- `oihana\interfaces\Cloneable` — contrat `clone(): static`.

Ces interfaces permettent à du code générique de manipuler n'importe quel `Options` (ou descendant) sans le coupler à une implémentation concrète.

### `devium/toml`

Décodeur TOML léger et conforme à la spec [TOML 1.0](https://toml.io/). Utilisé exclusivement par `oihana\files\toml\resolveTomlConfig`.

- **Classe exposée** : `Devium\Toml\TomlError` (exception remontée en cas de TOML invalide).
- **Fonction utilisée** : `Devium\Toml\Toml::decode()` (en pratique encapsulée dans `resolveTomlConfig`).

Le choix de `devium/toml` plutôt qu'une alternative (`vlucas/phpdotenv`, `yosymfony/toml`) est motivé par : conformité stricte à la spec, zéro dépendance transitive, *active maintenance*.

## Dépendances de développement

Déclarées dans `require-dev` de `composer.json` — uniquement présentes si tu installes la librairie via `composer install` sur le clone, **pas** quand tu fais `composer require oihana/php-files` dans ton projet :

| Package | Version | Rôle |
|---|---|---|
| [`phpunit/phpunit`](https://github.com/sebastianbergmann/phpunit) | `^12` | Framework de tests unitaires. |
| [`nunomaduro/collision`](https://github.com/nunomaduro/collision) | `^8.8` | Affichage coloré des erreurs PHPUnit. |
| [`mikey179/vfsstream`](https://github.com/bovigo/vfsStream) | `^1.6` | Simulation d'un système de fichiers virtuel (tests sans I/O réelle). |
| [`phpdocumentor/shim`](https://github.com/phpDocumentor/shim) | `^3.8` | Wrapper pour exécuter phpDocumentor via Composer. |
| `ext-posix`, `ext-zlib` | — | Extensions PHP requises uniquement pour certains tests (ownership, gzip). |

## Mapping namespace → package

| Namespace rencontré dans la doc | Package source |
|---|---|
| `oihana\files\*` | `oihana/php-files` (présente librairie) |
| `oihana\options\*` | `oihana/php-files` (présente librairie) |
| `oihana\core\*` | `oihana/php-core` |
| `oihana\enums\*` (`Char`, `Order`) | `oihana/php-core` |
| `oihana\reflect\*` | `oihana/php-reflect` |
| `oihana\interfaces\*` | `oihana/php-enums` |
| `Devium\Toml\*` | `devium/toml` |

## Et la suite ?

- [Glossaire](glossary.md) — termes récurrents.
- [Introduction](introduction.md) — vue d'ensemble.
- [Sommaire FR](../README.md) — table des matières complète.
