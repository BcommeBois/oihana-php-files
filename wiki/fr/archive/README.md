# Archives — `oihana\files\archive`

Le namespace `oihana\files\archive` rassemble deux boîtes à outils symétriques de fonctions standalone :

- **`oihana\files\archive\tar`** — archives **tar** (avec ou sans compression `gzip` / `bzip2`), construites par le **GNU tar** du système quand il y en a un, et par **`PharData`** (`ext-phar`) sinon. Voir [comment une archive tar est construite](tar-engine.md).
- **`oihana\files\archive\zip`** — archives **zip**, basées sur **`ZipArchive`** (`ext-zip`).

Les deux APIs sont volontairement **parallèles** : `zip`/`unzip` reflètent `tar`/`untar`, `zipDirectory` reflète `tarDirectory`, etc.

## Catalogue

| Catégorie | tar (`PharData`) | zip (`ZipArchive`) |
|---|---|---|
| **Création** | [`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory), [`tarBinary`](tar-engine.md) | [`zip`](zip.md#zip), [`zipDirectory`](zip.md#zipdirectory) |
| **Extraction** | [`untar`](untar.md#untar) | [`unzip`](unzip.md#unzip) |
| **Inspection** | [`tarFileInfo`](tar.md#tarfileinfo), [`tarIsCompressed`](tar.md#tariscompressed) | [`zipFileInfo`](zip.md#zipfileinfo) |
| **Validation** | [`assertTar`](untar.md#asserttar), [`hasTarExtension`](untar.md#hastarextension), [`hasTarMimeType`](untar.md#hastarmimetype), [`validateTarStructure`](untar.md#validatetarstructure) | [`assertZip`](unzip.md#assertzip), [`hasZipExtension`](unzip.md#haszipextension), [`hasZipMimeType`](unzip.md#haszipmimetype), [`validateZipStructure`](unzip.md#validatezipstructure) |

> ℹ️ Pas de `zipIsCompressed` : un zip est un conteneur dont la compression se décide **par entrée** (DEFLATE ou STORE), la notion globale « compressé ou non » n'a pas de sens.

## Formats supportés

| Format       | Extensions reconnues          | Compression       | Backend |
|--------------|-------------------------------|-------------------|---------|
| **tar**      | `.tar`                        | aucune            | GNU tar, sinon `PharData` |
| **tar.gz**   | `.tar.gz`, `.tgz`             | gzip (`ext-zlib`) | GNU tar, sinon `PharData` |
| **tar.bz2**  | `.tar.bz2`, `.tbz2`           | bzip2 (`ext-bz2`) | GNU tar, sinon `PharData` |
| **zip**      | `.zip`                        | DEFLATE ou STORE (par entrée) | `ZipArchive` (`ext-zip`) |

L'énumération [`CompressionType`](../enums.md#compressiontype) liste les valeurs canoniques (`gzip`, `bzip2`, `none`, `zip`).

## Principes communs

1. **Pas de subprocess, à une exception mesurée près.** `zip` passe par `ZipArchive`, et `tar` passait par `PharData` — portable, scriptable, testable, au prix d'une limite de taille. Pour tar, ce prix s'est révélé rédhibitoire : 311,8 secondes contre 2,1 sur le même arbre de 96 Mo, et un refus pur et simple de tout nom de fichier au-delà de 100 octets. Donc la **création d'archives tar** utilise le GNU tar du système quand il y en a un, et se rabat sur `PharData` partout ailleurs, en produisant les mêmes archives dans les deux cas. Tout le reste — extraction, inspection, validation, et l'intégralité de `zip` — demeure en PHP pur. Voir [comment une archive tar est construite](tar-engine.md).
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

- **Grosses archives** (> quelques Go) : `ZipArchive`, et `PharData` là où il reste le moteur, chargent des index en mémoire. La création d'une archive tar n'a plus ce plafond là où un GNU tar est disponible — [vérifier quel moteur est en place](tar-engine.md#savoir-quel-moteur-est-en-place).
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
