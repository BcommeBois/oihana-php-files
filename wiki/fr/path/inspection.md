# Inspection

Cinq fonctions pour analyser un chemin sans le modifier.

- [`splitPath`](#splitpath) — sépare *racine* / *reste*.
- [`directoryPath`](#directorypath) — extrait le dossier parent (équivalent robuste de `dirname()`).
- [`isLocalPath`](#islocalpath) — détecte les chemins distants (avec scheme `://`).
- [`isBasePath`](#isbasepath) — vérifie si un chemin est contenu dans un autre.
- [`realpathParent`](#realpathparent) — résout le parent d'un chemin, garde son dernier segment.

---

## `splitPath`

```php
splitPath( string $path ) : array  // [string $root, string $remainder]
```

Sépare un chemin **canonique** en deux parties : la **racine** (protocole / lettre de lecteur / slash de tête) et le **reste**.

**Patterns supportés :**

| Entrée                          | Racine retournée | Reste retourné         |
|---------------------------------|------------------|------------------------|
| `/var/www/html`                 | `/`              | `var/www/html`         |
| `C:/Windows/System32`           | `C:/`            | `Windows/System32`     |
| `C:`                            | `C:/`            | *(vide)*               |
| `file:///home/user/docs`        | `file:///`       | `home/user/docs`       |
| `phar:///app/bundle.phar/src`   | `phar:///`       | `app/bundle.phar/src`  |

> ⚠ L'entrée est supposée déjà **canonique** (slashes uniformes, pas de `.` / `..`). Pour un chemin brut, canonicalise-le d'abord avec [`canonicalizePath`](joining-and-normalizing.md#canonicalizepath).

```php
use function oihana\files\path\splitPath;

[ $root , $rest ] = splitPath( '/etc/nginx/nginx.conf' );
// $root = '/' , $rest = 'etc/nginx/nginx.conf'

[ $root , $rest ] = splitPath( 'C:/Program Files' );
// $root = 'C:/' , $rest = 'Program Files'

[ $root , $rest ] = splitPath( 'C:' );
// $root = 'C:/' , $rest = ''

[ $root , $rest ] = splitPath( 'file:///var/log' );
// $root = 'file:///' , $rest = 'var/log'
```

**Quand l'utiliser ?** Quand tu veux **manipuler indépendamment** la racine et le reste — par exemple pour préserver le scheme d'un `phar://` à travers une transformation custom. C'est ce que font en interne `canonicalizePath`, `makeAbsolute` et `relativePath`.

---

## `directoryPath`

```php
directoryPath( string $path ) : string
```

Équivalent **robuste et portable** de `dirname()` natif. Renvoie le dossier parent en forme canonique.

**Corrige les défauts de `dirname()` natif :**

| Entrée            | `dirname()` natif | `directoryPath()` |
|-------------------|-------------------|-------------------|
| `'C:/symfony'`    | `'C:'`            | `'C:/'`           |
| `'C:/'`           | `'.'`             | `'C:/'`           |
| `'C:'`            | `'.'`             | `'C:/'`           |
| `'symfony'`       | `'.'`             | `''`              |
| `'\\back\\slash'` | (échoue sur Unix) | `'\\back'`        |

**Gère aussi :**

- Les schemes (`file:///home/user/doc.txt` → `'/home/user'`, scheme `file://` retiré).
- Les schemes non-`file://` (`phar:///app/src/main.php` → `'phar:///app/src'`, scheme préservé).
- Les chemins UNC Windows (`\\server\share\folder` → `\\server\share`).
- La préservation du **séparateur d'entrée** : si l'entrée contient des `\`, le résultat utilise des `\`. Sinon `/`.

```php
use function oihana\files\path\directoryPath;

directoryPath( '/var/www/html/file.txt' );
// → '/var/www/html'

directoryPath( 'C:\\Windows\\System32\\file.txt' );
// → 'C:\\Windows\\System32'

directoryPath( 'D:/Program Files/My App/file.txt' );
// → 'D:/Program Files/My App'

directoryPath( 'file:///home/user/doc.txt' );
// → '/home/user'  (le scheme file:// est retiré)

directoryPath( 'phar:///app/src/main.php' );
// → 'phar:///app/src'  (le scheme phar:// est préservé)

directoryPath( 'file.txt' );  // → ''
directoryPath( '' );          // → ''
```

> 💡 **Pourquoi `file://` est-il retiré mais pas `phar://` ?** Parce que `file://` est sémantiquement équivalent à *pas de scheme* (filesystem local), tandis que `phar://` désigne un wrapper PHP distinct. Cette convention suit ce que fait la plupart des libs du monde Symfony.

---

## `isLocalPath`

```php
isLocalPath( string $path ) : bool
```

Vrai si le chemin pointe vers le **système de fichiers de la machine** (pas une URL distante).

**Détection :** simple présence de `://` dans la chaîne.

- `'/var/log'`, `'C:\\Users'`, `'./relative'` → `true`.
- `'https://example.com'`, `'s3://bucket'`, `'ftp://host'` → `false`.

**Cas limite :** `'phar://'`, `'vfs://'`, `'file://'` retournent aussi `false` (ils contiennent `://`). Si tu veux distinguer un *stream wrapper PHP local* d'une URL distante, écris ta propre vérification basée sur le scheme.

```php
use function oihana\files\path\isLocalPath;

isLocalPath( '/var/log/app.log' );     // true
isLocalPath( 'C:\\Users\\Admin' );     // true
isLocalPath( './config.ini' );         // true

isLocalPath( 'https://example.com' );  // false
isLocalPath( 's3://my-bucket/file' );  // false
isLocalPath( 'phar://x.phar' );        // false (contient ://)
isLocalPath( '' );                     // false (vide)
```

---

## `isBasePath`

```php
isBasePath( string $basePath , string $childPath ) : bool
```

Vrai si `$childPath` est **égal à** ou **contenu dans** `$basePath`.

**Algorithme :**

1. Canonicalise les deux chemins.
2. Ajoute un slash terminal à `$basePath` et compare avec `str_starts_with($childPath . '/', $basePath . '/')`.

Cette astuce du **slash terminal** évite les faux positifs comme `/var/www-legacy` considéré comme contenu dans `/var/www`.

```php
use function oihana\files\path\isBasePath;

isBasePath( '/var/www', '/var/www/site/index.php' ); // true
isBasePath( '/var/www', '/var/www' );                // true (match exact)
isBasePath( '/var/www', '/var/www-legacy' );         // false (préfixe partiel rejeté)
isBasePath( 'C:/Users', 'C:/Users/Bob/file.txt' );   // true
```

> ⚠ **Cas d'usage sécurité.** Cette fonction est l'antidote idéal aux attaques de type *path traversal* (`../../../etc/passwd`). Workflow type :
>
> ```php
> $safe   = makeAbsolute( $userInput, $allowedRoot );
> if ( !isBasePath( $allowedRoot, $safe ) ) {
>     throw new \RuntimeException("Refused: $safe escapes $allowedRoot");
> }
> ```

---

## `realpathParent`

```php
realpathParent( string $path ) : string
```

Résout le **parent** d'un chemin et laisse son **dernier segment** tel quel.

C'est la seule fonction de cette page qui interroge le disque. `realpath()` répond pour le chemin entier, ce qui est trop quand le dernier segment est précisément ce qu'on juge : un lien symbolique serait remplacé par sa cible, et un chemin inexistant répondrait `false`. Ici, tout ce qui est au-dessus du dernier segment est résolu — donc un ancêtre qui est un lien ne peut pas cacher où mène vraiment un chemin — et le dernier segment est gardé mot pour mot.

Si le parent n'existe pas, le chemin est canonicalisé lexicalement : il n'y a rien à demander au disque.

```php
use function oihana\files\path\realpathParent;

// Sur macOS, où /var est un lien vers /private/var :
realpathParent( '/var/folders/x/T' ) ; // '/private/var/folders/x/T'

// Le parent est la racine, et le résultat ne porte qu'un séparateur, pas deux :
realpathParent( '/tmp' ) ;             // '/tmp'

// Un lien est gardé pour lui-même, jamais suivi :
symlink( '/elsewhere' , '/tmp/pointer' ) ;
realpathParent( '/tmp/pointer' ) ;     // '/tmp/pointer'
```

**À quoi ça sert ?** À décider si un chemin est contenu dans un autre sans se faire tromper. [`deleteTemporaryDirectory`](../files/deletion.md#deletetemporarydirectory) l'utilise pour refuser ce qui n'est pas dans le dossier temporaire : résoudre le parent ferme l'ancêtre-lien, garder le dernier segment permet de supprimer un lien posé dans le temp sans toucher à sa cible.

---

## Voir aussi

- [Jointure et normalisation](joining-and-normalizing.md) — `canonicalizePath` (utilisée en amont).
- [Absolu vs relatif](absolute-vs-relative.md) — détection et conversion.
- [Vue d'ensemble du namespace](README.md).
- Glossaire : [Scheme](../getting-started/glossary.md#scheme), [Local (chemin)](../getting-started/glossary.md#local-chemin-local).
