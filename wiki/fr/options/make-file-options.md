# Options concrètes — `oihana\files\options`

Deux classes du namespace `oihana\files\options` qui étendent [`Options`](README.md) pour fournir des **types de configuration spécialisés** :

- [`MakeFileOptions`](#makefileoptions) — wrapper typé pour les options de `makeFile`.
- [`OwnershipInfos`](#ownershipinfos) — informations de propriétaire/groupe retournées par `getOwnershipInfos`.

> 💡 Ces classes sont des **exemples canoniques** du pattern `Options` dans `oihana/php-files`. La mécanique générique vit dans [`oihana\options\Options`](README.md) ; les fichiers ci-dessous montrent **comment concrétiser**.

---

## `MakeFileOptions`

```php
namespace oihana\files\options ;

class MakeFileOptions extends Options
{
    public bool    $append      = false ;
    public ?string $content     ;        // pas de défaut
    public ?string $file        = null ;
    public bool    $force       = true ;
    public ?string $group       = null ;
    public bool    $lock        = true ;
    public ?bool   $overwrite   = true ;
    public ?string $owner       = null ;
    public ?int    $permissions = null ;

    public function __toString() : string
    {
        return $this->file ?? '' ;
    }
}
```

### Rôle

Wrapper d'options pour la fonction [`makeFile`](../files/creation.md#makefile). Les **9 propriétés publiques** correspondent exactement aux clés acceptées par la signature options-as-array de `makeFile`.

### Différence avec `MakeFileOption` (l'énumération de constantes)

Attention à ne pas confondre :

| | `MakeFileOption` (singulier) | `MakeFileOptions` (pluriel) |
|---|---|---|
| Namespace | `oihana\files\enums` | `oihana\files\options` |
| Type | classe de constantes (`Option` de l'énumération) | classe DTO (étend `Options`) |
| Usage | `[ MakeFileOption::APPEND => true ]` (clés de tableau) | `(new MakeFileOptions())->append = true` (propriétés objet) |
| Quand utiliser | tableau d'options ad-hoc | DTO réutilisable, formattable, sérialisable |

### Usage : équivalence des deux styles

```php
use function oihana\files\makeFile;
use oihana\files\options\MakeFileOptions;
use oihana\files\enums\MakeFileOption;

// Style 1 — tableau + énumération de clés (rapide)
makeFile( '/path/file.txt' , 'Hello' , [
    MakeFileOption::PERMISSIONS => 0600 ,
    MakeFileOption::LOCK        => false ,
]) ;

// Style 2 — DTO typé (plus structuré, plus verbeux)
$opts = new MakeFileOptions([
    'file'        => '/path/file.txt' ,
    'content'     => 'Hello' ,
    'permissions' => 0600 ,
    'lock'        => false ,
]) ;
makeFile( $opts->toArray() ) ;

// Style 3 — DTO + override programmatique
$base = new MakeFileOptions([ 'force' => true , 'permissions' => 0644 ]) ;
$file = $base->clone() ;
$file->file        = '/log/app.log' ;
$file->content     = $logLine ;
$file->permissions = 0640 ;
makeFile( $file->toArray() ) ;
```

### `__toString()`

Renvoie `$this->file ?? ''` — utile pour les logs ou l'affichage rapide.

```php
$opts = new MakeFileOptions([ 'file' => '/var/log/app.log' ]) ;
echo "Création de : $opts" ;
// → Création de : /var/log/app.log
```

### Pourquoi un DTO plutôt qu'un tableau ?

Le tableau d'options reste **le mode par défaut** pour `makeFile`. Le DTO `MakeFileOptions` est intéressant pour :

- **Réutiliser une configuration** entre plusieurs appels (clone + override partiel) ;
- **Passer la config à travers les couches** d'une application (services, controllers, commands) sans perdre le typage ;
- **Sérialiser/désérialiser** la config (JSON pour API, persistance) ;
- **Formater des chemins dynamiquement** via `format()` :

```php
$opts = new MakeFileOptions([
    'file'    => '/logs/{{component}}/{{date}}.log' ,
    'content' => '...' ,
]) ;
$opts->file = $opts->format( $opts->file ) ;
// Ou avec formatFromDocument depuis un contexte runtime
```

### Liens

- [Création](../files/creation.md#makefile) — la fonction qui consomme cette option.
- [`MakeFileOption`](../enums.md) — l'énumération de constantes équivalente.

---

## `OwnershipInfos`

```php
namespace oihana\files\options ;

class OwnershipInfos extends Options
{
    public ?string $owner = null ;  // ex. 'www-data'
    public ?string $group = null ;  // ex. 'www-data'
    public ?int    $uid   = null ;  // ex. 33
    public ?int    $gid   = null ;  // ex. 33

    public function equalsTo( OwnershipInfos $other ): bool ;
    public function __toString(): string ; // "owner:group (uid:gid)"
}
```

### Rôle

DTO retourné par [`getOwnershipInfos`](../files/system.md#getownershipinfos). Sert à représenter **l'identité POSIX** (propriétaire + groupe) d'un fichier ou dossier sous une forme typée et comparable.

### Champs

| Champ   | Type     | Source |
|---------|----------|--------|
| `owner` | `?string`| `posix_getpwuid($uid)['name']` — `null` si `ext-posix` indisponible. |
| `group` | `?string`| `posix_getgrgid($gid)['name']` — `null` si `ext-posix` indisponible. |
| `uid`   | `?int`   | `fileowner($path)` — toujours disponible. |
| `gid`   | `?int`   | `filegroup($path)` — toujours disponible. |

> 💡 Sur Windows (sans `ext-posix`), seuls `uid` / `gid` sont renseignés — `owner` / `group` restent `null`. Les valeurs UID/GID y sont aussi moins significatives qu'en POSIX, mais cohérentes (toujours `0` ou les valeurs émulées par le runtime).

### Usage

```php
use function oihana\files\getOwnershipInfos;

$info = getOwnershipInfos( '/var/www/html' ) ;

echo $info->owner ;   // 'www-data'
echo $info->group ;   // 'www-data'
echo $info->uid ;     // 33
echo $info->gid ;     // 33
echo $info ;          // 'www-data:www-data (33:33)'
```

### `equalsTo( OwnershipInfos $other ) : bool`

**Comparaison stricte** des 4 champs (`uid`, `gid`, `owner`, `group`).

```php
$expected = new OwnershipInfos([
    'owner' => 'www-data' ,
    'group' => 'www-data' ,
    'uid'   => 33 ,
    'gid'   => 33 ,
]) ;

$actual = getOwnershipInfos( '/var/www/html' ) ;

if ( !$actual->equalsTo( $expected ) ) {
    throw new \RuntimeException( "Ownership mismatch on /var/www/html: got $actual" ) ;
}
```

### `__toString()` : format `owner:group (uid:gid)`

Affichage lisible pour logs, debug, messages d'erreur. Les `null` deviennent `'?'` pour rester non-ambigu.

```php
echo new OwnershipInfos([ 'uid' => 1000 , 'gid' => 1000 ]) ;
// → '?:? (1000:1000)'  (sans posix)

echo new OwnershipInfos([ 'owner' => 'alice' , 'group' => 'devs' , 'uid' => 1000 , 'gid' => 100 ]) ;
// → 'alice:devs (1000:100)'
```

### Cas d'usage : audit de permissions

```php
use function oihana\files\getOwnershipInfos;
use oihana\files\options\OwnershipInfos;

$expected = new OwnershipInfos([ 'owner' => 'www-data' , 'group' => 'www-data' ]) ;

$paths = [
    '/var/www/html' ,
    '/var/www/html/uploads' ,
    '/var/www/html/cache' ,
] ;

foreach ( $paths as $path ) {
    $actual = getOwnershipInfos( $path ) ;
    if ( !$actual->equalsTo( $expected ) ) {
        echo "[WARN] $path : owner mismatch, got $actual" , PHP_EOL ;
    }
}
```

### Cas d'usage : sérialisation en réponse API

```php
$info = getOwnershipInfos( $path ) ;

return new JsonResponse( $info ) ;
// → {"owner":"www-data","group":"www-data","uid":33,"gid":33}
//   (via jsonSerialize() hérité de Options)
```

### Liens

- [Système](../files/system.md#getownershipinfos) — la fonction `getOwnershipInfos` qui produit cet objet.
- [`OwnershipInfo`](../enums.md) (singulier) — l'énumération de clés associée.

---

## Comparaison globale

| | `MakeFileOptions` | `OwnershipInfos` |
|---|---|---|
| Étend | `Options` | `Options` |
| Rôle | **Input** : config passée à une fonction. | **Output** : données retournées par une fonction. |
| Mutable ? | Oui — on construit progressivement. | En théorie oui, mais utilisé en lecture-seule en pratique. |
| Méthode propre | (aucune) | `equalsTo()` |
| `__toString()` | retourne le chemin du fichier. | retourne `owner:group (uid:gid)`. |
| Énumération de clés équivalente | `MakeFileOption` (dans `enums/`) | `OwnershipInfo` (dans `enums/`) |

Ces deux exemples illustrent **deux usages canoniques** du pattern `Options` : **passer une config** (`Make*Options`) et **représenter un résultat structuré** (`*Infos`). Tous les `Options` du codebase oihana s'inscrivent dans l'un ou l'autre.

## Voir aussi

- [Pattern Options](README.md) — la classe abstraite `Options` et ses méthodes.
- [Création](../files/creation.md#makefile) — usage de `MakeFileOptions`.
- [Système](../files/system.md#getownershipinfos) — usage de `OwnershipInfos`.
- [Énumérations](../enums.md) — `MakeFileOption`, `OwnershipInfo`.
- [Sommaire FR](../README.md).
