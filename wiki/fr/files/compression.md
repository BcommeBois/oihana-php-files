# Compression (fichier unique)

Compression et décompression d'un **fichier unique** hors archive tar, en **streaming par chunks** (pas de subprocess, le fichier n'est jamais chargé entièrement en mémoire).

- [`gzipFile`](#gzipfile) / [`gunzipFile`](#gunzipfile) — gzip (DEFLATE), via `ext-zlib`.
- [`bzip2File`](#bzip2file) / [`bunzip2File`](#bunzip2file) — bzip2, via `ext-bz2`.

> 💡 Pour compresser **plusieurs** fichiers / dossiers, voir les archives [tar](../archive/tar.md) et [zip](../archive/zip.md). Ces fonctions ciblent le cas « un fichier → un fichier compressé ».
>
> ⚠ `ext-zlib` et `ext-bz2` sont déclarées en `suggest` de `composer.json`. Si l'extension manque, la fonction lève une `FileException` explicite.

---

## `gzipFile`

```php
gzipFile(
    string  $source ,
    ?string $destination = null ,
    int     $level       = -1 ,
    bool    $overwrite   = true
) : string
```

Compresse `$source` en gzip. Destination par défaut : `$source` + `.gz`. Le niveau `$level` va de `0` à `9` ; `-1` (défaut) utilise le niveau par défaut de zlib.

**Retourne** le chemin de destination.

| Cas | Exception |
|---|---|
| Source absente / illisible | `FileException` (via `assertFile`) |
| `ext-zlib` absente | `FileException` |
| Destination existante avec `$overwrite = false` | `FileException` |
| Échec d'ouverture de la destination | `FileException` |

```php
use function oihana\files\gzipFile;

gzipFile( '/var/log/app.log' ) ;                    // -> /var/log/app.log.gz
gzipFile( '/data/dump.sql' , '/data/dump.gz' , 9 ) ; // niveau maximal
```

---

## `gunzipFile`

```php
gunzipFile(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Décompresse un fichier gzip. Destination par défaut : `$source` **sans** son suffixe `.gz`, ou `$source` + `.out` si le suffixe est absent.

```php
use function oihana\files\gunzipFile;

gunzipFile( '/var/log/app.log.gz' ) ; // -> /var/log/app.log
```

---

## `bzip2File`

```php
bzip2File(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Compresse `$source` en bzip2. Destination par défaut : `$source` + `.bz2`. Contrairement à `gzipFile`, **aucun niveau** n'est exposé : `bzopen()` n'en accepte pas en mode streaming.

```php
use function oihana\files\bzip2File;

bzip2File( '/data/dump.sql' ) ; // -> /data/dump.sql.bz2
```

---

## `bunzip2File`

```php
bunzip2File(
    string  $source ,
    ?string $destination = null ,
    bool    $overwrite   = true
) : string
```

Décompresse un fichier bzip2. Destination par défaut : `$source` **sans** son suffixe `.bz2`, ou `$source` + `.out` si le suffixe est absent.

```php
use function oihana\files\bunzip2File;

bunzip2File( '/data/dump.sql.bz2' ) ; // -> /data/dump.sql
```

---

## Voir aussi

- [Archives tar](../archive/tar.md) / [zip](../archive/zip.md) — compression multi-fichiers.
- [Énumérations](../enums.md#compressiontype) — `CompressionType`.
- [Exceptions](../exceptions.md) — `FileException`.
- [Vue d'ensemble](README.md).
