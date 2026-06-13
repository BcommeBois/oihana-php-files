# Archives — `oihana\files\archive`

Le namespace `oihana\files\archive` rassemble deux boîtes à outils symétriques de fonctions standalone :

- **`oihana\files\archive\tar`** — archives **tar** (avec ou sans compression `gzip` / `bzip2`), basées sur **`PharData`** (`ext-phar`).
- **`oihana\files\archive\zip`** — archives **zip**, basées sur **`ZipArchive`** (`ext-zip`).

Les deux APIs sont volontairement **parallèles** : `zip`/`unzip` reflètent `tar`/`untar`, `zipDirectory` reflète `tarDirectory`, etc.

## Catalogue

| Catégorie | tar (`PharData`) | zip (`ZipArchive`) |
|---|---|---|
| **Création** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) | [`zip`](zip.md#zip), [`zipDirectory`](zip.md#zipdirectory) |
| **Extraction** | [`untar`](untar.md#untar) | [`unzip`](unzip.md#unzip) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) | [`zipFileInfo`](zip.md#zipfileinfo) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) | [`assertZip`](unzip.md#assertzip), [`hasZipExtension`](unzip.md#haszipextension), [`hasZipMimeType`](unzip.md#haszipmimetype), [`validateZipStructure`](unzip.md#validatezipstructure) |

> ℹ️ Pas de `zipIsCompressed` : un zip est un conteneur dont la compression se décide **par entrée** (DEFLATE ou STORE), la notion globale « compressé ou non » n'a pas de sens.

## Formats supportés

| Format       | Extensions reconnues          | Compression       | Backend |
|--------------|-------------------------------|-------------------|---------|
| **tar**      | `.tar`                        | aucune            | `PharData` |
| **tar.gz**   | `.tar.gz`, `.tgz`             | gzip (`ext-zlib`) | `PharData` |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`           | bzip2 (`ext-bz2`) | `PharData` |
| **zip**      | `.zip`                        | DEFLATE ou STORE (par entrée) | `ZipArchive` (`ext-zip`) |

L'énumération [`CompressionType`](../enums.md#compressiontype) liste les valeurs canoniques (`gzip`, `bzip2`, `none`, `zip`).

## Principes communs

1. **Pas de subprocess.** Tout passe par `PharData` / `ZipArchive` — pas de `exec('tar ...')` / `exec('zip ...')`. Portable, scriptable, testable. Inconvénient : limite de taille (mémoire/temps PHP).
2. **Dossiers vides préservés.** `tar` comme `zip` préservent les sous-dossiers vides via `addEmptyDir`.
3. **Sécurité à l'extraction.** `untar` et `unzip` détectent les tentatives de **path traversal** (`..`) — protection *Zip Slip* / *Tar Slip*. `unzip` ajoute des plafonds **anti-bombe** (`maxEntries`, `maxSize`).
4. **Validation à plusieurs niveaux.** Extension (rapide) → MIME via `finfo` → structure (parse).

## Cas d'usage typique

```php
use function oihana\files\archive\zip\{ zipDirectory , unzip , zipFileInfo } ;
use oihana\files\enums\ZipOption;

// 1. Créer une archive d'un dossier
$archive = zipDirectory( '/var/www/site' , null , '/backups/site.zip' ) ;

// 2. Inspecter
$info = zipFileInfo( $archive ) ;
echo "Fichiers : {$info['fileCount']}, taille : {$info['totalSize']} bytes" ;

// 3. Extraire ailleurs, avec garde-fous anti-bombe
unzip( $archive , '/tmp/restored' , [
    ZipOption::MAX_SIZE => 200 * 1024 * 1024 ,
]) ;
```

## ⚠ Limites connues

- **Grosses archives** (> quelques GB) : `PharData` / `ZipArchive` chargent des index en mémoire — privilégier les outils CLI streaming pour les très gros volumes.
- **Symlinks (tar)** : `PharData` recrée le symlink, pas la cible.
- **`validateTarStructure`** ne supporte pas les tars compressés (décompresser d'abord).
- **Permissions Windows (zip)** : un zip sans attributs `OPSYS_UNIX` n'a pas de mode à restaurer — `unzip(..., keepPermissions: true)` laisse alors le mode par défaut.

## Voir aussi

- [Créer un tar](tar.md) · [Extraire un tar](untar.md)
- [Créer un zip](zip.md) · [Extraire un zip](unzip.md)
- [Énumérations](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`, `ZipOption`, `ZipInfo`.
- [Exceptions](../exceptions.md) — `UnsupportedCompressionException`, `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — helpers Phar utilisés en interne par tar.
- [Sommaire FR](../README.md).
