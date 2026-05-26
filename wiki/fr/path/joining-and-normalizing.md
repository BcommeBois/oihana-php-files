# Jointure et normalisation

Quatre fonctions pour assembler et nettoyer un chemin sans toucher au système de fichiers.

- [`joinPaths`](#joinpaths) — concatène plusieurs fragments en un chemin canonique.
- [`normalizePath`](#normalizepath) — remplace `\` par `/`.
- [`canonicalizePath`](#canonicalizepath) — forme canonique (résout `.`, `..`, `~`, slashes, scheme).
- [`extractCanonicalParts`](#extractcanonicalparts) — primitive bas niveau utilisée par les deux précédentes.

---

## `joinPaths`

```php
joinPaths( string ...$paths ) : string
```

Concatène un nombre arbitraire de fragments en **un seul chemin canonique**.

**Règles :**

1. Les fragments vides (`''`) sont ignorés.
2. Le **premier** fragment non-vide est conservé tel quel (slash de tête, lettre de lecteur, scheme préservés).
3. Les fragments suivants sont joints avec **exactement un** `/`. Pas de double `//` même si le fragment précédent finit par `/` ou `\`.
4. Le résultat passe par [`canonicalizePath`](#canonicalizepath) à la fin (`.` et `..` résolus, slashes uniformisés).
5. Si tous les fragments sont vides → chaîne vide.

```php
use function oihana\files\path\joinPaths;

joinPaths( '/var', 'log', 'app.log' );
// → '/var/log/app.log'

joinPaths( 'C:\\', 'Temp', '..', 'Logs' );
// → 'C:/Logs'

joinPaths( 'phar://archive.phar', '/sub', '/file.php' );
// → 'phar://archive.phar/sub/file.php'

joinPaths( '', 'relative', 'path' );
// → 'relative/path'
```

> 💡 À privilégier systématiquement à `$a . '/' . $b` ou `$a . DIRECTORY_SEPARATOR . $b` : pas de risque de double slash, pas de surprise sur les schemes, résultat toujours canonique.

---

## `normalizePath`

```php
normalizePath( string $path ) : string
```

**Uniquement** : remplace `\` par `/`. Ne résout pas les `.`/`..`, ne canonicalise pas, ne touche pas au filesystem.

```php
use function oihana\files\path\normalizePath;

normalizePath( 'C:\\Users\\myuser\\Documents' );
// → 'C:/Users/myuser/Documents'

normalizePath( '/var/www/html' );
// → '/var/www/html' (inchangé)
```

**À utiliser quand :** tu veux juste unifier les séparateurs (par exemple avant un `explode('/')`), sans payer le coût de la canonicalisation complète.

**À ne pas confondre avec :** `realpath()` natif PHP, qui résout aussi les symlinks et vérifie l'existence — voir le [glossaire](../getting-started/glossary.md#canonical-path).

---

## `canonicalizePath`

```php
canonicalizePath( string $path ) : string
```

Convertit un chemin en sa **forme canonique absolute-style** : slashes uniformes, segments `.` et `..` résolus, scheme préservé, `~` étendu au home directory.

**Algorithme :**

1. **Cache** : lookup dans le buffer LRU statique ([`CanonicalizeBuffer`](../enums.md#canonicalizebuffer)).
2. **Expansion du `~`** : remplacé par le home (`getHomeDirectory()`).
3. **Séparateurs** : `\` → `/` (via `normalizePath`).
4. **Split racine / reste** (via `splitPath`).
5. **Cleanup `.` / `..`** (via `extractCanonicalParts`).
6. **Stockage du résultat** dans le buffer ; nettoyage LRU périodique.

**Aucun accès filesystem.** Les chemins inexistants sont acceptés.

```php
use function oihana\files\path\canonicalizePath;

canonicalizePath( '~/projects/../site//index.php' );
// → '/home/alice/site/index.php' (Linux, utilisateur alice)

canonicalizePath( 'C:\\Temp\\..\\Logs\\.' );
// → 'C:/Logs'

canonicalizePath( 'phar:///app/bundle.phar/src/../config' );
// → 'phar:///app/bundle.phar/config'
```

> ⚠ **Différence avec `realpath()`** : `realpath` **résout les symlinks** et **renvoie `false`** si le chemin n'existe pas. `canonicalizePath` est purement textuel — il fonctionne sur n'importe quelle chaîne.

---

## `extractCanonicalParts`

```php
extractCanonicalParts( string $root , string $pathWithoutRoot ) : array
```

Primitive bas-niveau utilisée par `canonicalizePath`. Découpe `$pathWithoutRoot` en segments et **élimine/résout** :

- les segments vides ;
- les `.` (dossier courant) ;
- les `..` (dossier parent) — résolus en `pop` du dernier segment quand `$root` n'est pas vide.

**Si `$root` est vide** (chemin relatif), les `..` de tête sont préservés (`['..', '..', 'folder']`).

```php
use function oihana\files\path\extractCanonicalParts;

extractCanonicalParts( '/var/www', 'project/../cache/./logs' );
// → ['cache', 'logs']

extractCanonicalParts( '', '../../folder' );
// → ['..', '..', 'folder']
```

**Quand l'utiliser directement ?** Rarement. Préfère `canonicalizePath` qui orchestre l'ensemble. À connaître uniquement si tu écris du code qui interagit avec `splitPath` au niveau du moteur.

---

## Voir aussi

- [Absolu vs relatif](absolute-vs-relative.md) — conversions et détections.
- [Inspection](inspection.md) — `splitPath` (utilisé en interne par `canonicalizePath`).
- [Vue d'ensemble du namespace](README.md) — retour à l'index.
- Glossaire : [Canonical path](../getting-started/glossary.md#canonical-path), [Scheme](../getting-started/glossary.md#scheme).
