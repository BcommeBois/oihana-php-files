# Découverte

Six fonctions pour lister, filtrer et explorer le contenu d'un dossier.

- [`findFiles`](#findfiles) — l'API principale (filtres, sort, mode, recursive).
- [`recursiveFilePaths`](#recursivefilepaths) — variante allégée, retourne des chaînes, filtres par extension.
- [`shouldExcludeFile`](#shouldexcludefile) — utilitaire d'exclusion (glob ou regex).
- [`sortFiles`](#sortfiles) — tri par critère ou callback.
- [`hasFiles`](#hasfiles) — un dossier contient-il au moins un fichier ?
- [`hasDirectories`](#hasdirectories) — un dossier contient-il au moins un sous-dossier ?

---

## `findFiles`

```php
findFiles( ?string $directory , array $options = [] ) : array
```

**L'API la plus riche** pour explorer un dossier. Retourne un tableau de `SplFileInfo[]` (ou de valeurs mappées si `filter` est défini).

Lève `DirectoryException` via [`assertDirectory`](assertions.md#assertdirectory) si le chemin est invalide.

### Options (toutes facultatives)

| Clé (string ou enum)           | Type                              | Défaut          | Effet |
|--------------------------------|-----------------------------------|-----------------|-------|
| `'mode'` / `FindFilesOption::MODE` | `'files'` / `'dirs'` / `'both'` | `'files'`       | Type d'entrées retournées. |
| `'recursive'` / `::RECURSIVE`      | `bool`                          | `false`         | Parcours récursif. |
| `'followLinks'` / `::FOLLOW_LINKS` | `bool`                          | `false`         | Suivre les *symlinks* (mode recursive uniquement). |
| `'includeDots'` / `::INCLUDE_DOTS` | `bool`                          | `false`         | Inclure les fichiers/dossiers commençant par `.`. |
| `'pattern'` / `::PATTERN`          | `string \| string[] \| null`    | `null`          | Glob (`*.php`) ou regex (`/^foo$/i`), ou liste mixte. Détection automatique via `isRegexp`. |
| `'sort'` / `::SORT`                | `callable \| string \| string[]`| `false`         | Tri (voir `sortFiles`). |
| `'order'` / `::ORDER`              | `'asc'` / `'desc'`              | `'asc'`         | Direction de tri. |
| `'filter'` / `::FILTER`            | `?callable(SplFileInfo): mixed` | `null`          | Mapping final via `array_map` (transforme chaque entrée). |

### Modes de parcours

- `'files'` (défaut) — fichiers uniquement.
- `'dirs'` — dossiers uniquement.
- `'both'` — les deux.

### Patterns : glob OU regex (détection auto)

Si le pattern ressemble à une regex (entouré de délimiteurs comme `/`, `#`, `~`, etc.), il est traité comme tel (`preg_match`). Sinon, c'est un glob (`fnmatch`).

```php
// Glob
findFiles( '/var/www' , [ 'pattern' => '*.php' ] ) ;

// Regex
findFiles( '/var/www' , [ 'pattern' => '/^config\..+$/' ] ) ;

// Liste mixte (OU logique : au moins un pattern matche)
findFiles( '/var/www' , [ 'pattern' => [ '*.php' , '/^config\..+$/' ] ] ) ;
```

> ⚠ **Sécurité — ReDoS.** Les patterns doivent venir d'une **source de confiance** (config, code interne). Une regex malveillante de type `/^(a+)+$/` peut consommer du CPU pendant plusieurs secondes via *catastrophic backtracking* — PHP n'a pas de timeout sur `preg_match`. Pour les patterns user-supplied, voir [security.md § ReDoS](../security.md#redos-sur-patterns-regex-utilisateur).

### Exemples par cas d'usage

```php
use function oihana\files\findFiles;
use oihana\files\enums\FindFilesOption;
use oihana\files\enums\FindMode;
use oihana\enums\Order;
use SplFileInfo;

// 1. Basique
$files = findFiles( '/var/www' ) ;

// 2. Récursif
$files = findFiles( '/var/www' , [ FindFilesOption::RECURSIVE => true ] ) ;

// 3. Avec dotfiles
$files = findFiles( '/var/www' , [ FindFilesOption::INCLUDE_DOTS => true ] ) ;

// 4. Suivre les symlinks (recursive uniquement)
$files = findFiles( '/var/www' , [
    FindFilesOption::RECURSIVE    => true ,
    FindFilesOption::FOLLOW_LINKS => true ,
]) ;

// 5. Pattern
$php = findFiles( '/var/www' , [ FindFilesOption::PATTERN => '*.php' ] ) ;

// 6. Dossiers uniquement
$dirs = findFiles( '/var/www' , [ FindFilesOption::MODE => FindMode::DIRS ] ) ;

// 7. Trier par nom décroissant
$files = findFiles( '/var/www' , [
    FindFilesOption::SORT  => 'name' ,
    FindFilesOption::ORDER => Order::desc ,
]) ;

// 8. Tri multi-critères : type puis nom (dossiers d'abord)
$all = findFiles( '/var/www' , [
    FindFilesOption::MODE => FindMode::BOTH ,
    FindFilesOption::SORT => [ 'type' , 'name' ] ,
]) ;

// 9. Mapper en chaînes
$names = findFiles( '/var/www' , [
    FindFilesOption::FILTER => fn( SplFileInfo $f ) => $f->getBasename() ,
]) ;

// 10. Récupérer seulement les tailles
$sizes = findFiles( '/var/www' , [
    FindFilesOption::FILTER => fn( SplFileInfo $f ) => $f->getSize() ,
]) ;

// 11. Tout combiner
$logs = findFiles( '/var/log' , [
    FindFilesOption::RECURSIVE    => true ,
    FindFilesOption::FOLLOW_LINKS => true ,
    FindFilesOption::INCLUDE_DOTS => true ,
    FindFilesOption::MODE         => FindMode::FILES ,
    FindFilesOption::PATTERN      => [ '*.log' , '*.txt' ] ,
    FindFilesOption::SORT         => 'ci_name' ,
    FindFilesOption::ORDER        => Order::asc ,
    FindFilesOption::FILTER       => fn( SplFileInfo $f ) => $f->getFilename() ,
]) ;
```

### Pipeline interne

`findFiles` fait, dans l'ordre :

1. **Itère** via `DirectoryIterator` (non-récursif) ou `RecursiveIteratorIterator` (récursif).
2. **Filtre par mode** (`isFile()` / `isDir()`).
3. **Filtre dotfiles** sauf si `includeDots: true`.
4. **Filtre par pattern** (glob ou regex).
5. **Trie** via `sortFiles` si `sort` défini.
6. **Map** via `filter` si défini.

---

## `recursiveFilePaths`

```php
recursiveFilePaths( string $directory , array $options = [] ) : array
```

Variante **plus simple** que `findFiles` quand tu veux juste des **chemins string** (pas des `SplFileInfo`) avec filtre par extension et liste d'exclusion.

**Lève `RuntimeException`** si le dossier n'existe pas.

### Options

| Clé (string ou enum) | Type | Défaut | Effet |
|---|---|---|---|
| `'excludes'` / `RecursiveFilePathsOption::EXCLUDES` | `string[]` | `[]` | Liste de noms à exclure (comparaison exacte sur le filename). |
| `'extensions'` / `::EXTENSIONS` | `string[]` | `null` | Liste d'extensions autorisées (sans le point). `null` ou vide → toutes. |
| `'maxDepth'` / `::MAX_DEPTH` | `int` | `-1` | Profondeur max (`-1` = illimitée). |
| `'sortable'` / `::SORTABLE` | `bool` | `true` | Tri alphabétique du résultat. |

### Exemples

```php
use function oihana\files\recursiveFilePaths;
use oihana\files\enums\RecursiveFilePathsOption;

// Tous les fichiers récursivement
$all = recursiveFilePaths( __DIR__ ) ;

// Seulement les .php et .inc
$php = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::EXTENSIONS => [ 'php' , 'inc' ] ,
]) ;

// Exclure certains noms
$clean = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::EXCLUDES => [ 'ignore.php' , 'test.php' ] ,
]) ;

// Limiter à 2 niveaux
$shallow = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::MAX_DEPTH => 1 ,
]) ;

// Sans tri (perf)
$unsorted = recursiveFilePaths( __DIR__ , [
    RecursiveFilePathsOption::SORTABLE => false ,
]) ;
```

### `findFiles` vs `recursiveFilePaths` : quand choisir ?

| Besoin                                              | Préférer |
|-----------------------------------------------------|----------|
| Liste de chemins simples, filtre par extension      | `recursiveFilePaths` |
| Objets `SplFileInfo`, glob/regex, tri complexe      | `findFiles` |
| Mode non-récursif                                   | `findFiles` (recursiveFilePaths est toujours récursif) |
| Pattern complexe avec regex                         | `findFiles` |
| Exclusion par **nom de fichier exact**              | `recursiveFilePaths` |
| Exclusion par **pattern**                           | `findFiles` (mais avec une callback) ou [`copyFilteredFiles`](copying.md) |

---

## `shouldExcludeFile`

```php
shouldExcludeFile( string $filePath , array $excludePatterns ) : bool
```

Helper d'**exclusion** par pattern. Retourne `true` si le fichier matche au moins un pattern d'exclusion.

**Auto-détection** :

- Si le pattern ressemble à une regex (`'/^...$/i'`) → `preg_match`.
- Sinon → `fnmatch` (glob, avec flag `FNM_PATHNAME` → `*` ne traverse pas `/`).

**Tente le match sur deux cibles** : le chemin complet `$filePath` ET le `basename`. Match si **au moins une** des deux match.

```php
use function oihana\files\shouldExcludeFile;

$patterns = [
    '*.log' ,                // glob sur basename
    '/^error_\d+/' ,         // regex sur basename
    'config/db.php' ,        // glob sur full path
] ;

shouldExcludeFile( '/var/www/app/logs/access.log' , $patterns ) ;     // true (*.log matche access.log)
shouldExcludeFile( '/tmp/error_12345.txt' , $patterns ) ;             // true (regex matche)
shouldExcludeFile( '/var/www/app/config/db.php' , $patterns ) ;       // true (config/db.php matche)
shouldExcludeFile( '/var/www/index.php' , $patterns ) ;               // false
```

> ⚠ **Sécurité — ReDoS.** Même piège que [`findFiles`](#patterns--glob-ou-regex-détection-auto) : `$excludePatterns` est utilisé avec `preg_match` quand un pattern est délimité comme une regex. Les patterns doivent venir d'une source de confiance — voir [security.md § ReDoS](../security.md#redos-sur-patterns-regex-utilisateur).

Utilisé en interne par [`copyFilteredFiles`](copying.md). Réutilisable seul pour tes propres filtres.

---

## `sortFiles`

```php
sortFiles( array &$files , callable|string|array $sort , ?string $order = 'asc' ) : void
```

Trie un tableau de `SplFileInfo[]` **in-place** (modifie le tableau, ne retourne rien).

### Modes de tri

**1. Callback custom :**

```php
sortFiles( $files , fn( SplFileInfo $a , SplFileInfo $b ) => $a->getMTime() <=> $b->getMTime() ) ;
sortFiles( $files , $callback , 'desc' ) ; // inverse via array_reverse à la fin
```

**2. Critère string (clé intégrée) :**

| Clé          | Comparaison |
|--------------|---|
| `'name'`     | `strcmp` sur le filename (sensible à la casse). |
| `'ci_name'`  | `strcasecmp` (insensible à la casse). |
| `'extension'`| `strcasecmp` sur l'extension. |
| `'size'`     | Comparaison `<=>` de `getSize()`. |
| `'type'`     | `strcmp` de `getType()` ('file', 'dir', 'link', etc.). |
| `'atime'`    | Access time. |
| `'ctime'`    | Change time (inode). |
| `'mtime'`    | Modification time. |

**3. Liste de critères (multi-critères, premier non-zéro l'emporte) :**

```php
sortFiles( $files , [ 'type' , 'name' ] ) ;
// Trie d'abord par type (dir/file), puis par nom au sein de chaque type
```

### Exemples

```php
use function oihana\files\sortFiles;

// 1. Nom ascendant
sortFiles( $files , 'name' ) ;

// 2. Nom case-insensitive descendant
sortFiles( $files , 'ci_name' , 'desc' ) ;

// 3. Extension puis taille
sortFiles( $files , [ 'extension' , 'size' ] ) ;

// 4. Mtime descendant (plus récent en premier)
sortFiles( $files , fn( $a , $b ) => $a->getMTime() <=> $b->getMTime() , 'desc' ) ;

// 5. Type puis nom case-insensitive
sortFiles( $files , [ 'type' , 'ci_name' ] ) ;
```

> 💡 Une clé inconnue dans la liste retourne `0` (pas d'erreur) — la sort continue avec les critères suivants.

---

## `hasFiles`

```php
hasFiles( ?string $dir , bool $strict = false ) : bool
```

Indique si un dossier contient **au moins un fichier**, ou **uniquement des fichiers** en mode strict.

**Lève `DirectoryException`** si le dossier n'existe pas (via `assertDirectory`).

```php
use function oihana\files\hasFiles;

hasFiles( '/var/www' ) ;
// → true si au moins un fichier (même s'il y a aussi des dossiers)

hasFiles( '/var/www' , strict: true ) ;
// → true seulement si /var/www contient EXCLUSIVEMENT des fichiers
//   (aucun sous-dossier, aucun symlink, etc.)
```

---

## `hasDirectories`

```php
hasDirectories( ?string $dir , bool $strict = false ) : bool
```

Symétrique de `hasFiles`. Indique si un dossier contient **au moins un sous-dossier**, ou **uniquement des sous-dossiers** en mode strict.

```php
use function oihana\files\hasDirectories;

hasDirectories( '/var/www' ) ;
// → true si au moins un sous-dossier

hasDirectories( '/var/www' , strict: true ) ;
// → true seulement si EXCLUSIVEMENT des sous-dossiers
```

### Cas d'usage : tester si un dossier est "vide" sémantiquement

```php
if ( !hasFiles( $dir ) && !hasDirectories( $dir ) ) {
    // Dossier vide (à part `.` et `..`)
    deleteDirectory( $dir ) ;
}
```

---

## Voir aussi

- [Copie filtrée](copying.md) — `copyFilteredFiles` (utilise `shouldExcludeFile`).
- [Assertions](assertions.md) — `assertDirectory` (utilisée en amont).
- [Énumérations](../enums.md) — `FindFilesOption`, `FindMode`, `RecursiveFilePathsOption`.
- [Vue d'ensemble](README.md).
