# Copie et déplacement

Fonctions de copie et de déplacement de fichiers, du **fichier unique** au **dossier entier** (workflows de **backup**, **synchronisation**, **export**, **archivage**).

**Fichier unique :**
- [`copyFile`](#copyfile) — copie un fichier (overwrite, destination-dossier, création du parent, exceptions typées).
- [`moveFile`](#movefile) — déplace/renomme un fichier (`rename` atomique + repli copie/suppression inter-systèmes).
- [`renameFile`](#renamefile) — alias sémantique de `moveFile`.

**Dossier (copie filtrée) :**
- [`copyFilteredFiles`](#copyfilteredfiles) — copie récursive avec exclusions par pattern + callback de filtrage.
- [`copyFilteredFilesWithMetadata`](#copyfilteredfileswithmetadata) — `copyFilteredFiles` + écriture optionnelle de `.metadata.json` + garde « rien ne matche ».

---

## `copyFile`

```php
copyFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

Copie **un seul** fichier, avec gestion d'erreurs typée (là où [`copyFilteredFiles`](#copyfilteredfiles) reproduit toute une arborescence) :

- la source est validée par [`assertFile`](assertions.md#assertfile) (doit exister et être lisible) ;
- si `$destination` est un **dossier existant**, le fichier est copié **dedans** en conservant le nom de la source (convention `cp source dir/`) ;
- copier un fichier **sur lui-même** est refusé (cela tronquerait la source) ;
- si `$overwrite` vaut `false`, une destination existante lève une `FileException` ;
- le dossier parent de la destination est créé à la demande via [`makeDirectory`](creation.md#makedirectory) quand `$createDirectory` vaut `true`.

**Retourne `true`** en cas de succès.

| Cas | Exception |
|---|---|
| Source absente / illisible | `FileException` (via `assertFile`) |
| Source et destination identiques | `FileException` |
| Destination existante avec `$overwrite = false` | `FileException` |
| Échec de la copie | `FileException` |
| Dossier parent absent avec `$createDirectory = false` | `DirectoryException` |

```php
use function oihana\files\copyFile;

copyFile( '/data/report.pdf' , '/backup/report.pdf' ) ;          // cible explicite
copyFile( '/data/report.pdf' , '/backup' ) ;                     // dans un dossier
copyFile( '/data/report.pdf' , '/backup/report.pdf' , false ) ;  // lève si déjà présent
```

---

## `moveFile`

```php
moveFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

Déplace (ou renomme) **un seul** fichier. Partage la sémantique de destination de [`copyFile`](#copyfile) (destination-dossier, `overwrite`, création du parent).

Le déplacement utilise `rename()` (**atomique sur le même système de fichiers**). Quand source et destination sont sur des **systèmes de fichiers différents**, `rename()` ne peut pas franchir le périphérique : la fonction bascule alors de façon transparente sur [`copyFile`](#copyfile) + [`deleteFile`](deletion.md#deletefile).

**Retourne `true`** en cas de succès. Mêmes exceptions que `copyFile` (hors « même fichier » : renommer un fichier sur lui-même est un no-op réussi).

```php
use function oihana\files\moveFile;

moveFile( '/tmp/upload.tmp' , '/data/final.pdf' ) ; // déplacement + renommage
moveFile( '/data/final.pdf' , '/archive' ) ;        // dans un dossier
```

---

## `renameFile`

```php
renameFile(
    string $source ,
    string $destination ,
    bool   $overwrite       = true ,
    bool   $createDirectory = true
) : bool
```

**Alias sémantique** de [`moveFile`](#movefile) : renommer un fichier revient à le déplacer. Voir `moveFile` pour le détail des comportements.

```php
use function oihana\files\renameFile;

renameFile( '/data/old-name.txt' , '/data/new-name.txt' ) ;
```

---

## `copyFilteredFiles`

```php
copyFilteredFiles(
    string    $sourceDir ,
    string    $destDir ,
    array     $excludePatterns = [] ,
    ?callable $filterCallback  = null
) : bool
```

Copie récursivement un dossier vers un autre, en **préservant la structure**. Deux mécanismes de filtrage indépendants et combinables :

1. **`$excludePatterns`** — liste de **patterns glob/regex** ; tout fichier ou dossier matchant est **ignoré** (via [`shouldExcludeFile`](discovery.md#shouldexcludefile)).
2. **`$filterCallback`** — callback `fn(string $filePath): bool` ; retourne `true` pour **inclure**.

Les dossiers de destination sont **créés à la volée** via [`makeDirectory`](creation.md#makedirectory).

**Retourne `bool`** : `true` si au moins un fichier ou dossier a été copié, `false` sinon.

**Lève `DirectoryException`** si la création d'un dossier de destination échoue.

### Logique de filtrage (combinée)

Pour qu'un fichier soit copié, il doit :

1. **Ne pas** matcher un pattern d'exclusion.
2. **ET** retourner `true` au callback (si fourni).

Les deux filtres sont **AND-combinés** — un fichier exclu par l'un est rejeté.

### Exemple complet

Structure de départ :

```
/tmp/source/
├── .git/
│   └── config
├── images/
│   └── logo.png   (5 Ko)
├── index.php      (1 Ko)
└── error.log
```

Appel :

```php
use function oihana\files\copyFilteredFiles;

$source = '/tmp/source' ;
$dest   = '/tmp/destination' ;

// 1. Exclure les dossiers .git et tous les *.log
$exclude = [ '.git' , '*.log' ] ;

// 2. Filtrer aussi par taille : pas plus de 2 Ko
$filter = fn( string $filePath ) =>
    is_dir( $filePath ) || filesize( $filePath ) < 2048 ;

copyFilteredFiles( $source , $dest , $exclude , $filter ) ;
```

Résultat (`/tmp/destination/`) :

```
/tmp/destination/
├── images/        ← dossier copié (passe le filtre is_dir)
└── index.php      ← copié (1 Ko < 2 Ko)
```

Explication :
- `.git/` exclu par `.git` ;
- `error.log` exclu par `*.log` ;
- `images/` créé par `makeDirectory` ;
- `images/logo.png` rejeté par le filtre callback (5 Ko ≥ 2 Ko) ;
- `index.php` copié.

### Patterns d'exclusion classiques

```php
// VCS et dépendances
$dependencies = [ '.git' , '.svn' , 'node_modules' , 'vendor' ] ;

// Caches et builds
$builds = [ '.cache' , 'tmp' , 'build' , 'dist' , '*.log' , '*.bak' ] ;

// Fichiers d'environnement
$envs = [ '.env' , '.env.local' , '*.local' ] ;

copyFilteredFiles( $source , $dest , [ ...$dependencies , ...$builds , ...$envs ] ) ;
```

### Cas d'usage : backup d'un site

```php
use function oihana\files\{ copyFilteredFiles , makeTimestampedDirectory } ;

$snapshot = makeTimestampedDirectory(
    basePath: '/backups' ,
    prefix  : 'site-' ,
) ;
// → /backups/site-2026-05-26T15:30:12

copyFilteredFiles(
    '/var/www/site' ,
    $snapshot ,
    [ '.git' , 'node_modules' , 'vendor' , '*.log' , 'cache/*' ] ,
    fn( string $path ) =>
        // Pas plus de 50 Mo par fichier
        is_dir( $path ) || filesize( $path ) < 50 * 1024 * 1024
) ;
```

### Cas d'usage : export pour publication

```php
// Copier les sources sans rien d'inutile pour un consommateur final
copyFilteredFiles(
    '/dev/myproject' ,
    '/dist/myproject' ,
    [
        '.git' , '.gitignore' , '.github' ,
        'node_modules' , 'vendor' ,
        'tests' , 'docs' ,
        '*.md.bak' , '*.tmp' ,
        'phpunit.xml' , 'phpdoc.xml' ,
    ]
) ;
```

### Pièges et limites

- **Symlinks** : la fonction utilise `RecursiveDirectoryIterator::SKIP_DOTS`, mais **ne suit pas les symlinks** par défaut sauf si tu modifies les flags. `copy()` ne traverse pas non plus — les symlinks sont copiés comme symlinks (ou comme cible, selon la plateforme).
- **Permissions** : `copy()` natif PHP **ne préserve pas** owner/group (juste le contenu et les perms basiques). Pour un backup fidèle, considérer `rsync` ou `cp -p`.
- **Fichiers ouverts en écriture** : peuvent être copiés dans un état intermédiaire — pas de verrou en lecture.
- **Atomicité** : la copie n'est pas transactionnelle. Si elle échoue à mi-parcours (disque plein, permission), `$destDir` contient un état partiel.

> 💡 **Pour les très gros volumes**, `rsync` reste plus rapide et plus robuste. `copyFilteredFiles` est idéal pour les snapshots ponctuels < ~1 Go.

---

## `copyFilteredFilesWithMetadata`

```php
copyFilteredFilesWithMetadata(
    string        $sourceDir ,
    string        $destDir ,
    array         $excludePatterns = [] ,
    ?callable     $filterCallback  = null ,
    array         $metadata        = []
) : void
```

Étape de **staging** mutualisée par les fonctions d'archivage de dossier ([`tarDirectory`](../archive/tar.md#tardirectory) et [`zipDirectory`](../archive/zip.md#zipdirectory)). Elle :

1. délègue la copie filtrée à [`copyFilteredFiles`](#copyfilteredfiles) ;
2. écrit, si `$metadata` est non vide, un fichier `.metadata.json` (JSON joli, slashes non échappés) à la racine de `$destDir` ;
3. **garantit que la destination est non vide** — sinon lève `RuntimeException`.

> ℹ️ Embarquer des métadonnées rend la destination non vide même si **aucun** fichier source n'a matché les filtres (l'archive ne contiendra alors que `.metadata.json`).

### Retour et exceptions

- **Retour** : `void`.
- **`RuntimeException`** : aucun fichier n'a matché les filtres **et** aucune métadonnée fournie (message `"No files match the filtering criteria."`).

### Exemple

```php
use function oihana\files\copyFilteredFilesWithMetadata;

copyFilteredFilesWithMetadata(
    '/var/www/html' ,
    '/tmp/staging' ,
    [ '.git' , 'node_modules' ] ,
    fn( string $path ): bool => str_ends_with( $path , '.php' ) ,
    [ 'createdBy' => 'admin' , 'date' => date( 'c' ) ] ,
) ;
// /tmp/staging contient les .php filtrés + un .metadata.json
```

---

## Voir aussi

- [Découverte](discovery.md#shouldexcludefile) — `shouldExcludeFile` utilisée pour le filtrage.
- [Création](creation.md) — `makeDirectory` créé les dossiers de destination.
- [Suppression](deletion.md) — `deleteDirectory` pour nettoyer une destination existante avant copie.
- [Vue d'ensemble](README.md).
