# Lecture

Quatre fonctions pour lire le contenu d'un fichier de manière idiomatique et efficace, et pour charger plusieurs fichiers PHP retournant des tableaux.

- [`getFileLines`](#getfilelines) — toutes les lignes en `array`.
- [`getFileLinesGenerator`](#getfilelinesgenerator) — toutes les lignes en `Generator` (memory-friendly).
- [`countFileLines`](#countfilelines) — comptage rapide par chunks de 8 ko.
- [`requireAndMergeArrays`](#requireandmergearrays) — `require` de plusieurs fichiers retournant des `array`, puis merge.

> 💡 Toutes les fonctions de lecture appellent [`assertFile`](assertions.md#assertfile) en amont — tu n'as pas besoin de vérifier l'existence avant.

---

## `getFileLines`

```php
getFileLines(
    ?string   $file ,
    ?callable $map = null
) : ?array
```

Lit **toutes les lignes** d'un fichier dans un tableau. Chaque ligne est `rtrim`'ée (suppression de `\r\n` ou `\n`).

**Implémentation :** appelle `getFileLinesGenerator` en interne puis convertit en array via `iterator_to_array` — pratique mais charge tout en mémoire. Pour les très gros fichiers, préférer le générateur.

**Paramètres :**

- `$file` : chemin (passé à `assertFile`).
- `$map` : callback optionnel `fn(string $line): mixed` appliqué à chaque ligne.

**Retour :** tableau de lignes (ou résultats du mapping). Tableau **vide** si le fichier existe mais est de taille 0.

```php
use function oihana\files\getFileLines;

// Lecture simple
$lines = getFileLines( '/var/log/myapp.log' ) ;
// → ['line 1', 'line 2', ...]

// Avec mapping : parse CSV à la volée
$rows = getFileLines( '/data/users.csv' , fn( string $line ) => str_getcsv( $line ) ) ;
// → [['name','age'], ['alice','30'], ...]

// Filtrer + transformer
$errors = getFileLines( '/var/log/app.log' , function( string $line ) {
    return str_contains( $line , 'ERROR' ) ? trim( $line ) : null ;
}) ;
$errors = array_filter( $errors ) ;
```

> ⚠ **Mémoire** : un fichier de 100 Mo = 100 Mo en RAM. Voir `getFileLinesGenerator` pour le streaming.

---

## `getFileLinesGenerator`

```php
getFileLinesGenerator(
    ?string   $file ,
    ?callable $map = null
) : Generator
```

Version **memory-friendly** de `getFileLines`. Yield chaque ligne au fur et à mesure — la mémoire ne contient qu'**une ligne à la fois**, plus le buffer interne de `fopen`.

**Garanties :**

- Le handle est fermé en `finally`, donc même si tu sors prématurément du `foreach`.
- Pas de lecture spéculative — si tu break après 10 lignes, les 999 990 suivantes ne sont jamais touchées.

```php
use function oihana\files\getFileLinesGenerator;

// Parcours simple
foreach ( getFileLinesGenerator( '/var/log/huge.log' ) as $line ) {
    echo $line , PHP_EOL ;
}

// Parse CSV ligne par ligne
foreach ( getFileLinesGenerator( '/data/users.csv' , fn( $l ) => str_getcsv( $l ) ) as $row ) {
    print_r( $row ) ;
}

// Stopper dès qu'on trouve une ligne d'erreur
foreach ( getFileLinesGenerator( '/var/log/app.log' ) as $line ) {
    if ( str_contains( $line , 'FATAL' ) ) {
        echo "Erreur fatale détectée : $line" ;
        break ; // handle fermé proprement
    }
}
```

### Quand choisir lequel ?

| Cas                                          | Préférer                  |
|----------------------------------------------|---------------------------|
| Fichier < 10 Mo, traitement vectorisé        | `getFileLines`            |
| Fichier > 10 Mo                              | `getFileLinesGenerator`   |
| Itération unique en streaming                | `getFileLinesGenerator`   |
| Besoin de `count`/`array_slice`              | `getFileLines` (le générateur n'est pas un array) |

---

## `countFileLines`

```php
countFileLines( ?string $file ) : int
```

Compte les lignes d'un fichier **par chunks de 8 Ko** en utilisant `substr_count($chunk, "\n")`. Beaucoup plus rapide qu'un `count(file($path))` qui charge tout, ou qu'une boucle `fgets` ligne par ligne.

**Détails :**

- Fichier de taille 0 → retourne `0` immédiatement.
- Compte le nombre de `\n` — un fichier sans `\n` final peut compter une ligne de moins que ce que ton éditeur affiche.

```php
use function oihana\files\countFileLines;

$total = countFileLines( '/var/log/access.log' ) ;
// → 1283491

if ( countFileLines( '/var/log/errors.log' ) > 100 ) {
    sendAlert() ;
}
```

> ⚠ Le compteur dépend du **caractère `\n`**. Pour des fichiers avec terminaisons `\r` seules (vieux Mac), le résultat est `0`. En 2026, ces fichiers sont rares — mais à savoir.

---

## `requireAndMergeArrays`

```php
requireAndMergeArrays(
    array $filePaths ,
    bool  $recursive = true
) : array
```

Charge plusieurs fichiers PHP via `require`, **chacun devant retourner un `array`**, puis les fusionne dans l'ordre.

**Détails :**

- Si un fichier n'existe pas → `RuntimeException`.
- Si un fichier ne retourne pas un `array` → `RuntimeException`.
- `$recursive: true` (défaut) → `deepMerge` de [`oihana\core\arrays`](../getting-started/dependencies.md#oihanaphp-core) (fusion récursive des sous-tableaux).
- `$recursive: false` → `array_merge` classique (les clés numériques sont renumérotées, les chaînes écrasées).

### Usage typique : config par couches

```php
use function oihana\files\requireAndMergeArrays;

$config = requireAndMergeArrays([
    __DIR__ . '/config/defaults.php' ,
    __DIR__ . '/config/env/' . $env . '.php' ,
    __DIR__ . '/config/local.php' ,
]) ;
```

Avec `config/defaults.php` :

```php
return [
    'app' => [
        'debug'    => false ,
        'timezone' => 'UTC' ,
        'logs'     => '/var/log/app' ,
    ] ,
] ;
```

Et `config/env/dev.php` :

```php
return [
    'app' => [
        'debug' => true ,
    ] ,
] ;
```

Résultat (merge récursif) :

```php
[
    'app' => [
        'debug'    => true ,   // surchargé
        'timezone' => 'UTC' ,  // conservé
        'logs'     => '/var/log/app' , // conservé
    ] ,
]
```

### Différence merge récursif vs plat

```php
// recursive: true  → fusion profonde des arrays imbriqués
$a = [ 'app' => [ 'debug' => false , 'tz' => 'UTC' ] ] ;
$b = [ 'app' => [ 'debug' => true ] ] ;
// → [ 'app' => [ 'debug' => true , 'tz' => 'UTC' ] ]

// recursive: false → array_merge écrase au niveau top
// → [ 'app' => [ 'debug' => true ] ]   ← 'tz' perdu !
```

> 💡 **Toujours `recursive: true` pour les fichiers de config** sauf cas spécifique où tu veux un override complet d'une section.

---

## Voir aussi

- [Assertions](assertions.md) — `assertFile` (utilisée en amont).
- [TOML](../toml/README.md) — `resolveTomlConfig` pour le même pattern config-by-layers, mais en TOML.
- [Découverte](discovery.md) — `findFiles` pour récupérer les chemins à lire.
- Dépendances : [`deepMerge`](../getting-started/dependencies.md#oihanaphp-core).
- [Vue d'ensemble](README.md).
