# TOML — `oihana\files\toml`

Le module **`oihana\files\toml`** expose une seule fonction — [`resolveTomlConfig`](#resolvetomlconfig) — pour **charger un fichier TOML** et le **fusionner** avec une configuration par défaut.

> 💡 Repose sur la dépendance [`devium/toml`](https://github.com/vanodevium/toml) (conforme TOML 1.0). Voir [Dépendances](../getting-started/dependencies.md#deviumtoml).

## Pourquoi TOML ?

[TOML](https://toml.io) (Tom's Obvious Minimal Language) est un format de configuration :

- **lisible** humainement (plus que JSON, comparable à YAML mais sans les pièges d'indentation) ;
- **typé** (string, int, float, bool, datetime, array, table) ;
- **spec stricte** (1.0 stable) — pas d'ambiguïté entre implémentations ;
- **adopté largement** par Rust (`Cargo.toml`), Python (`pyproject.toml`), et de nombreux outils modernes.

Exemple de fichier TOML :

```toml
debug = false
timezone = "UTC"

[database]
host = "localhost"
port = 3306
username = "root"

[[servers]]
name = "primary"
host = "10.0.0.1"

[[servers]]
name = "replica"
host = "10.0.0.2"
```

Après décodage par `devium/toml`, ça devient un tableau associatif PHP standard.

---

## `resolveTomlConfig`

```php
resolveTomlConfig(
    ?string   $filePath ,
    ?array    $defaultConfig = []   ,
    ?string   $defaultPath   = null ,
    ?callable $init          = null
) : array
```

Pipeline complet : **résolution de chemin → assertion → décodage → fusion → post-traitement**.

### Paramètres

| Paramètre        | Type        | Effet |
|------------------|-------------|-------|
| `$filePath`      | `?string`   | Chemin du TOML. **Si `null` ou vide → seule la config par défaut est utilisée.** |
| `$defaultConfig` | `?array`    | Valeurs par défaut, fusionnées sous la config décodée (priorité plus basse). |
| `$defaultPath`   | `?string`   | Dossier de base où chercher un `$filePath` relatif. Si `null`, un `$filePath` relatif est résolu par rapport au répertoire de travail courant. |
| `$init`          | `?callable` | Post-traitement appliqué à la config finale. Signature : `fn(array): array`. |

### Logique de résolution du chemin

1. **Ajout d'extension** : si `$filePath` ne se termine pas par `.toml`, le suffixe est ajouté automatiquement (`FileExtension::TOML`).
2. **Résolution relative** : si `$filePath` n'est pas absolu **et** qu'un `$defaultPath` est fourni, on joint `joinPaths($defaultPath, $filePath)` et on teste `is_file`. Si le fichier existe à cet endroit, ce chemin est retenu (`$defaultPath` est validé par `assertDirectory`).
3. **`assertFile`** : vérifie que le chemin final existe et est lisible. Un chemin resté relatif est résolu par rapport au répertoire de travail courant par les fonctions de fichiers de PHP.

### Logique de fusion

- **Fusion profonde** via [`deepMerge`](../getting-started/dependencies.md#oihanaphp-core) — les sous-tableaux sont mergés récursivement (les valeurs scalaires du TOML écrasent celles des defaults, mais les sous-arrays sont mergés).
- **Précédence** : TOML > défaut. La valeur du fichier remplace la valeur du défaut au même chemin.

### Logique du callback `$init`

Si fourni, appelé en dernier avec la config finale. Doit retourner un `array` — typiquement pour :
- valider que des clés obligatoires sont présentes ;
- transformer certains chemins (résoudre des `${VAR}`, expanser `~`, etc.) ;
- enrichir avec des données calculées (hostname, version, etc.).

### Exceptions

| Exception | Cas |
|---|---|
| `FileException` | Chemin résolu invalide ou fichier inexistant. |
| `DirectoryException` | `$defaultPath` fourni mais pas un dossier valide. |
| `Devium\Toml\TomlError` | Contenu TOML mal formé. |

> ⚠ Les trois exceptions sont **distinctes** — capture-les séparément si tu veux différencier les types d'erreur.

---

## Exemple complet

`config/default.toml` (committed en VCS) :

```toml
debug = false
timezone = "UTC"

[database]
host = "localhost"
port = 3306
```

`config/local.toml` (non committed) :

```toml
debug = true

[database]
host = "db.production.internal"
```

```php
use function oihana\files\toml\resolveTomlConfig;

$defaultConfig = [
    'app' => [
        'name'    => 'MyApp' ,
        'version' => '1.0.0' ,
    ] ,
    'database' => [
        'port'    => 3306 ,
        'timeout' => 30 ,
    ] ,
] ;

$config = resolveTomlConfig(
    'config/local' ,                 // .toml ajouté automatiquement
    $defaultConfig ,
    __DIR__ ,                        // base pour résoudre 'config/local'
    fn( array $cfg ) => $cfg + [     // post-traitement : ajout d'un champ calculé
        'hostname' => gethostname() ,
    ] ,
) ;
```

Résultat (deep-merge) :

```php
[
    'app' => [
        'name'    => 'MyApp' ,
        'version' => '1.0.0' ,
    ] ,
    'database' => [
        'host'    => 'db.production.internal' ,  // override TOML
        'port'    => 3306 ,                       // conservé du default
        'timeout' => 30 ,                         // conservé du default
    ] ,
    'debug'    => true ,
    'timezone' => 'UTC' ,
    'hostname' => 'web-01' ,                      // ajouté par $init
]
```

---

## Cas d'usage : config par environnement

```php
$env = getenv('APP_ENV') ?: 'dev' ;

$config = resolveTomlConfig(
    "config/env/{$env}" ,           // config/env/dev.toml ou prod.toml
    $defaults ,
    __DIR__ ,
) ;
```

## Cas d'usage : config absente acceptable

```php
// Si l'utilisateur n'a pas créé local.toml, on garde le défaut
$config = resolveTomlConfig(
    $userConfigPath ?? null ,        // null → seuls les defaults
    $defaults ,
) ;
```

## Cas d'usage : init avec validation

```php
$config = resolveTomlConfig(
    'config/app' ,
    $defaults ,
    __DIR__ ,
    function( array $cfg ) {
        // Validation des clés obligatoires
        foreach ( [ 'database.host' , 'database.port' ] as $required ) {
            $path = explode( '.' , $required ) ;
            $val  = $cfg ;
            foreach ( $path as $key ) {
                if ( !isset( $val[ $key ] ) ) {
                    throw new \RuntimeException( "Missing required config: $required" ) ;
                }
                $val = $val[ $key ] ;
            }
        }
        return $cfg ;
    } ,
) ;
```

---

## `escapeTomlString`

```php
escapeTomlString( string $value ) : string
```

Échappe une valeur pour l'écrire dans une **chaîne de base** TOML (`"…"`). Le résultat ne contient **pas** les guillemets autour.

- `\` et `"` sont échappés ;
- les caractères de contrôle qui ont un raccourci deviennent `\b`, `\t`, `\n`, `\f`, `\r` ;
- les autres caractères de contrôle et DEL deviennent `\uXXXX`.

Sans cela, un mot de passe qui contient un guillemet ou une barre oblique inverse donne un fichier que le parseur refuse : toute la configuration devient illisible.

```php
use function oihana\files\toml\escapeTomlString;

echo escapeTomlString( 'pa"ss\word' ) ; // pa\"ss\\word
echo escapeTomlString( "two\nlines" ) ; // two\nlines
echo escapeTomlString( "\x01" ) ;       // \u0001
```

---

## `setTomlValue`

```php
setTomlValue( string $content , ?string $section , string $key , string $value ) : string
```

Écrit une valeur texte dans un document TOML, **dans une seule table**, sans toucher au reste du texte.

| Paramètre  | Type      | Effet |
|------------|-----------|-------|
| `$content` | `string`  | Le document TOML. |
| `$section` | `?string` | La table (`database`, `app.database`), sans tenir compte de la casse. `null` pour la table racine, au-dessus du premier en-tête. |
| `$key`     | `string`  | La clé. |
| `$value`   | `string`  | La valeur brute, écrite en chaîne de base et échappée par [`escapeTomlString`](#escapetomlstring). |

### Pourquoi « dans une seule table »

Une même clé revient souvent dans plusieurs tables : `host` ou `pass` sous `[database]`, `[smtp]` et `[mysql]`. Un remplacement qui ne s'arrête pas à la table visée écrase des identifiants qui n'ont rien à voir.

### Ce qui est gardé

Le document est modifié **comme du texte**, pas décodé puis réécrit : l'alignement avant `=`, le commentaire de fin de ligne et tout le reste du fichier restent tels quels. Les fins de ligne suivent celles du document (CRLF s'il en a, LF sinon).

### Quand la clé ou la table manque

- **La clé manque** : elle est ajoutée à la fin de sa table, avant les lignes vides qui la ferment.
- **La table manque** : elle est ajoutée à la fin du document.

> ⚠ Seules les clés écrites en chaîne d'une ligne (`"…"` ou `'…'`) sont reconnues. Une clé qui vaut un nombre, un booléen, un tableau ou une chaîne sur plusieurs lignes n'est pas remplacée : une nouvelle ligne est ajoutée pour elle.

```php
use function oihana\files\toml\setTomlValue;

$toml = <<<TOML
[database]
host = "localhost" # local

[smtp]
host = "mail"
TOML;

echo setTomlValue( $toml , 'database' , 'host' , 'db.example.com' ) ;
// [database]
// host = "db.example.com" # local
//
// [smtp]
// host = "mail"
```

---

## Comparaison : TOML vs alternatives

| Format  | Lisibilité | Typage | Standard | Quand l'utiliser ici |
|---------|------------|--------|----------|----------------------|
| **TOML** | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | Config humaine, deep nested, multi-environments |
| **PHP** | ⭐⭐ | ⭐⭐⭐ | n/a | Config 100% PHP, expressions calculées → [`requireAndMergeArrays`](../files/reading.md#requireandmergearrays) |
| **JSON** | ⭐ | ⭐⭐ | ⭐⭐⭐ | Pas pratique en humain (pas de commentaires) |
| **YAML** | ⭐⭐ | ⭐⭐ | ⭐⭐ | Lisible mais ambigu (indentation) |
| **`.env`** | ⭐⭐⭐ | ⭐ | ⭐⭐ | Variables d'env plates, pas de structure |

Pour `oihana/*`, le choix par défaut est :
- **TOML** pour les configs versionnées au repo (utilisateur final / déploiement).
- **PHP via `requireAndMergeArrays`** pour les configs internes au framework (DI containers, mappings calculés).

## Voir aussi

- [Lecture](../files/reading.md#requireandmergearrays) — `requireAndMergeArrays` pour le même pattern en pur PHP.
- [Path](../path/README.md) — `isAbsolutePath` et `joinPaths` utilisées en interne pour la résolution du chemin.
- [Assertions](../files/assertions.md) — `assertFile`, `assertDirectory`.
- [Dépendances](../getting-started/dependencies.md#deviumtoml) — `devium/toml`, `deepMerge`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Sommaire FR](../README.md).
