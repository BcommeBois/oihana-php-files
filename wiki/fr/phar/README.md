# Phar — `oihana\files\phar`

Quatre helpers autour de la classe PHP native **`PharData`**, principalement utilisés en interne par le module [archive/tar](../archive/README.md) mais réutilisables seuls.

- [`assertPhar`](#assertphar) — vérifie que l'extension `phar` est chargée.
- [`getPharBasePath`](#getpharbasepath) — construit une URI `phar://...` pour accéder au contenu interne d'une archive.
- [`getPharCompressionType`](#getpharcompressiontype) — convertit `CompressionType::*` → constante `Phar::*`.
- [`preservePharFilePermissions`](#preservepharfilepermissions) — restaure les permissions d'origine après extraction.

> 💡 Tous ces helpers vivent dans le namespace `oihana\files\phar` et sont autochargés via `composer.autoload.files`.

## Pourquoi un module dédié ?

PHP fournit `PharData` (classe native, dans l'extension `ext-phar`) pour lire et écrire des archives `.phar`, `.tar`, `.tar.gz`, `.tar.bz2`, `.zip`. Mais l'API native a des angles morts ergonomiques :

- pas de helper pour mapper la **chaîne** de compression (`'gzip'`, `'bz2'`) vers la **constante** `Phar::GZ` / `Phar::BZ2` ;
- pas de fonction pour construire l'URI `phar://...` à partir d'une instance ;
- pas de fonction de **safeguard** pour vérifier la disponibilité de l'extension avant de l'utiliser ;
- les permissions stockées dans l'archive ne sont pas restaurées automatiquement par `extractTo`.

Ce module comble ces gaps.

---

## `assertPhar`

```php
assertPhar() : void
```

**Safeguard** à appeler avant toute opération `PharData`. Vérifie deux choses :

1. La classe `PharData` existe (`class_exists`).
2. L'extension `phar` est chargée (`extension_loaded`).

**Lève `RuntimeException`** si l'une des deux échoue.

```php
use function oihana\files\phar\assertPhar;

try {
    assertPhar() ;
    $phar = new \PharData('/path/to/archive.tar') ;
    // ... opérations Phar
}
catch ( \RuntimeException $e ) {
    echo "Phar support indisponible : " . $e->getMessage() ;
}
```

> 💡 En pratique, sur la plupart des distributions PHP (Debian/Ubuntu/Mac brew/Windows), `ext-phar` est compilé par défaut et toujours présent. `assertPhar` est utile pour les environnements minimalistes (PHP buildé from-source avec `--disable-phar`, conteneurs Docker custom).

---

## `getPharBasePath`

```php
getPharBasePath( PharData $phar ) : string
```

Retourne l'**URI `phar://`** pointant vers la racine de l'archive — utilisée pour accéder aux fichiers internes via les *stream wrappers* PHP.

```php
use function oihana\files\phar\getPharBasePath;

$phar = new \PharData('/absolute/path/to/archive.tar') ;
$baseUri = getPharBasePath( $phar ) ;
// → 'phar:///absolute/path/to/archive.tar'

// Lire un fichier interne sans extraction
$content = file_get_contents( $baseUri . '/docs/readme.txt' ) ;

// Lister
$files = scandir( $baseUri ) ;
```

**Détail :** la fonction utilise `realpath()` sur le chemin de l'archive pour garantir une URI absolue, même si tu as ouvert le `PharData` avec un chemin relatif.

Utilisé en interne par [`untar`](../archive/untar.md#untar) pour parcourir l'archive sans la décompresser sur disque.

---

## `getPharCompressionType`

```php
getPharCompressionType( string $compression ) : int
```

Convertit une **chaîne** (`CompressionType::*`) en **constante** `Phar::*` numérique. Pratique pour appeler `PharData::compress($pharConstant)` à partir d'une option utilisateur stockée en string.

### Mapping

| Entrée                    | Sortie       |
|---------------------------|--------------|
| `CompressionType::GZIP`   | `Phar::GZ`   |
| `CompressionType::BZIP2`  | `Phar::BZ2`  |
| `CompressionType::NONE`   | `Phar::NONE` |
| toute autre valeur        | `UnsupportedCompressionException` |

```php
use function oihana\files\phar\getPharCompressionType;
use oihana\files\enums\CompressionType;

$compression = CompressionType::GZIP ;
$pharConstant = getPharCompressionType( $compression ) ;
// → 4096 (la valeur numérique de Phar::GZ)

$phar = new \PharData('/path/to/archive.tar') ;
$phar->compress( $pharConstant ) ;

// Valeur invalide → exception
getPharCompressionType( 'rar' ) ;
// → UnsupportedCompressionException: Compression type 'rar' is not supported
```

Utilisé en interne par [`tar`](../archive/tar.md#tar) pour passer du type chaîne à la constante Phar attendue par l'API native.

---

## `preservePharFilePermissions`

```php
preservePharFilePermissions( PharData $phar , string $outputPath ) : void
```

**Restaure les permissions d'origine** (mode `chmod`) des fichiers contenus dans une archive, **après** que celle-ci a été extraite via `extractTo`.

`PharData::extractTo` extrait les fichiers mais leur applique les permissions **par défaut** du process (umask) — pas celles stockées dans l'archive. Ce helper rebadge les fichiers extraits avec leurs vraies perms.

### Comportement

- Itère sur les fichiers de l'archive.
- Pour chaque fichier présent dans `$outputPath` à `basename(file)`, applique `chmod($filePath, $file->getPerms())`.
- En cas d'erreur, **log un warning** via `error_log()` et continue (pas d'exception bloquante).

```php
use function oihana\files\phar\{ assertPhar , preservePharFilePermissions } ;

assertPhar() ;

$phar = new \PharData('/archives/app.tar') ;
$phar->extractTo('/var/www/app' , null , true ) ;

preservePharFilePermissions( $phar , '/var/www/app' ) ;
// Restaure les modes (ex. 0755 pour les binaires exécutables)
```

### ⚠ Limitations

- **Comparaison basename**, pas par chemin relatif complet — si l'archive contient deux fichiers du même nom dans des sous-dossiers différents, le comportement est imprévisible. Cas rare en pratique mais à connaître.
- **Pas de restauration owner/group** — uniquement le mode (`chmod`). Pour owner/group, utiliser `chown`/`chgrp` séparément (cf. [`getOwnershipInfos`](../files/system.md#getownershipinfos)).

Utilisé en interne par [`untar`](../archive/untar.md#untar) avec l'option `keepPermissions: true`.

---

## Quand utiliser ce module directement ?

Dans 95% des cas, tu n'as **pas besoin** de toucher au namespace `phar/` — les fonctions de haut niveau ([`tar`](../archive/tar.md), [`untar`](../archive/untar.md), [`tarFileInfo`](../archive/tar.md#tarfileinfo)) orchestrent tout.

Utilise les helpers directement si :

- tu travailles avec **`PharData` brut** pour un cas non couvert par le module `archive/tar` (ex. archives `.zip`, manipulation fine d'un Phar exécutable) ;
- tu veux **lire un fichier interne sans extraction** → `getPharBasePath` + `file_get_contents` ;
- tu construis ta propre boucle d'extraction et veux **restaurer manuellement** les permissions.

## Voir aussi

- [Archive (tar)](../archive/README.md) — le module qui consomme ces helpers en pratique.
- [`untar`](../archive/untar.md#untar) — utilise `preservePharFilePermissions` avec l'option `keepPermissions`.
- [Énumérations](../enums.md) — `CompressionType`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`.
- [Sommaire FR](../README.md).
