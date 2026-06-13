# Liens symboliques

Trois fonctions pour créer, tester et lire des liens symboliques — l'angle mort signalé dans la doc des archives.

- [`createSymlink`](#createsymlink) — créer un lien (avec `overwrite`).
- [`isSymlink`](#issymlink) — tester si un chemin est un lien (`bool`, ne lève pas).
- [`readSymlink`](#readsymlink) — lire la cible d'un lien.

> 💡 Sur **Windows**, la création de symlinks requiert des privilèges particuliers. Ces fonctions ciblent les environnements POSIX (Linux, macOS).

---

## `createSymlink`

```php
createSymlink( string $target , string $link , bool $overwrite = false ) : bool
```

Crée un lien symbolique `$link` pointant vers `$target`. La cible **n'a pas besoin d'exister** — les liens pendants sont valides (comportement POSIX normal). Si une entrée existe déjà à `$link`, elle n'est remplacée que si `$overwrite` vaut `true`.

| Cas | Exception |
|---|---|
| Entrée existante avec `$overwrite = false` | `FileException` |
| Échec de création du lien | `FileException` |

```php
use function oihana\files\createSymlink;

createSymlink( '/var/www/releases/42' , '/var/www/current' , overwrite: true ) ;
```

---

## `isSymlink`

```php
isSymlink( string $path ) : bool
```

Wrapper sur `is_link()` : retourne `false` (ne lève jamais) pour un fichier régulier, un dossier ou un chemin inexistant.

```php
use function oihana\files\isSymlink;

isSymlink( '/var/www/current' ) ; // true
isSymlink( '/etc/hosts' ) ;       // false
```

---

## `readSymlink`

```php
readSymlink( string $link ) : string
```

Retourne la cible pointée par un lien symbolique. **Lève `FileException`** si `$link` n'est pas un lien.

```php
use function oihana\files\readSymlink;

echo readSymlink( '/var/www/current' ) ; // '/var/www/releases/42'
```

---

## Voir aussi

- [Création](creation.md) — `makeFile`, `touchFile`.
- [Découverte](discovery.md) — `findFiles` (option `followLinks`).
- [Exceptions](../exceptions.md) — `FileException`.
- [Vue d'ensemble](README.md).
