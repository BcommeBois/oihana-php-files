# Absolu vs relatif

Six fonctions pour détecter et convertir entre chemins absolus et relatifs.

- **Détection** : [`isAbsolutePath`](#isabsolutepath), [`isRelativePath`](#isrelativepath).
- **Conversion** : [`makeAbsolute`](#makeabsolute), [`makeRelative`](#makerelative), [`computeRelativePath`](#computerelativepath), [`relativePath`](#relativepath).

> 💡 Voir le [glossaire](../getting-started/glossary.md#absolu-chemin) pour la définition formelle d'un chemin absolu (commence par `/`, lettre de lecteur Windows, ou scheme `phar://`/`file://`).

---

## `isAbsolutePath`

```php
isAbsolutePath( string $path ) : bool
```

Vrai si `$path` est absolu (Unix, Windows, UNC, ou scheme URL).

**Reconnaît :**

- Chemins Unix : `/var/www`, `/`.
- Chemins Windows avec lettre de lecteur : `C:\Users\Test`, `D:/folder`, `C:` (cas spécial), `C:/`.
- Chemins UNC Windows : `\\server\share\folder`.
- Chemins avec scheme : `file:///c/Users/`, `phar:///app/bundle.phar`.

**Cas particuliers :**

- `''` → `false` (vide).
- `'C:'` → `true` (drive letter seule).
- `'documents/report.pdf'` → `false`.
- `'../images/pic.jpg'` → `false`.

```php
use function oihana\files\path\isAbsolutePath;

isAbsolutePath( '/var/www' );           // true
isAbsolutePath( 'C:\\Users\\Test' );    // true
isAbsolutePath( 'D:/folder/file.txt' ); // true
isAbsolutePath( '\\\\server\\share' );  // true (UNC)
isAbsolutePath( 'file:///c/Users/' );   // true

isAbsolutePath( 'documents/x.pdf' );    // false
isAbsolutePath( '' );                   // false
```

---

## `isRelativePath`

```php
isRelativePath( string $path ) : bool
```

Inverse exact de `isAbsolutePath`. Vrai si **non** absolu.

```php
use function oihana\files\path\isRelativePath;

isRelativePath( 'documents/report.pdf' ); // true
isRelativePath( '../images/pic.jpg' );    // true
isRelativePath( '' );                     // true (vide est considéré relatif)

isRelativePath( '/var/www' );             // false
isRelativePath( 'C:\\Users' );            // false
```

> ⚠ Une chaîne **vide** est considérée *relative* par cette fonction (cohérent avec `!isAbsolutePath('')`).

---

## `makeAbsolute`

```php
makeAbsolute( string $path , string $basePath ) : string
```

Transforme `$path` en chemin **absolu canonique** en le joignant à `$basePath` (absolu obligatoirement).

**Comportement :**

- Si `$path` est **déjà absolu** → simplement canonicalisé (`$basePath` ignoré).
- Si `$path` est relatif → joint à `$basePath`, puis canonicalisé.
- Le scheme de `$basePath` (`phar://`, etc.) est **préservé** dans le résultat.

**Lève `InvalidArgumentException`** si :
- `$basePath` est vide ;
- `$basePath` n'est pas absolu.

```php
use function oihana\files\path\makeAbsolute;

makeAbsolute( 'documents/../project/file.txt', '/home/user' );
// → '/home/user/project/file.txt'

makeAbsolute( 'data\\.\\config.ini', 'C:\\Users\\Test' );
// → 'C:/Users/Test/data/config.ini'

// Path déjà absolu : basePath ignoré
makeAbsolute( '/etc/app.conf', '/var/www' );
// → '/etc/app.conf'

// Scheme préservé
makeAbsolute( 'src/bootstrap.php', 'phar:///usr/local/bin/composer.phar' );
// → 'phar:///usr/local/bin/composer.phar/src/bootstrap.php'
```

---

## `makeRelative`

```php
makeRelative( string $path , string $basePath ) : string
```

Transforme un chemin **absolu** en chemin **relatif** par rapport à un autre chemin absolu.

**Préconditions :** les deux chemins doivent être absolus et partager la même racine (même lettre de lecteur, même scheme).

**Lève `InvalidArgumentException`** si :
- un des deux chemins n'est pas absolu ;
- les racines diffèrent (ex. `C:/` vs `D:/`, ou `phar://` vs `/`).

```php
use function oihana\files\path\makeRelative;

// Sous-dossier
makeRelative( '/var/www/project/app', '/var/www/project' );
// → 'app'

// Vers un dossier voisin
makeRelative( '/var/www/assets', '/var/www/project/app' );
// → '../../assets'

// Identiques
makeRelative( '/var/www', '/var/www' );
// → ''  (chaîne vide)

// Depuis la racine
makeRelative( '/home/user/documents', '/' );
// → 'home/user/documents'

// Windows
makeRelative( 'C:/Users/Test/Documents', 'C:/Users/Test/Downloads' );
// → '../Documents'

// Phar
makeRelative( 'phar:///app/src/controller', 'phar:///app/src/model' );
// → '../controller'
```

---

## `computeRelativePath`

```php
computeRelativePath( string $targetPath , string $basePath ) : string
```

Calcule la **relativité entre deux chemins déjà normalisés** (typiquement, deux chemins relatifs ou les *parties post-racine* déjà extraites).

**Différence avec `relativePath`** : ne canonicalise pas, ne valide pas les types, suppose que tu lui passes des chaînes propres. Primitive bas niveau.

```php
use function oihana\files\path\computeRelativePath;

computeRelativePath( 'foo/bar/baz', 'foo'     ); // 'bar/baz'
computeRelativePath( 'foo/baz',     'foo/bar' ); // '../baz'
computeRelativePath( 'foo/bar',     'foo/bar' ); // '.'
computeRelativePath( 'a/b',         'a/b/c/d' ); // '../../'
computeRelativePath( 'a/b/c',       'a'       ); // 'b/c'
computeRelativePath( 'a/x/y',       'a/b/c'   ); // '../../x/y'
```

**Quand l'utiliser directement ?** Dans des cas où tu travailles déjà sur des chemins relatifs propres et veux éviter le coût d'une canonicalisation. Sinon, `relativePath` est plus sûr.

---

## `relativePath`

```php
relativePath( string $path , string $basePath ) : string
```

Version **publique et robuste** : canonicalise d'abord, valide la cohérence des racines, gère les schemes, puis appelle `computeRelativePath` en interne.

**Préconditions :**
- Les deux chemins doivent être du même type (les deux absolus OU les deux relatifs).
- Si absolus : même racine (lettre de lecteur ou scheme identique).

**Lève `InvalidArgumentException`** si la combinaison est invalide (un absolu + un relatif, ou racines différentes).

```php
use function oihana\files\path\relativePath;

// Absolus
relativePath( '/foo/bar/baz', '/foo'     ); // 'bar/baz'
relativePath( '/foo/baz',     '/foo/bar' ); // '../baz'
relativePath( '/foo/bar',     '/foo/bar' ); // '.'
relativePath( '/a/b',         '/a/b/c/d' ); // '../../'

// Relatifs
relativePath( 'foo/bar/baz', 'foo'     ); // 'bar/baz'
relativePath( 'foo/baz',     'foo/bar' ); // '../baz'
```

> 💡 **Quand choisir `makeRelative` vs `relativePath` ?**
> - `makeRelative` : strictement absolu → absolu. Plus restrictif, message d'erreur plus précis.
> - `relativePath` : accepte aussi relatif → relatif. Plus flexible.

---

## Voir aussi

- [Jointure et normalisation](joining-and-normalizing.md) — `joinPaths`, `canonicalizePath` (utilisée en amont).
- [Inspection](inspection.md) — `splitPath`, `isBasePath`.
- [Vue d'ensemble du namespace](README.md).
- Glossaire : [Absolu](../getting-started/glossary.md#absolu-chemin), [Relatif](../getting-started/glossary.md#relatif-chemin).
