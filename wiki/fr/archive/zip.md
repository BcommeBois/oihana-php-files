# Créer une archive zip

Trois fonctions pour produire et inspecter des archives **zip**.

- [`zip`](#zip) — création depuis fichiers et/ou dossiers (API principale).
- [`zipDirectory`](#zipdirectory) — convenience pour un dossier unique avec filtres et metadata.
- [`zipFileInfo`](#zipfileinfo) — inspection (validité, MIME, compression, count, taille).

> 💡 Implémentation basée sur **`ZipArchive`** natif (extension `ext-zip`). Contrairement à `tar` (qui passe par `PharData`), la compression est appliquée **par entrée** et l'archive est écrite sur disque au `close()`.

> ℹ️ Pas d'équivalent à `tarIsCompressed` : un zip est un conteneur dont la compression se décide entrée par entrée — la notion « archive compressée ou non » n'a pas de sens global.

---

## `zip`

```php
zip(
    string|array $paths ,
    ?string      $outputPath   = null ,
    ?string      $compression  = CompressionType::ZIP ,
    ?string      $preserveRoot = null
) : string
```

**API principale** de création d'archive zip. Accepte un ou plusieurs fichiers/dossiers en entrée. Miroir de [`tar`](tar.md#tar).

### Paramètres

| Paramètre        | Type                 | Effet |
|------------------|----------------------|-------|
| `$paths`         | `string \| string[]` | Chemin(s) absolu(s) à inclure (fichiers OU dossiers, mélange autorisé). |
| `$outputPath`    | `?string`            | Chemin final de l'archive. Si `null`, un nom auto-généré est utilisé dans `sys_get_temp_dir()`. |
| `$compression`   | `?string`            | **Méthode par entrée** : `CompressionType::ZIP` (DEFLATE, défaut) ou `CompressionType::NONE` (stored, sans compression). |
| `$preserveRoot`  | `?string`            | Si défini (chemin absolu), les entrées sont stockées **relativement à ce dossier** — utile pour préserver la structure. |

### Retour et exceptions

- **Retour** : chemin complet de l'archive créée.
- **`FileException`** : un des `$paths` n'existe pas, **ou** l'archive ne peut pas être créée/écrite (dossier de sortie inaccessible).
- **`UnsupportedCompressionException`** : compression autre que `ZIP` ou `NONE` (ex. `GZIP`, `BZIP2` n'ont pas de sens pour un zip).
- **`DirectoryException`** : impossible de créer le dossier temporaire (cas `$outputPath = null`).
- **`RuntimeException`** : aucun chemin fourni (`[]`), ou aucun fichier finalement ajouté.

### Comportement clé

1. **Empty directories préservés** : `zip` parcourt l'arborescence et appelle `addEmptyDir` pour les dossiers sans contenu.
2. **Écriture au `close()`** : `ZipArchive` n'écrit rien tant que `close()` n'est pas appelé. `zip()` vérifie ensuite que le fichier existe bien — sinon il lève `FileException` (ex. dossier parent manquant : `open()` réussit mais `close()` échoue silencieusement).
3. **`$preserveRoot`** :
   - Si défini ET correspond au dossier passé → chemins relatifs à ce dossier (pas de préfixe).
   - Si non défini → chemins préfixés par `basename($path)` (typique pour archiver plusieurs dossiers).
4. **Compression `NONE`** : chaque entrée est marquée `ZipArchive::CM_STORE` (utile pour des fichiers déjà compressés — jpeg, mp4 — où le DEFLATE n'apporte rien).

### Exemples

```php
use function oihana\files\archive\zip\zip;
use oihana\files\enums\CompressionType;

// 1. Un fichier, auto-nommé, DEFLATE par défaut
$path = zip( '/var/www/html/index.php' ) ;
// → /tmp/oihana/files/archive/zip/zip/archive_20260613_081500abc.zip

// 2. Un dossier, chemin de sortie explicite
$path = zip( '/var/www/html' , '/tmp/site.zip' ) ;

// 3. Plusieurs fichiers, stockés sans compression
$path = zip(
    [ '/etc/hosts' , '/etc/hostname' ] ,
    '/tmp/config.zip' ,
    CompressionType::NONE ,
) ;

// 4. Préservation de la racine — entrées relatives au preserveRoot
$path = zip(
    '/var/www/html/project' ,
    '/tmp/project.zip' ,
    CompressionType::ZIP ,
    '/var/www/html/project' , // → entrées : src/... public/... (sans wrapper project/)
) ;
```

### Pourquoi `$preserveRoot` ?

Soit l'arborescence :

```
project/
├── src/
└── public/
```

Sans `$preserveRoot` — `zip('/var/www/html/project', ...)` produit :

```
project/src/...
project/public/...
```

→ l'extraction recrée un sous-dossier `project/`.

Avec `$preserveRoot = '/var/www/html/project'` :

```
src/...
public/...
```

→ l'extraction crée directement `src/` et `public/` sans wrapper.

---

## `zipDirectory`

```php
zipDirectory(
    string  $directory ,
    ?string $compression = CompressionType::ZIP ,
    ?string $outputPath  = null ,
    array   $options     = []
) : string
```

**Convenience** au-dessus de `zip`, spécialisée pour archiver **un dossier unique** avec filtres et metadata. Miroir de [`tarDirectory`](tar.md#tardirectory).

### Options

| Clé (string ou enum) | Type | Effet |
|---|---|---|
| `'exclude'` / `ZipOption::EXCLUDE` | `string[]` | Liste de patterns glob/noms à exclure (cf. [`shouldExcludeFile`](../files/discovery.md#shouldexcludefile)). |
| `'filter'` / `ZipOption::FILTER` | `?callable(string $filePath): bool` | Callback de filtrage personnalisé. Retourne `true` pour inclure. |
| `'metadata'` / `ZipOption::METADATA` | `array<string, mixed>` | Metadata sérialisée en JSON dans `.metadata.json`. |

### Logique

- **Si aucune option** (pas d'exclude, pas de filter, pas de metadata) → `zip()` direct sur le dossier (avec `preserveRoot = $directory`).
- **Sinon** :
  1. Copie filtrée du dossier vers un dossier temporaire (via [`copyFilteredFilesWithMetadata`](../files/copying.md)) ;
  2. Écriture éventuelle de `.metadata.json` ;
  3. `zip()` du temporaire vers `$outputPath` ;
  4. Nettoyage du temporaire (en `finally`).

### Si `$outputPath` est null

Le chemin par défaut est : `dirname($directory)/basename($directory).zip`.

Exemple : `zipDirectory('/var/www/html')` → `/var/www/html.zip`.

### Exemples

```php
use function oihana\files\archive\zip\zipDirectory;
use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipOption;

// 1. Nom auto, DEFLATE
$archive = zipDirectory( '/var/www/html' ) ;
// → /var/www/html.zip

// 2. Stocké sans compression, exclusions classiques
$archive = zipDirectory(
    '/var/www/html' ,
    CompressionType::NONE ,
    null ,
    [
        ZipOption::EXCLUDE => [ '.git' , 'node_modules' , 'vendor' ] ,
    ]
) ;

// 3. Callback custom + metadata
$archive = zipDirectory(
    '/var/www/html' ,
    CompressionType::ZIP ,
    '/backups/php-only.zip' ,
    [
        ZipOption::FILTER => fn( string $filePath ) =>
            str_ends_with( $filePath , '.php' ) ,
        ZipOption::METADATA => [
            'createdBy'    => 'admin' ,
            'description'  => 'Backup of PHP source files' ,
            'creationDate' => date( 'c' ) ,
        ] ,
    ]
) ;
```

> 💡 **Quand préférer `zip` à `zipDirectory` ?** Quand tu veux archiver **plusieurs sources** non-contigües (`zip(['/etc/hosts', '/var/log'])`), ou contrôler manuellement `$preserveRoot`. `zipDirectory` est plus pratique pour le cas mono-dossier.

---

## `zipFileInfo`

```php
zipFileInfo( string $filePath , bool $strictMode = false ) : array
```

Inspecte un fichier zip et retourne ses informations sous forme de tableau associatif (clés [`ZipInfo`](../enums.md#zipinfo)) :

| Clé           | Type      | Description |
|---------------|-----------|---|
| `isValid`     | `bool`    | Passe la validation [`assertZip`](unzip.md#assertzip). |
| `extension`   | `string`  | Extension en minuscules (sans le point). |
| `mimeType`    | `?string` | MIME détecté via `finfo`. |
| `compression` | `?string` | `zip` si le MIME est zip-like, sinon `none`. |
| `fileCount`   | `?int`    | Nombre d'entrées (si valide). |
| `totalSize`   | `?int`    | Somme des tailles **décompressées** en bytes (si valide). |

**Lève `FileException`** si le fichier n'existe pas.

```php
use function oihana\files\archive\zip\zipFileInfo;

$info = zipFileInfo( '/archives/sample.zip' ) ;
print_r( $info ) ;
// [
//     'isValid'     => true,
//     'extension'   => 'zip',
//     'mimeType'    => 'application/zip',
//     'compression' => 'zip',
//     'fileCount'   => 42,
//     'totalSize'   => 1048576,
// ]

// Fichier invalide → isValid: false, fileCount/totalSize: null
$info = zipFileInfo( '/bad/file.zip' ) ;

// Mode strict : valide aussi la structure interne via validateZipStructure
$info = zipFileInfo( '/archives/sample.zip' , strictMode: true ) ;
```

---

## Voir aussi

- [Extraire une archive](unzip.md) — `unzip`, `assertZip`, `hasZipExtension`, `hasZipMimeType`, `validateZipStructure`.
- [Vue d'ensemble du namespace archive](README.md).
- [Énumérations](../enums.md) — `CompressionType`, `ZipOption`, `ZipInfo`.
- [Copie filtrée](../files/copying.md) — `copyFilteredFilesWithMetadata` utilisée par `zipDirectory`.
- [Créer un tar](tar.md) — l'équivalent pour le format tar.
