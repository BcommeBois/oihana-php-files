# Copie filtrée

Une seule fonction, mais centrale pour les workflows de **backup**, **synchronisation** et **export**.

- [`copyFilteredFiles`](#copyfilteredfiles) — copie récursive avec exclusions par pattern + callback de filtrage.

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

## Voir aussi

- [Découverte](discovery.md#shouldexcludefile) — `shouldExcludeFile` utilisée pour le filtrage.
- [Création](creation.md) — `makeDirectory` créé les dossiers de destination.
- [Suppression](deletion.md) — `deleteDirectory` pour nettoyer une destination existante avant copie.
- [Vue d'ensemble](README.md).
