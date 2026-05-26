# Archives — `oihana\files\archive\tar`

Le namespace `oihana\files\archive\tar` rassemble **9 fonctions standalone** pour créer, extraire et inspecter des archives **tar** (avec ou sans compression `gzip` / `bzip2`).

> 💡 Implémentation basée sur **`PharData`** natif (extension `ext-phar`, activée par défaut dans PHP). Pas de dépendance externe.

## Catalogue

| Catégorie | Fonctions |
|---|---|
| **Création** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) |
| **Extraction** | [`untar`](untar.md#untar) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) |

## Formats supportés

| Format       | Extensions reconnues          | Compression       | Mode d'écriture |
|--------------|-------------------------------|-------------------|-----------------|
| **tar**      | `.tar`                        | aucune            | natif           |
| **tar.gz**   | `.tar.gz`, `.tgz`             | gzip              | natif (`ext-zlib`) |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`           | bzip2             | natif (`ext-bz2`)  |

L'énumération [`CompressionType`](../enums.md#compressiontype) liste les valeurs canoniques (`gz`, `bz2`, `none`).

## Principes

1. **Pas de subprocess.** Tout passe par `PharData` — pas de `exec('tar ...')`. Avantage : portable, scriptable, testable. Inconvénient : limite de taille (mémoire/temps PHP).
2. **Dossiers vides préservés.** Contrairement à un `cp -r` naïf, `tar` préserve les sous-dossiers vides via `addEmptyDir`.
3. **Sécurité à l'extraction.** `untar` détecte les tentatives de **path traversal** (`..`) dans les noms d'entrées de l'archive — protection contre les attaques *Zip Slip* / *Tar Slip*.
4. **Validation à plusieurs niveaux.** `hasTarExtension` (rapide, juste le nom), `hasTarMimeType` (lecture des premiers octets via `finfo`), `validateTarStructure` (parse + itération via `PharData`).

## Cas d'usage typique

```php
use function oihana\files\archive\tar\{ tarDirectory , untar , tarFileInfo } ;
use oihana\files\enums\CompressionType;

// 1. Créer une archive compressée d'un dossier
$archive = tarDirectory(
    '/var/www/site' ,
    CompressionType::GZIP ,
    '/backups/site.tar.gz' ,
) ;

// 2. Inspecter
$info = tarFileInfo( $archive ) ;
echo "Fichiers : {$info['fileCount']}, taille : {$info['totalSize']} bytes" ;

// 3. Extraire ailleurs (avec protection path-traversal)
untar( $archive , '/tmp/restored' ) ;
```

## ⚠ Limites connues

- **`validateTarStructure` ne supporte pas les tars compressés** — il faut décompresser d'abord (ce que fait `untar` en interne).
- **Symlinks** : `PharData` les sérialise comme symlinks — l'extraction recrée le symlink, **pas la cible**. À garder en tête pour les archives portables.
- **Grosses archives** (> quelques GB) : `PharData` charge des index en mémoire — privilégier `tar` CLI ou des outils streaming pour les très gros volumes.
- **Compression réelle** : `PharData::compress()` peut échouer silencieusement si l'extension correspondante (`ext-zlib`, `ext-bz2`) n'est pas chargée. `tar()` lève alors `UnsupportedCompressionException`.

## Voir aussi

- [Créer une archive](tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, `tarIsCompressed`.
- [Extraire une archive](untar.md) — `untar` et les fonctions de validation.
- [Énumérations](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`, `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — helpers Phar utilisés en interne (`getPharCompressionType`, `preservePharFilePermissions`).
- [Sommaire FR](../README.md).
