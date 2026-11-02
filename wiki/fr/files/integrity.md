# Intégrité

Quatre fonctions pour vérifier l'**intégrité** et l'**égalité** de fichiers (checksums, déduplication, vérification d'extraction d'archive), et pour garder une empreinte à côté de son fichier.

- [`fileChecksum`](#filechecksum) — calcule le hash du contenu d'un fichier.
- [`filesAreEqual`](#filesareequal) — teste si deux fichiers ont un contenu identique.
- [`checksumLine` et `readChecksumLine`](#checksumline-et-readchecksumline) — écrire une empreinte à côté de son fichier comme le fait `sha256sum`, et la relire.

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

## `checksumLine` et `readChecksumLine`

```php
checksumLine( string $hash , string $filename ) : string
readChecksumLine( string $content , string $filename , string $algorithm = 'sha256' ) : ?string
```

Une empreinte ne vaut que si on la garde : ces deux fonctions l'écrivent **à côté du fichier**, au format qu'écrit `sha256sum`, et la relisent avant de faire confiance au fichier.

**`checksumLine`** écrit la ligne : le hash en minuscules, deux espaces, le nom, un retour à la ligne. `sha256sum -c` (ou `md5sum -c`, `shasum -c`…) la vérifie à la main, sans PHP. Donnez le nom seul, sans dossier : la ligne se vérifie encore une fois les deux fichiers copiés ailleurs.

**`readChecksumLine`** la relit, pour un fichier :

- le hash doit avoir la longueur de celui de l'algorithme — 64 caractères pour `sha256` (le défaut, comme dans `fileChecksum`), 32 pour `md5`, 128 pour `sha512` ;
- le mode binaire de `sha256sum` (un espace et une étoile avant le nom) est lu aussi, comme les fins de ligne Windows ;
- **seule une ligne qui nomme ce fichier compte** : un fichier d'empreinte copié à côté du mauvais fichier ne s'en porte pas garant. Un fichier qui en liste plusieurs (`SHA256SUMS`) est lu ligne à ligne, et la première ligne qui nomme celui-ci est retenue ;
- **retourne** le hash en minuscules, ou `null` quand aucune ligne ne nomme le fichier avec un hash bien formé.

| Cas | Résultat |
|---|---|
| Aucune ligne ne nomme le fichier, ou son hash n'a pas la bonne longueur | `null` |
| Algorithme non supporté | `FileException` |

```php
use function oihana\files\checksumLine;
use function oihana\files\fileChecksum;
use function oihana\files\readChecksumLine;

// Quand le fichier est écrit
file_put_contents( '/data/report.pdf.sha256' , checksumLine( fileChecksum( '/data/report.pdf' ) , 'report.pdf' ) ) ;

// Avant de le lire
$recorded = readChecksumLine( file_get_contents( '/data/report.pdf.sha256' ) , 'report.pdf' ) ;

if ( $recorded === null || $recorded !== fileChecksum( '/data/report.pdf' ) )
{
    // pas d'empreinte pour ce fichier, ou le fichier a changé depuis
}
```

> ⚠️ `sha256sum` écrit autrement un nom qui contient un retour à la ligne ou une barre oblique inverse (échappé, avec une barre oblique inverse en tête de ligne). Un tel nom n'est pas pris en charge.

---

## Voir aussi

- [Assertions](assertions.md#assertfile) — `assertFile`, utilisée en amont.
- [Archive — unzip](../archive/unzip.md) / [untar](../archive/untar.md) — vérification post-extraction.
- [Exceptions](../exceptions.md) — `FileException`.
- [Vue d'ensemble](README.md).
