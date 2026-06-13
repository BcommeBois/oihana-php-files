# Intégrité

Deux fonctions pour vérifier l'**intégrité** et l'**égalité** de fichiers (checksums, déduplication, vérification d'extraction d'archive).

- [`fileChecksum`](#filechecksum) — calcule le hash du contenu d'un fichier.
- [`filesAreEqual`](#filesareequal) — teste si deux fichiers ont un contenu identique.

---

## `fileChecksum`

```php
fileChecksum( string $file , string $algorithm = 'sha256' ) : string
```

Calcule le hash du contenu d'un fichier via `hash_file()` — le fichier n'est **pas** chargé entièrement en mémoire. Utile pour les contrôles d'intégrité, la déduplication et la vérification des archives extraites.

- La source est validée par [`assertFile`](assertions.md#assertfile) (doit exister et être lisible).
- `$algorithm` doit faire partie de `hash_algos()` (sinon `FileException`), ce qui évite le `ValueError` brut de PHP.
- **Retourne** le hash en hexadécimal minuscule.

| Cas | Exception |
|---|---|
| Fichier absent / illisible | `FileException` (via `assertFile`) |
| Algorithme non supporté | `FileException` |

```php
use function oihana\files\fileChecksum;

$sha256 = fileChecksum( '/data/report.pdf' ) ;         // sha256 par défaut
$md5    = fileChecksum( '/data/report.pdf' , 'md5' ) ; // algorithme explicite
```

---

## `filesAreEqual`

```php
filesAreEqual( string $a , string $b , string $algorithm = 'sha256' ) : bool
```

Indique si deux fichiers ont un **contenu identique**. La comparaison est court-circuitée pour la performance :

1. si les deux chemins pointent vers le **même fichier** sur le disque → retourne `true` sans lecture ;
2. si les **tailles diffèrent** → retourne `false` sans hashage ;
3. sinon → comparaison par [`fileChecksum`](#filechecksum).

**Lève `FileException`** si l'un des fichiers est absent/illisible ou si l'algorithme n'est pas supporté.

```php
use function oihana\files\filesAreEqual;

if ( filesAreEqual( '/data/a.bin' , '/backup/a.bin' ) )
{
    // contenus identiques — déduplication sûre
}
```

> 💡 **Vérification d'extraction** : après [`unzip`](../archive/unzip.md) ou [`untar`](../archive/untar.md), `filesAreEqual` confirme qu'un fichier extrait correspond bien à son original.

---

## Voir aussi

- [Assertions](assertions.md#assertfile) — `assertFile`, utilisée en amont.
- [Archive — unzip](../archive/unzip.md) / [untar](../archive/untar.md) — vérification post-extraction.
- [Exceptions](../exceptions.md) — `FileException`.
- [Vue d'ensemble](README.md).
