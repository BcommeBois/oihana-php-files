# Options — `oihana\options`

Le namespace **`oihana\options`** expose **deux classes abstraites** qui forment ensemble un pattern central de `oihana/*` : la modélisation d'**objets de configuration fortement typés**, hydratables, sérialisables et transformables en arguments CLI.

- [`Options`](#la-classe-options) — classe abstraite à étendre pour définir un objet de config.
- [`Option`](#la-classe-option) — contrat associé qui mappe les **noms de propriétés** vers les **noms d'options CLI**.

> 💡 Le namespace **`oihana\options`** est à distinguer du dossier `oihana\files\options/` (qui contient des **implémentations concrètes** comme `MakeFileOptions` et `OwnershipInfos` — voir [Options concrètes](make-file-options.md)).

## Le pattern Options en bref

**Problème** : passer 10 paramètres à une fonction PHP est illisible. Passer un tableau associatif perd le typage. Construire une classe wrapper pour chaque cas est verbeux.

**Solution** : une classe abstraite `Options` qui apporte automatiquement :

1. **Hydratation** depuis un tableau ou un objet (via le constructeur).
2. **Sérialisation** en `array` et en JSON.
3. **Formatage de templates** (`'{{host}}:{{port}}'` → `'localhost:8080'`).
4. **Génération d'arguments CLI** (`--host "localhost" --port 8080`).
5. **Fusion** de plusieurs sources de config (`resolve(...)`).

```php
class ServerOptions extends Options
{
    public string $host = 'localhost' ;
    public int    $port = 8080 ;
    public bool   $debug = false ;
}

$opts = new ServerOptions( [ 'debug' => true ] ) ;

echo $opts->format( 'http://{{host}}:{{port}}' ) ;
// → http://localhost:8080

echo json_encode( $opts ) ;
// → {"host":"localhost","port":8080,"debug":true}
```

---

## La classe `Options`

```php
abstract class Options implements ClearableArrayable, Cloneable, JsonSerializable
```

Implémente 3 interfaces de [`oihana/php-enums`](../getting-started/dependencies.md#oihanaphp-enums) :

- `Arrayable` (via `ClearableArrayable`) — contrat `toArray()`.
- `ClearableArrayable` — contrat `toArray(bool $clear)`.
- `Cloneable` — contrat `clone(): static`.
- `JsonSerializable` (PHP natif) — pour `json_encode()`.

Utilise le trait `ReflectionTrait` (de [`oihana/php-reflect`](../getting-started/dependencies.md#oihanaphp-reflect)) pour énumérer les propriétés publiques.

### `__construct( array|object|null $init = null )`

**Hydratation automatique** depuis un tableau ou un objet. Seules les propriétés publiques **déclarées** sur la classe sont mises à jour — les clés inconnues sont silencieusement ignorées.

```php
class MyOptions extends Options
{
    public string $name = '' ;
    public int    $count = 0 ;
}

$o = new MyOptions( [
    'name'    => 'foo' ,
    'count'   => 42 ,
    'unknown' => 'ignored' , // ignoré silencieusement
] ) ;

// Aussi : objet en entrée
$o = new MyOptions( $somestdClass ) ;
```

### `create( array|Options|null $options = null ) : Options` (statique)

**Factory** souple :

- Tableau → nouvelle instance avec hydratation.
- Instance existante → renvoyée telle quelle (pas de copie).
- `null` → nouvelle instance vide.

```php
$o = MyOptions::create( [ 'name' => 'foo' ] ) ; // new MyOptions(['name'=>'foo'])
$o = MyOptions::create( $existing ) ;            // === $existing
$o = MyOptions::create() ;                       // new MyOptions()
```

### `clone() : static`

**Deep copy** via `serialize`/`unserialize`. À utiliser quand tu veux dupliquer un Options sans risquer un partage de référence sur les sous-tableaux/objets.

```php
$base   = new ServerOptions( [ 'host' => 'localhost' ] ) ;
$custom = $base->clone() ;
$custom->host = 'remote' ;
// $base->host est toujours 'localhost'
```

### `toArray( bool $clear = false ) : array`

Convertit l'objet en tableau associatif via les propriétés publiques.

**Si `$clear: true`** :
- les chaînes vides (`''` après `trim`) deviennent `null` ;
- les tableaux vides (`[]`) deviennent `null` ;
- les entrées `null` sont **filtrées** du résultat.

```php
$o = new ServerOptions( [
    'host'  => 'localhost' ,
    'port'  => 8080 ,
    'debug' => null ,
    'empty' => '' ,
] ) ;

$o->toArray() ;
// [ 'host' => 'localhost', 'port' => 8080, 'debug' => null, 'empty' => '', ... ]

$o->toArray( true ) ;
// [ 'host' => 'localhost', 'port' => 8080 ]
```

### `jsonSerialize() : object`

Sérialisation **JSON** via `json_encode`. Renvoie un **`object`** (pas un `array`) → garantit que `json_encode` produit `{}` même quand le résultat est vide (et pas `[]`).

```php
echo json_encode( new ServerOptions( [ 'host' => '' , 'debug' => null ] ) ) ;
// → {}  (et pas [])
```

Utilise `toArray(true)` en interne — les valeurs vides sont filtrées.

### `format( ?string $template , string $prefix = '{{' , string $suffix = '}}' , ?string $pattern = null ) : ?string`

**Formate un template** en remplaçant les placeholders `{{prop}}` par les valeurs des propriétés de l'objet.

```php
$o = new ServerOptions( [ 'host' => 'example.com' , 'port' => 443 ] ) ;

echo $o->format( 'https://{{host}}:{{port}}' ) ;
// → https://example.com:443

// Délimiteurs custom
echo $o->format( 'Hello %%host%%!' , '%%' , '%%' ) ;
// → Hello example.com!

// Propriété manquante → remplacée par chaîne vide
echo $o->format( 'X: {{nonexistent}}' ) ;
// → X:
```

### `formatArray( array &$data , array|object|null $source = null , ... ) : array`

**Formate récursivement** tous les `string` d'un tableau en utilisant l'objet comme source de placeholders (ou un `$source` externe).

Modifie `$data` **par référence**.

```php
$o = new ServerOptions( [ 'host' => 'example.com' , 'apiVersion' => 'v1' ] ) ;

$payload = [
    'base' => 'https://{{host}}/api/{{apiVersion}}' ,
    'endpoints' => [
        'users' => 'https://{{host}}/api/{{apiVersion}}/users' ,
    ] ,
    'unchanged' => 42 ,
] ;

$o->formatArray( $payload ) ;

// $payload =
// [
//     'base'      => 'https://example.com/api/v1' ,
//     'endpoints' => [ 'users' => 'https://example.com/api/v1/users' ] ,
//     'unchanged' => 42 ,
// ]
```

### `formatFromDocument( array|object $document , ... ) : void`

**Inverse** de `format` : formate les **propriétés string publiques** de l'objet en utilisant un document externe comme source de placeholders.

```php
$o = new ServerOptions() ;
$o->url = 'https://{{host}}/{{path}}' ;

$o->formatFromDocument( [ 'host' => 'example.com' , 'path' => 'docs' ] ) ;

echo $o->url ;
// → https://example.com/docs
```

> 💡 Utile pour expanser des templates **après** hydratation, avec des variables d'environnement par exemple.

### `getOptions( ?string $clazz , ... ) : string`

**Conversion en chaîne d'arguments CLI**. Le cœur du pattern Options pour intégration avec Symfony Console, scripts shell, exec, etc.

```php
class MyOption extends Option
{
    public const string HOST    = 'host' ;
    public const string PORT    = 'port' ;
    public const string VERBOSE = 'verbose' ;
}

class MyOptions extends Options
{
    public string $host    = 'localhost' ;
    public int    $port    = 8080 ;
    public bool   $verbose = true ;
}

$opts = new MyOptions() ;
echo $opts->getOptions( MyOption::class ) ;
// → --host "localhost" --port 8080 --verbose
```

#### Paramètres de `getOptions`

| Paramètre        | Type                            | Défaut    | Effet |
|------------------|---------------------------------|-----------|-------|
| `$clazz`         | `?string` (class-string<Option>) | `null`   | Classe `Option` à utiliser pour le mapping. Sans elle, retourne `''`. |
| `$prefix`        | `callable\|string\|null`        | `'--'`    | Préfixe par défaut, ou callback `fn(string $name): string`. |
| `$excludes`      | `?array`                        | `null`    | Propriétés à ignorer. |
| `$separator`     | `callable\|string`              | `' '`     | Séparateur entre option et valeur (espace, `=`, etc.), ou callback. |
| `$order`         | `?array`                        | `null`    | Ordre forcé pour certaines propriétés. |
| `$reverseOrder`  | `bool`                          | `false`   | Si `true`, les propriétés ordonnées passent à la fin. |

#### Comportements par type

| Type de valeur | Sortie |
|---|---|
| `null` | propriété **ignorée**. |
| `true` | option en flag : `--verbose` (sans valeur). |
| `array` | option **répétée** : `--list "a" --list "b"`. |
| autre (string, int, bool=false) | `--option "value"` avec `json_encode` sur la valeur. |

#### Exemple avancé : mix de préfixes et séparateurs

```php
$opts->getOptions(
    MyOption::class ,
    prefix: fn( string $name ) => match( $name )
    {
        'foo'     => '--' ,
        'verbose' => '-' ,
        'list'    => '/opt:' ,
        default   => '' ,
    } ,
    excludes: [ 'internalFlag' ] ,
    separator: fn( string $name ) => $name === 'list' ? '=' : ' ' ,
) ;
// → --foo "value" -verbose /opt:list="a" /opt:list="b"
```

#### Ordre forcé

```php
$opts->getOptions(
    MyOption::class ,
    order: [ 'verbose' , 'foo' ] ,         // 'verbose' et 'foo' en premier
    reverseOrder: false ,                  // (true → à la fin)
) ;
```

### `resolve( ...$sources ) : static` (statique)

**Fusion** de plusieurs sources de configuration en une instance unique. Précédence : **dernière source > première**.

Accepte :
- **Arrays** → merge direct.
- **Instances `Options`** (ou tout `Arrayable` / `ClearableArrayable`) → conversion via `toArray()` (ou `toArray(true)` pour `ClearableArrayable`).
- **`null`** → ignoré.

```php
$defaults = new ServerOptions( [ 'host' => 'localhost' , 'port' => 8080 ] ) ;
$user     = [ 'port' => 8888 , 'debug' => true ] ;
$override = new ServerOptions( [ 'debug' => false ] ) ;

$final = ServerOptions::resolve( $defaults , $user , $override ) ;
// host = localhost, port = 8888, debug = false
```

**Lève `InvalidArgumentException`** si une source n'est ni `array`, ni `Options`, ni `Arrayable`/`ClearableArrayable`, ni `null`.

### `__toString() : string`

Par défaut, retourne `''`. À **surcharger** dans les classes concrètes pour un affichage utile (ex. `MakeFileOptions` retourne le chemin du fichier).

---

## La classe `Option`

```php
abstract class Option
```

**Contrat** utilisé par `Options::getOptions()` pour mapper les noms de propriétés Object vers les noms d'options CLI. Aucune propriété — uniquement deux méthodes statiques surchargeables.

Utilise le trait `ConstantsTrait` (de `oihana/php-reflect`) pour exposer ses constantes via réflexion.

### `getCommandOption( string $option ) : string` (statique)

**Transforme** le nom de propriété en nom d'option CLI. Implémentation par défaut : `hyphenate()` (de `oihana/php-core`) → kebab-case.

```php
Option::getCommandOption( 'dryRun' ) ;     // → 'dry-run'
Option::getCommandOption( 'apiKey' ) ;     // → 'api-key'
Option::getCommandOption( 'maxRetries' ) ; // → 'max-retries'
```

**Surcharge** pour une autre convention :

```php
class MyOption extends Option
{
    public static function getCommandOption( string $option ): string
    {
        return strtoupper( $option ) ; // → 'DRYRUN', 'APIKEY'
    }
}
```

### `getCommandPrefix( string $option ) : ?string` (statique)

**Préfixe par option**. Retourne `null` par défaut → utilise le préfixe global passé à `getOptions()`.

**Surcharge** pour différencier flags courts/longs ou notations alternatives :

```php
class MyOption extends Option
{
    public const string VERBOSE = 'verbose' ;
    public const string HOST    = 'host' ;

    public static function getCommandPrefix( string $option ): ?string
    {
        return match( $option )
        {
            self::VERBOSE => '-' ,    // -verbose (court)
            default       => '--' ,   // --host (long)
        } ;
    }
}
```

---

## Pattern complet : exemple end-to-end

```php
use oihana\options\Option;
use oihana\options\Options;

// 1. Définir le mapping CLI
class RsyncOption extends Option
{
    public const string ARCHIVE     = 'archive' ;
    public const string VERBOSE     = 'verbose' ;
    public const string DRY_RUN     = 'dryRun' ;
    public const string EXCLUDE     = 'exclude' ;
    public const string DESTINATION = 'destination' ;
}

// 2. Définir le DTO d'options
class RsyncOptions extends Options
{
    public bool    $archive     = true ;
    public bool    $verbose     = false ;
    public bool    $dryRun      = false ;
    public ?array  $exclude     = null ;
    public ?string $destination = null ;
}

// 3. Hydrater depuis l'input utilisateur (HTTP, CLI, fichier de config…)
$rsync = RsyncOptions::resolve(
    [ 'archive' => true , 'verbose' => true ] ,           // défauts métier
    parse_ini_file( '/etc/myapp/rsync.ini' ) ?: [] ,      // config user
    [ 'dryRun' => $_GET[ 'dryRun' ] ?? false ] ,          // override request
) ;

// 4. Construire la commande shell
$cmd = 'rsync ' . $rsync->getOptions( RsyncOption::class ) ;
// → rsync --archive --verbose --dry-run --exclude "..." --destination "..."

// 5. (Optionnel) Sérialiser pour log ou audit
file_put_contents( '/var/log/rsync.json' , json_encode( $rsync ) ) ;
```

## Voir aussi

- [Options concrètes](make-file-options.md) — `MakeFileOptions`, `OwnershipInfos` (exemples du codebase).
- [Dépendances](../getting-started/dependencies.md) — `oihana/php-reflect` (`ReflectionTrait`), `oihana/php-enums` (interfaces), `oihana/php-core` (`hyphenate`, `formatDocument`).
- [Création](../files/creation.md) — `makeFile` accepte un `MakeFileOptions` comme alternative aux paramètres positionnels.
- [Système](../files/system.md#getownershipinfos) — `getOwnershipInfos` retourne une instance `OwnershipInfos`.
- [Sommaire FR](../README.md).
