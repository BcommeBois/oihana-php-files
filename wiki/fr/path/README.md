# Chemins — `oihana\files\path`

Le namespace `oihana\files\path` rassemble **14 fonctions standalone** pour manipuler des chemins de fichiers et de dossiers de façon portable (Unix, Windows, URL/scheme PHP comme `phar://`).

> 💡 **Toutes ces fonctions sont autochargées** via `composer.autoload.files` — pas besoin de `use function`, mais l'IDE l'apprécie. Aucune n'accède au système de fichiers (sauf `canonicalizePath` qui résout `~` via `getHomeDirectory`).

## Principes

1. **Manipulation purement textuelle.** Aucune validation d'existence (sauf `~` expansion). Tu peux normaliser un chemin inexistant — utile pour la génération de chemins, les tests, etc.
2. **Préservation du *scheme*.** Les chemins URL-style (`phar://`, `file://`, `vfs://`) sont préservés à travers les opérations.
3. **Slashes uniformes.** Toutes les fonctions renvoient des chemins avec `/` même quand l'entrée utilise `\` (Windows). Exception : `directoryPath` reconstruit avec le séparateur d'entrée si l'entrée contient des `\`.
4. **Cache LRU sur `canonicalizePath`.** Les chemins canonicalisés sont mis en cache mémoire (voir [`CanonicalizeBuffer`](../enums.md#canonicalizebuffer)) — utile dans les boucles serrées sur les mêmes chemins.

## Catalogue

| Catégorie | Fonctions |
|---|---|
| **Jointure et normalisation** | [`joinPaths`](joining-and-normalizing.md#joinpaths), [`normalizePath`](joining-and-normalizing.md#normalizepath), [`canonicalizePath`](joining-and-normalizing.md#canonicalizepath), [`extractCanonicalParts`](joining-and-normalizing.md#extractcanonicalparts) |
| **Absolu / relatif** | [`isAbsolutePath`](absolute-vs-relative.md#isabsolutepath), [`isRelativePath`](absolute-vs-relative.md#isrelativepath), [`makeAbsolute`](absolute-vs-relative.md#makeabsolute), [`makeRelative`](absolute-vs-relative.md#makerelative), [`computeRelativePath`](absolute-vs-relative.md#computerelativepath), [`relativePath`](absolute-vs-relative.md#relativepath) |
| **Inspection** | [`splitPath`](inspection.md#splitpath), [`directoryPath`](inspection.md#directorypath), [`isLocalPath`](inspection.md#islocalpath), [`isBasePath`](inspection.md#isbasepath) |

## Cas d'usage typique

```php
use function oihana\files\path\joinPaths;
use function oihana\files\path\makeAbsolute;
use function oihana\files\path\isBasePath;

$base = '/var/www';

// Construire un chemin propre à partir de fragments
$logFile = joinPaths( $base, 'logs', '..', 'logs/app.log' );
// → '/var/www/logs/app.log'

// Garantir l'absolu à partir d'un input utilisateur potentiellement relatif
$absolute = makeAbsolute( $userInput, $base );

// Sécurité : refuser d'écrire en dehors du dossier racine autorisé
if ( !isBasePath( $base, $absolute ) ) {
    throw new \RuntimeException("Path escape attempt: $absolute");
}
```

## Voir aussi

- [Jointure et normalisation](joining-and-normalizing.md) — `joinPaths`, `normalizePath`, `canonicalizePath`, `extractCanonicalParts`.
- [Absolu vs relatif](absolute-vs-relative.md) — détection (`isAbsolutePath`/`isRelativePath`) et conversion (`makeAbsolute`/`makeRelative`/`computeRelativePath`/`relativePath`).
- [Inspection](inspection.md) — `splitPath`, `directoryPath`, `isLocalPath`, `isBasePath`.
- [Sommaire FR](../README.md) — retour à la table des matières.
