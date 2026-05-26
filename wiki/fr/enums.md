# Catalogue des énumérations

`oihana/php-files` expose **18 classes d'énumérations** (constantes typées) + **3 traits MIME** sous `oihana\files\enums\`. Toutes sont des **classes de constantes**, pas des `enum` natifs PHP — elles utilisent le trait [`ConstantsTrait`](../getting-started/dependencies.md#oihanaphp-reflect) qui expose `enum()`, `getAll()`, etc.

> 💡 Pourquoi des classes de constantes plutôt que des `enum` PHP 8.1 ? Les `enum` natifs ne permettent pas l'**héritage** ni les **constantes composées** (array). Les classes de constantes permettent par exemple `FileMimeType::AI = ['application/postscript', 'application/illustrator']` (MIME multiple) — impossible avec un `enum` natif.

## Vue d'ensemble par catégorie

| Catégorie | Classes |
|---|---|
| **MIME types** | [`FileMimeType`](#filemimetype), [`ImageMimeType`](#imagemimetype), [`AudioMimeType`](#audiomimetype), [`VideoMimeType`](#videomimetype) |
| **Extensions de fichier** | [`FileExtension`](#fileextension), [`TarExtension`](#tarextension) |
| **Formats & compression** | [`CompressionType`](#compressiontype), [`ImageFormat`](#imageformat) |
| **Options (clés de tableaux)** | [`FindFilesOption`](#findfilesoption), [`FindFileOption`](#findfileoption), [`MakeFileOption`](#makefileoption), [`MakeDirectoryOption`](#makedirectoryoption), [`RecursiveFilePathsOption`](#recursivefilepathsoption), [`TarOption`](#taroption) |
| **Modes / énumérations métier** | [`FindMode`](#findmode) |
| **Résultats structurés (clés)** | [`OwnershipInfo`](#ownershipinfo), [`TarInfo`](#tarinfo) |
| **État interne** | [`CanonicalizeBuffer`](#canonicalizebuffer) |
| **Traits réutilisables** | [`ImageMimeTypeTrait`, `AudioMimeTypeTrait`, `VideoMimeTypeTrait`](#traits-mime) |

---

## MIME types

### `FileMimeType`

**Catalogue principal** de types MIME — **56 constantes** couvrant documents, images, audio, vidéo, archives, formats spécialisés (`cbor`, `cose`, `cose.enc`).

Inclut les types **composés** (constante = array) pour les formats qui peuvent matcher plusieurs MIME : par exemple `FileMimeType::AI = ['application/postscript', 'application/illustrator']`.

```php
use oihana\files\enums\FileMimeType;

FileMimeType::PDF  ;  // 'application/pdf'
FileMimeType::JSON ;  // 'application/json'
FileMimeType::CBOR ;  // 'application/cbor'

// Multi-MIME
FileMimeType::AI ;   // ['application/postscript', 'application/illustrator']
```

Méthodes utilitaires (héritées de `ConstantsTrait`) : `getAll()`, `enum()`, etc.

### `ImageMimeType`

**14 constantes** pour les images : `AVIF`, `BMP`, `CUR`, `GIF`, `HEIC`, `HEIF`, `ICO`, `JPEG`, `JPG`, `PNG`, `SVG`, `TIF`, `TIFF`, `WEBP`.

Délègue ses constantes au trait [`ImageMimeTypeTrait`](#traits-mime) — permet à n'importe quelle classe d'enum custom de les hériter.

```php
ImageMimeType::JPG  ;  // 'image/jpeg'
ImageMimeType::SVG  ;  // 'image/svg+xml'
ImageMimeType::AVIF ;  // 'image/avif'
```

### `AudioMimeType`

**7 constantes** : `AAC`, `FLAC`, `M4A`, `MP3`, `OGG`, `WAV`, `WMA`. Via [`AudioMimeTypeTrait`](#traits-mime).

### `VideoMimeType`

**10 constantes** : couvre MP4, WebM, AVI, MKV, MOV, etc. Via [`VideoMimeTypeTrait`](#traits-mime).

### Traits MIME

Les **3 traits** (`AudioMimeTypeTrait`, `ImageMimeTypeTrait`, `VideoMimeTypeTrait`) découplent la déclaration des constantes du choix de la classe. Permet de **composer** :

```php
class MediaMimeType
{
    use ConstantsTrait , AudioMimeTypeTrait , VideoMimeTypeTrait ;
}

MediaMimeType::MP3 ; // 'audio/mpeg'
MediaMimeType::MP4 ; // 'video/mp4'
```

---

## Extensions de fichier

### `FileExtension`

**89 constantes** — la classe la plus volumineuse. Couvre toutes les extensions standards (images, audio, vidéo, documents, archives, code source) **avec le point** :

```php
FileExtension::PNG    ;  // '.png'
FileExtension::TAR_GZ ;  // '.tar.gz'
FileExtension::CBOR   ;  // '.cbor'
FileExtension::COSE   ;  // '.cose'
FileExtension::ENCRYPTED ; // '.enc'
```

#### Méthodes utilitaires

| Méthode | Rôle |
|---|---|
| `getFromMimeType( string $mimeType ): array\|string\|null` | Extension(s) correspondant à un MIME donné. |
| `getMimeType( string $extension ): string\|array\|null` | MIME correspondant à une extension (inverse). |
| `getMultiplePartExtensions( ?array $customs = [] ): array` | Liste des extensions composées (`.tar.gz`, `.blade.php`...). Utilisée par [`getBaseFileName`](files/system.md#getbasefilename) et [`getFileExtension`](files/system.md#getfileextension). |
| `resetCaches(): void` | Vide les caches internes (mappings extension ↔ MIME). |

### `TarExtension`

**12 constantes** spécifiques aux archives tar :

```
.tar, .tar.gz, .tar.bz2, .tar.xz, .tar.lz, .tar.lzo, .tar.lzma, .tar.zst, .tar.Z, .tbz2, .txz, .tgz
```

#### Méthodes utilitaires

| Méthode | Rôle |
|---|---|
| `getExtensionForCompression( string $compression ): string` | Renvoie l'extension complète pour un type de compression (`gzip` → `.tar.gz`). Lève `UnsupportedCompressionException`. |
| `getCompressionExtension( string $compression ): string` | Renvoie uniquement le suffixe de compression (`gzip` → `.gz`). |

---

## Formats & compression

### `CompressionType`

**8 constantes** + 3 méthodes utilitaires :

| Constante | Valeur |
|---|---|
| `NONE` | `'none'` |
| `GZIP` | `'gzip'` |
| `BZIP2` | `'bzip2'` |
| `ZIP` | `'zip'` |
| `LZ4` | `'lz4'` |
| `LZMA` | `'lzma'` |
| `XZ` | `'xz'` |
| `ZSTD` | `'zstd'` |

> ⚠ Les 8 constantes sont définies mais **seules `NONE`, `GZIP` et `BZIP2`** sont supportées par les fonctions [`tar`](archive/tar.md) / [`untar`](archive/untar.md). Les autres lèveront `UnsupportedCompressionException`.

| Méthode | Renvoie |
|---|---|
| `getDefault()` | `'gzip'` |
| `getFastCompressionTypes()` | `[NONE, LZ4, ZSTD]` |
| `getHighRatioCompressionTypes()` | `[LZMA, XZ, BZIP2]` |

### `ImageFormat`

**14 formats** d'image **sans préfixe MIME** (juste l'extension sans point) — `avif`, `bmp`, `cur`, `gif`, `heic`, `heif`, `ico`, `jpeg`, `jpg`, `png`, `svg`, `tif`, `tiff`, `webp`.

Utilisé comme **clé** dans les mappings format → MIME, notamment par [`getImageMimeType`](files/mime.md#getimagemimetype).

---

## Options (clés de tableaux)

Ces classes définissent les **clés acceptées** par les fonctions qui prennent un tableau d'options. Convention : nom de classe au **singulier** terminé par `Option` (en majuscule).

### `FindFilesOption`

8 clés pour [`findFiles`](files/discovery.md#findfiles) : `FILTER`, `FOLLOW_LINKS`, `INCLUDE_DOTS`, `MODE`, `ORDER`, `PATTERN`, `RECURSIVE`, `SORT`.

### `FindFileOption`

⚠ **Quasi-doublon de `FindFilesOption`** (mêmes 8 clés, même classe constantes). À surveiller — possible dette technique à clarifier.

### `MakeFileOption`

9 clés pour [`makeFile`](files/creation.md#makefile) : `APPEND`, `CONTENT`, `FILE`, `FORCE`, `GROUP`, `LOCK`, `OVERWRITE`, `OWNER`, `PERMISSIONS`.

> ⚠ Ne pas confondre avec [`MakeFileOptions`](options/make-file-options.md#makefileoptions) (pluriel, classe DTO).

### `MakeDirectoryOption`

5 clés pour [`makeDirectory`](files/creation.md#makedirectory) : `GROUP`, `OWNER`, `PATH`, `PERMISSIONS`, `RECURSIVE`.

### `RecursiveFilePathsOption`

4 clés pour [`recursiveFilePaths`](files/discovery.md#recursivefilepaths) : `EXCLUDES`, `EXTENSIONS`, `MAX_DEPTH`, `SORTABLE`.

### `TarOption`

6 clés pour [`tar`](archive/tar.md#tar) / [`tarDirectory`](archive/tar.md#tardirectory) / [`untar`](archive/untar.md#untar) : `DRY_RUN`, `EXCLUDE`, `FILTER`, `KEEP_PERMISSIONS`, `OVERWRITE`, `METADATA`.

---

## Modes / énumérations métier

### `FindMode`

3 modes pour [`findFiles`](files/discovery.md#findfiles) :

| Constante | Valeur | Effet |
|---|---|---|
| `BOTH` | `'both'` | Fichiers + dossiers |
| `FILES` | `'files'` | Fichiers uniquement (défaut) |
| `DIRS` | `'dirs'` | Dossiers uniquement |

---

## Résultats structurés (clés)

Ces classes définissent les **clés des tableaux retournés** par certaines fonctions.

### `OwnershipInfo`

4 clés du tableau retourné par [`getOwnershipInfos`](files/system.md#getownershipinfos) — `GROUP`, `GID`, `OWNER`, `UID`.

> ⚠ Ne pas confondre avec [`OwnershipInfos`](options/make-file-options.md#ownershipinfos) (pluriel, classe DTO).

### `TarInfo`

6 clés du tableau retourné par [`tarFileInfo`](archive/tar.md#tarfileinfo) : `COMPRESSION`, `EXTENSION`, `FILE_COUNT`, `IS_VALID`, `TOTAL_SIZE`, `MIME_TYPE`.

---

## État interne

### `CanonicalizeBuffer`

**Buffer statique LRU** utilisé par [`canonicalizePath`](path/joining-and-normalizing.md#canonicalizepath) pour mémoïser les chemins déjà canonicalisés.

| Constante / propriété | Valeur | Rôle |
|---|---|---|
| `CLEANUP_THRESHOLD` | `1250` | Seuil de déclenchement du nettoyage. |
| `CLEANUP_SIZE` | `1000` | Taille cible après nettoyage (les 1000 plus récents sont conservés). |
| `$buffer` (static array) | `[]` | Map `path → canonical`. |
| `$bufferSize` (static int) | `0` | Compteur. |

> 💡 Tu peux **inspecter ou vider** ce buffer en debug : `CanonicalizeBuffer::$buffer = []`. Utile en tests si tu veux des mesures déterministes.

---

## Conventions de nommage

Récap des deux pièges récurrents :

| `Singulier` (classe de **clés**) | `Pluriel` (classe **DTO** étendant `Options`) |
|---|---|
| `oihana\files\enums\MakeFileOption` | `oihana\files\options\MakeFileOptions` |
| `oihana\files\enums\OwnershipInfo` | `oihana\files\options\OwnershipInfos` |

→ Les premiers sont des **constantes string** (clés d'un tableau associatif). Les seconds sont des **objets typés** avec propriétés publiques.

## Voir aussi

- [Pattern Options](options/README.md) — la classe abstraite `Options` utilisée par les DTO pluriels.
- [Options concrètes](options/make-file-options.md) — `MakeFileOptions` et `OwnershipInfos`.
- [Exceptions](exceptions.md) — `UnsupportedCompressionException` levée par `TarExtension::*` et `CompressionType` au-delà de gzip/bzip2/none.
- [Tips](tips.md) — pièges et conventions.
- [Sommaire FR](README.md).
