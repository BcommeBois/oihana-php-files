# Créer une archive tar

Quatre fonctions pour produire et inspecter des archives tar.

- [`tar`](#tar) — création depuis fichiers et/ou dossiers (API principale).
- [`tarDirectory`](#tardirectory) — convenience pour un dossier unique avec filtres et metadata.
- [`tarFileInfo`](#tarfileinfo) — inspection (validité, MIME, compression, count, taille).
- [`tarIsCompressed`](#tariscompressed) — détection rapide par extension.

---

## `tar`

```php
tar(
    string|array $paths ,
    ?string      $outputPath  = null ,
    ?string      $compression = CompressionType::GZIP ,
    ?string      $preserveRoot = null
) : string
```

**API principale** de création d'archive tar. Accepte un ou plusieurs fichiers/dossiers en entrée.

### Paramètres

| Paramètre        | Type                | Effet |
|------------------|---------------------|-------|
| `$paths`         | `string \| string[]` | Chemin(s) absolu(s) à inclure (fichiers OU dossiers, mélange autorisé). |
| `$outputPath`    | `?string`           | Chemin final de l'archive. Si `null`, un nom auto-généré est utilisé dans `sys_get_temp_dir()`. |
| `$compression`   | `?string`           | `CompressionType::GZIP` (défaut), `BZIP2`, `XZ`, `ZSTD` ou `NONE`. `XZ` et `ZSTD` demandent le `tar` du système **et** leur programme — [`tarWritableCompressions()`](tar-engine.md#les-fonctions-du-moteur) dit ce que cette machine sait écrire. |
| `$preserveRoot`  | `?string`           | Si défini (chemin absolu), les entrées sont stockées **relativement à ce dossier** — utile pour préserver la structure. |

### Retour et exceptions

- **Retour** : chemin complet de l'archive créée.
- **`FileException`** : un des `$paths` n'existe pas.
- **`UnsupportedCompressionException`** : compression que **cette machine** ne sait pas écrire. Le message nomme ce qui manque — `xz needs the system GNU tar and its xz program` — ou dit que le format n'est pas écrit du tout (`lzma`, que `xz` remplace, et `lz4`, pour lequel GNU tar n'a pas de drapeau), et dans les deux cas ce que cette machine écrit.
- **`DirectoryException`** : impossible de créer le dossier temporaire.
- **`RuntimeException`** : aucun fichier ajouté, ou erreur de renommage.

### Comportement clé

1. **Empty directories préservés** : `tar` parcourt l'arborescence et appelle `addEmptyDir` pour les dossiers sans contenu — contrairement à un naïf `cp -r`.
2. **Travail en deux temps** : crée d'abord un `.tar` temporaire dans `/tmp`, puis le compresse, puis le renomme vers `$outputPath`. Garantit l'atomicité côté fichier final.
3. **`$preserveRoot`** :
   - Si défini ET correspond à un dossier passé → chemins relatifs à ce dossier (pas de préfixe).
   - Si non défini → chemins préfixés par `basename($path)` (typique pour archiver plusieurs dossiers).

### Exemples

```php
use function oihana\files\archive\tar\tar;
use oihana\files\enums\CompressionType;

// 1. Un fichier, auto-nommé, gzip par défaut
$path = tar( '/var/www/html/index.php' ) ;
// → /tmp/oihana/files/archive/tar/tar/archive_20260526_153012abc.tar.gz

// 2. Un dossier, bzip2, chemin de sortie explicite
$path = tar(
    '/var/www/html' ,
    '/tmp/site.tar.bz2' ,
    CompressionType::BZIP2 ,
) ;

// 3. Plusieurs fichiers, sans compression
$path = tar(
    [ '/etc/hosts' , '/etc/hostname' ] ,
    '/tmp/config.tar' ,
    CompressionType::NONE ,
) ;

// 4. Préservation de la racine — entrées relatives au préserveRoot
$path = tar(
    '/var/www/html/project' ,
    '/tmp/project.tar.gz' ,
    CompressionType::GZIP ,
    '/var/www/html' , // → entrées dans l'archive : project/...
) ;
```

### Pourquoi `$preserveRoot` ?

Sans `$preserveRoot` :

```
project/
├── src/
└── public/
```

Archive produite (avec `tar('/var/www/html/project', ...)`):

```
project/src/...
project/public/...
```

→ Extraction crée un sous-dossier `project/`.

Avec `$preserveRoot = '/var/www/html'`:

```
project/src/...
project/public/...
```

Avec `$preserveRoot = '/var/www/html/project'`:

```
src/...
public/...
```

→ Extraction crée directement `src/` et `public/` sans wrapper.

---

## `tarDirectory`

```php
tarDirectory(
    string  $directory ,
    ?string $compression = CompressionType::GZIP ,
    ?string $outputPath  = null ,
    array   $options     = []
) : string
```

**Convenience** au-dessus de `tar`, spécialisée pour archiver **un dossier unique** avec :

- **filtres d'exclusion** par pattern ;
- **callback de filtrage** custom ;
- **metadata embarquée** dans un fichier `.metadata.json` interne à l'archive.

### Options

| Clé (string ou enum) | Type | Effet |
|---|---|---|
| `'exclude'` / `TarOption::EXCLUDE` | `string[]` | Liste de patterns glob/regex (cf. [`shouldExcludeFile`](../files/discovery.md#shouldexcludefile)). |
| `'filter'` / `TarOption::FILTER` | `?callable(string $filePath): bool` | Callback de filtrage personnalisé. Retourne `true` pour inclure. |
| `'metadata'` / `TarOption::METADATA` | `array<string, string>` | Metadata sérialisée en JSON dans `.metadata.json`. |

### Logique

- **Si aucune option** (pas d'exclude, pas de filter, pas de metadata) → `tar()` direct sur le dossier (rapide).
- **Sinon** :
  1. Copie filtrée du dossier vers un dossier temporaire (via [`copyFilteredFiles`](../files/copying.md)) ;
  2. Écriture éventuelle de `.metadata.json` ;
  3. `tar()` du temporaire vers `$outputPath` ;
  4. Nettoyage du temporaire (en `finally`).

### Si `$outputPath` est null

Le chemin par défaut est : `dirname($directory)/basename($directory).{ext}`.

Exemple : `tarDirectory('/var/www/html')` → `/var/www/html.tar.gz`.

### Exemples

```php
use function oihana\files\archive\tar\tarDirectory;
use oihana\files\enums\CompressionType;
use oihana\files\enums\TarOption;

// 1. Compressé gzip, nom auto
$archive = tarDirectory( '/var/www/html' ) ;
// → /var/www/html.tar.gz

// 2. bz2, exclusions classiques
$archive = tarDirectory(
    '/var/www/html' ,
    CompressionType::BZIP2 ,
    null ,
    [
        TarOption::EXCLUDE => [ '.git' , 'node_modules' , 'vendor' ] ,
    ]
) ;

// 3. Callback custom + metadata
$archive = tarDirectory(
    '/var/www/html' ,
    CompressionType::NONE ,
    '/backups/php-only.tar' ,
    [
        TarOption::FILTER => fn( string $filePath ) =>
            str_ends_with( $filePath , '.php' ) ,
        TarOption::METADATA => [
            'createdBy'    => 'admin' ,
            'description'  => 'Backup of PHP source files' ,
            'creationDate' => date( 'c' ) ,
        ] ,
    ]
) ;
```

> 💡 **Quand préférer `tar` à `tarDirectory` ?** Quand tu veux archiver **plusieurs sources** non-contigües (`tar(['/etc/hosts', '/var/log'])`), ou contrôler manuellement `$preserveRoot`. `tarDirectory` est plus pratique pour le cas mono-dossier.

---

## `tarFileInfo`

```php
tarFileInfo( string $filePath , bool $strictMode = false ) : array
```

Inspecte un fichier tar et retourne ses informations sous forme de tableau associatif :

| Clé           | Type      | Description |
|---------------|-----------|---|
| `isValid`     | `bool`    | Passe la validation [`assertTar`](untar.md#asserttar). |
| `extension`   | `string`  | Extension en minuscules (sans le point). |
| `mimeType`    | `?string` | MIME détecté via `finfo`. |
| `compression` | `?string` | `gz`, `bz2`, ou `none` (déduit du MIME). |
| `fileCount`   | `?int`    | Nombre de fichiers (si valide). |
| `totalSize`   | `?int`    | Somme des tailles en bytes (si valide). |

**Lève `FileException`** si le fichier n'existe pas.

```php
use function oihana\files\archive\tar\tarFileInfo;

$info = tarFileInfo( '/archives/sample.tar' ) ;
print_r( $info ) ;
// [
//     'isValid'     => true,
//     'extension'   => 'tar',
//     'mimeType'    => 'application/x-tar',
//     'compression' => 'none',
//     'fileCount'   => 142,
//     'totalSize'   => 5283920,
// ]

// Fichier invalide → isValid: false, fileCount/totalSize: null
$info = tarFileInfo( '/bad/file.tar' ) ;

// Mode strict : valide aussi la structure interne via validateTarStructure
$info = tarFileInfo( '/archives/sample.tar' , strictMode: true ) ;
```

---

## `tarIsCompressed`

```php
tarIsCompressed( string $tarFile ) : bool
```

Vérifie **rapidement** si une archive tar est compressée — uniquement par **analyse de l'extension**, pas du contenu.

**Reconnaît :** `.tar.gz`, `.tgz`, `.tar.bz2`, `.tbz2` (case-insensitive).

```php
use function oihana\files\archive\tar\tarIsCompressed;

tarIsCompressed( 'archive.tar.gz'  ) ; // true
tarIsCompressed( 'archive.tgz'     ) ; // true
tarIsCompressed( 'archive.tar.bz2' ) ; // true
tarIsCompressed( 'archive.tbz2'    ) ; // true

tarIsCompressed( 'archive.tar'     ) ; // false (non compressé)
tarIsCompressed( 'archive.zip'     ) ; // false
tarIsCompressed( 'README.md'       ) ; // false
```

> ⚠ Ne consulte **pas le contenu** — un fichier nommé `fake.tar.gz` contenant n'importe quoi retourne `true`. Pour une validation réelle, voir [`assertTar`](untar.md#asserttar) ou [`tarFileInfo`](#tarfileinfo).

---

## `tarExcludesPath`

```php
tarExcludesPath( string $path , array $patterns ) : bool
```

Indique si les motifs `--exclude` de **GNU tar** laissent un chemin hors d'une archive. Une archive faite avec `tar --exclude=<motif> …` n'a pas deux sortes de choses que l'arbre d'origine a : ce qui a été ajouté depuis, et ce qu'un motif a laissé dehors exprès. Qui compare les deux — pour remettre un arbre dans l'état de son archive, par exemple — doit les distinguer, sinon il supprime ce qui ne devait jamais être archivé.

Les motifs sont lus comme GNU tar les lit par défaut :

| Règle | Effet | Exemple |
|---|---|---|
| **Non ancré** | Le motif peut commencer à n'importe quel `/` du chemin. | `cache` vaut pour `app/cache` |
| **Dossier de tête** | Un motif qui vise un dossier exclut tout ce qu'il contient. | `cache` vaut pour `app/cache/a.bin` |
| **`*` traverse `/`** | Comme `fnmatch()` sans option. | `content/*.log` vaut pour `content/logs/a.log` |

Les `/` de début et de fin sont ignorés, et un motif vide n'exclut rien. Un essai compare la fonction à GNU tar lui-même, quand il est installé.

```php
use function oihana\files\archive\tar\tarExcludesPath;

tarExcludesPath( 'content/cache/a.bin' , [ 'cache' ] ) ;         // true
tarExcludesPath( 'content/logs/a.log'  , [ 'content/*.log' ] ) ; // true
tarExcludesPath( 'content/cached.txt'  , [ 'cache' ] ) ;         // false (un segment, pas un préfixe)
```

> ℹ️ Ce n'est pas la règle de [`shouldExcludeFile`](../files/discovery.md#shouldexcludefile), qu'utilise `tarDirectory` : celle-ci compare un motif au chemin entier ou au nom du fichier. `tarExcludesPath` dit ce que fait le **binaire** `tar`.

---

## Voir aussi

- [Extraire une archive](untar.md) — `untar`, `assertTar`, `hasTarExtension`, `hasTarMimeType`, `validateTarStructure`.
- [Vue d'ensemble du namespace](README.md).
- [Énumérations](../enums.md) — `CompressionType`, `TarExtension`, `TarOption`, `TarInfo`.
- [Copie filtrée](../files/copying.md) — `copyFilteredFiles` utilisée par `tarDirectory`.
- [Phar](../phar/README.md) — helpers utilisés en interne.
