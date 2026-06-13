# Extraire une archive zip

Cinq fonctions pour extraire et valider des archives zip.

- [`unzip`](#unzip) — extraction (avec `dryRun`, `overwrite`, `maxEntries`, `maxSize`, `keepPermissions`).
- [`assertZip`](#assertzip) — validation combinée (extension + MIME + structure).
- [`hasZipExtension`](#haszipextension) — check rapide par extension.
- [`hasZipMimeType`](#haszipmimetype) — check par MIME type via `finfo`.
- [`validateZipStructure`](#validatezipstructure) — ouverture + inspection des premières entrées.

---

## `unzip`

```php
unzip(
    string $zipFile ,
    string $outputPath ,
    array  $options = []
) : true|array
```

Extrait une archive zip dans un dossier de sortie. Miroir de [`untar`](untar.md#untar), avec des **garde-fous de sécurité** (Zip Slip, bombe de décompression) et une politique d'**exceptions typées**.

### Options

| Clé (string ou enum) | Type | Défaut | Effet |
|---|---|---|---|
| `'dryRun'` / `ZipOption::DRY_RUN` | `bool` | `false` | N'extrait rien — retourne **la liste des entrées fichiers** qui seraient extraites (entrées-répertoire exclues). |
| `'overwrite'` / `ZipOption::OVERWRITE` | `bool` | `true` | Si `false`, lève `FileException` au premier fichier déjà existant. |
| `'maxEntries'` / `ZipOption::MAX_ENTRIES` | `int\|null` | `null` | Si défini, rejette l'archive qui déclare plus d'entrées que cette limite (anti-bombe). |
| `'maxSize'` / `ZipOption::MAX_SIZE` | `int\|null` | `null` | Si défini, plafonne la taille décompressée totale (en octets). Lève `FileException` **avant** toute écriture si dépassement. |
| `'keepPermissions'` / `ZipOption::KEEP_PERMISSIONS` | `bool` | `false` | Restaure les permissions Unix stockées dans chaque entrée (attributs externes `OPSYS_UNIX`) via `chmod`. |

### Retour

- `true` si extraction réussie ;
- `string[]` (entrées fichiers) en mode `dryRun`.

### Exceptions

> **Note de design.** Contrairement à `untar()` qui enveloppe toute erreur dans `RuntimeException`, `unzip()` lève des **exceptions typées** : `ZipArchive` rapporte ses erreurs par valeur de retour (et non en levant), il n'y a donc rien à « rattraper ». Les exceptions typées sont plus exploitables et collent au code d'origine `extractZip`.

- **`FileException`** :
  - `$zipFile` absent ou illisible (via `assertZip`) ;
  - archive impossible à ouvrir (corrompue) ;
  - **trop d'entrées** (`maxEntries` dépassé) ;
  - **taille décompressée totale supérieure à `maxSize`** (bombe de décompression) ;
  - **Zip Slip** : une entrée s'échappe du dossier de destination ;
  - tentative d'écrasement avec `overwrite: false`.
- **`DirectoryException`** : impossible de créer `$outputPath` (ou le dossier parent d'une entrée).

### Pipeline interne

1. `assertZip( $zipFile )` — garantit l'existence du fichier (lève `FileException` si absent).
2. `makeDirectory( $outputPath )` — création du dossier de sortie.
3. `ZipArchive::open()` — ouverture réelle (échec → `FileException`).
4. Pré-scan **`maxEntries`** puis **`maxSize`** (somme des tailles décompressées) — **avant** toute écriture.
5. Pour chaque entrée : vérification **Zip Slip**, puis (hors `dryRun`) création du dossier ou écriture du fichier, et restauration éventuelle des permissions.
6. Retourne la liste (mode `dryRun`) ou `true`.

### Protection Zip Slip

Une archive forgée peut contenir une entrée dont le nom remonte hors du dossier cible (`../../etc/passwd`). `unzip` canonicalise la cible de chaque entrée et la compare à la racine de destination via [`isBasePath`](../path/README.md) :

```php
// Archive forgée avec une entrée "../evil.txt"
unzip( '/uploads/malicious.zip' , '/var/www/uploads' ) ;
// → FileException: Zip Slip detected: the entry "../evil.txt" escapes the destination directory.
```

⚠ Cette vérification est faite **pour chaque entrée**, en mode normal **comme** en `dryRun` — la protection est donc systématique.

### Protection contre les bombes de décompression

Une **bombe de décompression** est une archive de quelques kilooctets qui se décompresse en plusieurs gigaoctets. Deux garde-fous complémentaires, tous deux vérifiés **avant** toute écriture :

```php
use function oihana\files\archive\zip\unzip;
use oihana\files\enums\ZipOption;

unzip( $uploadedArchive , $extractDir , [
    ZipOption::MAX_ENTRIES => 10_000 ,             // refuse > 10 000 entrées
    ZipOption::MAX_SIZE    => 100 * 1024 * 1024 ,  // refuse > 100 Mio décompressés
]) ;
// → FileException si l'une des deux limites est franchie ; aucun fichier écrit.
```

- `maxEntries` compare `numFiles` à la limite (coût négligeable).
- `maxSize` somme les tailles décompressées de toutes les entrées (`statIndex`) puis compare au seuil.
- Les deux sont **opt-in** (`null` par défaut) — comportement sans limite par défaut.

> 💡 **Recommandation sécurité** : pour toute archive d'origine externe (upload, téléchargement), combiner `maxEntries` + `maxSize` + `overwrite: false`, et idéalement un `dryRun` préalable pour inspecter le contenu.

### Restauration des permissions (`keepPermissions`)

Quand `keepPermissions: true`, `unzip` lit le mode Unix stocké dans les attributs externes de chaque entrée (`OPSYS_UNIX`) et l'applique via `chmod`, **fichiers et dossiers** :

```php
unzip( '/releases/app.zip' , '/opt/app' , [
    ZipOption::KEEP_PERMISSIONS => true ,
]) ;
// → un script empaqueté avec le mode 0750 est restauré exécutable.
```

- Les entrées **sans** permissions Unix (zip créé sous Windows, ou attributs absents) conservent le mode par défaut.
- Best-effort : un `chmod` qui échoue (ex. la cible ne nous appartient pas) est ignoré silencieusement.

### Exemples

```php
use function oihana\files\archive\zip\unzip;
use oihana\files\enums\ZipOption;

// 1. Extraction basique
unzip( '/path/to/archive.zip' , '/output/dir' ) ;

// 2. Avec options
unzip( '/path/to/archive.zip' , '/output/dir' , [
    ZipOption::OVERWRITE       => false ,
    ZipOption::KEEP_PERMISSIONS => true ,
]) ;

// 3. Dry-run : preview du contenu sans extraire
$files = unzip( '/path/to/archive.zip' , '/output/dir' , [
    ZipOption::DRY_RUN => true ,
]) ;
print_r( $files ) ;
// ['file1.txt', 'subdir/file2.php', ...]   (entrées-répertoire exclues)

// 4. Workflow sécurisé pour upload utilisateur
$preview = unzip( $uploadedArchive , $extractDir , [
    ZipOption::DRY_RUN     => true ,
    ZipOption::MAX_ENTRIES => 10_000 ,
    ZipOption::MAX_SIZE    => 100 * 1024 * 1024 ,
]) ;

unzip( $uploadedArchive , $extractDir , [
    ZipOption::OVERWRITE => false ,             // refuse d'écraser un fichier existant
    ZipOption::MAX_SIZE  => 100 * 1024 * 1024 , // re-vérifie au pré-scan
]) ;
```

---

## `assertZip`

```php
assertZip( string $filePath , bool $strictMode = false ) : bool
```

**⚠ Attention au nom trompeur** : comme [`assertTar`](untar.md#asserttar), cette fonction **retourne un `bool`** et ne lève pas systématiquement.

**Lève `FileException`** uniquement si le fichier n'existe pas (via `assertFile`).

### Logique de validation

1. **`hasZipExtension`** — extension `.zip` ? Sinon → `false`.
2. **`hasZipMimeType`** — MIME zip ? Sinon → `false`.
3. **Mode strict** (`$strictMode: true`) — `validateZipStructure` (ouverture `ZipArchive` + inspection).

```php
use function oihana\files\archive\zip\assertZip;

assertZip( '/archives/sample.zip' ) ;                    // → true (extension + MIME)
assertZip( '/archives/sample.zip' , strictMode: true ) ; // → true si structurellement valide
assertZip( '/path/missing.zip' ) ;                       // → FileException
```

> ℹ️ `unzip()` appelle `assertZip()` surtout pour garantir l'existence du fichier ; la validité réelle de l'archive est tranchée par `ZipArchive::open()`.

---

## `hasZipExtension`

```php
hasZipExtension(
    string $filePath ,
    array  $zipExtensions = [ FileExtension::ZIP ] // ['.zip']
) : bool
```

Check **rapide et purement textuel** : compare l'extension (en minuscules) à la liste fournie.

```php
use function oihana\files\archive\zip\hasZipExtension;

hasZipExtension( '/path/archive.zip' ) ; // true
hasZipExtension( '/path/ARCHIVE.ZIP' ) ; // true (case-insensitive)
hasZipExtension( '/path/archive.tar' ) ; // false
hasZipExtension( '/path/README.md'   ) ; // false

// Liste custom
hasZipExtension( '/path/pack.zipx' , [ '.zip' , '.zipx' ] ) ; // true
```

---

## `hasZipMimeType`

```php
hasZipMimeType(
    string $filePath ,
    array  $mimeTypes = [
        'application/zip' ,
        'application/x-zip' ,
        'application/x-zip-compressed' ,
        'application/zip-compressed' ,
        'multipart/x-zip' ,
    ]
) : bool
```

Check **par MIME type** via `finfo` (analyse des premiers octets). Délègue à [`hasMimeType`](../files/mime.md#hasmimetype) : **match si le MIME détecté contient** une des chaînes de la liste (`str_contains`).

```php
use function oihana\files\archive\zip\hasZipMimeType;

hasZipMimeType( '/archives/file.zip' ) ;    // → true (MIME: application/zip)
hasZipMimeType( '/archives/missing.zip' ) ; // → false (fichier inexistant)

// Liste custom
hasZipMimeType( '/path/file.zip' , [ 'application/zip' ] ) ;
```

> 💡 Plus fiable que `hasZipExtension` pour les fichiers renommés malicieusement, mais plus lent (lit le fichier). Un zip corrompu est souvent détecté `application/octet-stream` → retourne `false`.

---

## `validateZipStructure`

```php
validateZipStructure( string $filePath ) : bool
```

Vérifie la **structure interne** d'un zip en tentant de l'ouvrir via `ZipArchive` et d'inspecter **les 10 premières entrées max** (limite de perf).

```php
use function oihana\files\archive\zip\validateZipStructure;

validateZipStructure( '/path/to/archive.zip'  ) ; // true ou false
validateZipStructure( '/path/to/invalid.zip'  ) ; // false (ouverture impossible)
validateZipStructure( '/path/to/not_a_zip.txt') ; // false
validateZipStructure( '/nonexistent/file.zip' ) ; // false (fichier absent)
```

### Choix de fonction de validation : matrice

| Niveau | Vitesse | Fonction | Vérifie |
|---|---|---|---|
| 1 (rapide) | µs | `hasZipExtension` | Extension `.zip`. |
| 2 (modéré) | ms | `hasZipMimeType` | MIME via `finfo` (lecture du début du fichier). |
| 3 (lent) | ms+ | `validateZipStructure` | Ouverture `ZipArchive` + inspection des entrées. |
| 4 (combiné) | ms+ | `assertZip` (strict) | 1 + 2 + 3. |

---

## Voir aussi

- [Créer une archive](zip.md) — `zip`, `zipDirectory`, `zipFileInfo`.
- [Vue d'ensemble du namespace archive](README.md).
- [Énumérations](../enums.md) — `ZipOption`, `ZipInfo`, `FileExtension`, `CompressionType`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Extraire un tar](untar.md) — l'équivalent pour le format tar.
