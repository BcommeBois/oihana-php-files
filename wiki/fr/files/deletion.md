# Suppression

Quatre fonctions pour vider, supprimer, et nettoyer fichiers et dossiers.

- [`deleteFile`](#deletefile) — supprime un fichier.
- [`deleteDirectory`](#deletedirectory) — supprime un dossier récursivement (avec son contenu).
- [`clearFile`](#clearfile) — vide un fichier sans le supprimer.
- [`deleteTemporaryDirectory`](#deletetemporarydirectory) — supprime un dossier dans `sys_get_temp_dir()` (sécurisé).

> ⚠ **Toutes ces opérations sont destructives.** Privilégier le pattern `assertable` (cf. [Assertions](assertions.md)) pour valider l'état attendu avant de supprimer.

---

## `deleteFile`

```php
deleteFile(
    string $filePath ,
    bool   $assertable = true ,
    bool   $isReadable = true ,
    bool   $isWritable = true
) : bool
```

Supprime un fichier via `unlink()`.

**Pré-validation par défaut** (`assertable: true`) : appelle [`assertFile`](assertions.md#assertfile) pour garantir que le fichier existe et est lisible+inscriptible. Désactivable.

**Lève `FileException`** si :
- les assertions échouent (en mode `assertable`) ;
- `unlink` échoue (permissions, fichier ouvert sur Windows, etc.).

### Usage

```php
use function oihana\files\deleteFile;

// Mode strict : valide d'abord, lève une exception si problème
deleteFile( '/tmp/output.txt' ) ;

// Mode permissif : ne valide pas, mais lève une exception si unlink échoue
deleteFile( '/tmp/maybe.txt' , assertable: false ) ;
```

> 💡 Le mode `assertable: false` reste **pas silencieux** — si `unlink` échoue, l'exception est levée. Pour un *vraiment silencieux*, encadrer d'un `try/catch`.

---

## `deleteDirectory`

```php
deleteDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = true
) : bool
```

Supprime un dossier **récursivement** (fichiers + sous-dossiers).

**Parcours :** utilise `RecursiveIteratorIterator` en mode `CHILD_FIRST` — les fichiers et dossiers feuilles sont supprimés avant leurs parents. Permet de supprimer une arborescence en un seul appel.

**Le paramètre `$path` accepte :**

- `string` : chemin direct (`'/tmp/old-cache'`).
- `array` : segments à joindre (`['/tmp', 'old-cache']` → `/tmp/old-cache`).
- `null` : équivalent à `sys_get_temp_dir()` via `getDirectory()`.

### Usage

```php
use function oihana\files\deleteDirectory;

// Suppression directe
deleteDirectory( '/tmp/build-artifacts' ) ;
// → true (l'arborescence entière a disparu)

// Avec segments
deleteDirectory( [ '/tmp' , 'cache' , 'images' ] ) ;
// → /tmp/cache/images supprimé

// Mode permissif (le dossier peut ne pas exister)
deleteDirectory( '/tmp/maybe' , assertable: false ) ;
```

### Exceptions

- **`DirectoryException`** : assertions, échec `rmdir`/`unlink` sur un fichier/dossier intermédiaire.

### ⚠ Pièges

- **Liens symboliques** : `unlink` les supprime (pas leur cible). `rmdir` ne supprime que les *dossiers réels*, pas les symlinks pointant vers un dossier.
- **Permissions** : si tu n'as pas les droits sur un fichier interne, la suppression s'arrête au milieu — le dossier est dans un état partiellement supprimé.
- **Windows + fichier ouvert** : `unlink` échoue tant qu'un handle est ouvert. Fermer tous les handles d'abord.

---

## `clearFile`

```php
clearFile(
    ?string $file ,
    bool    $assertable = true
) : bool
```

**Vide** un fichier (le tronque à 0 byte) **sans le supprimer**. Utile pour :
- vider un fichier de log sans devoir le recréer (les processus qui le tiennent ouvert continuent à pouvoir y écrire) ;
- réinitialiser un fichier de cache.

**Implémentation :** `file_put_contents($file, '')`.

### Modes

| Mode                | Comportement face à un fichier manquant ou non-inscriptible |
|---------------------|---|
| `assertable: true`  (défaut) | Lève `FileException` via `assertFile`. |
| `assertable: false` | Retourne `false` silencieusement. |

### Usage

```php
use function oihana\files\clearFile;

// Strict : lève FileException si problème
clearFile( '/var/log/myapp.log' ) ;
// → true

// Permissif : retourne false si fichier manquant
$ok = clearFile( '/var/log/maybe.log' , assertable: false ) ;
if ( !$ok ) {
    // Loguer ou ignorer
}
```

> 💡 **Quand `clearFile` vs `deleteFile` + `makeFile` ?**
> - `clearFile` : préserve l'inode → les processus avec un handle ouvert continuent.
> - `deleteFile` + `makeFile` : nouvel inode → les *open handles* écrivent dans le néant (problème courant avec syslog/logrotate). Utiliser `copytruncate` côté logrotate ou `clearFile` côté app.

---

## `deleteTemporaryDirectory`

```php
deleteTemporaryDirectory(
    string|array|null $path ,
    bool $assertable = true ,
    bool $isReadable = true ,
    bool $isWritable = true
) : bool
```

Supprime un dossier **à l'intérieur de `sys_get_temp_dir()`**. Wrapper sécurisé autour de `deleteDirectory`.

### Garde-fous de sécurité

Pour éviter d'effacer accidentellement quelque chose hors du temp :

1. **`null` ou chemin vide** → retourne `false`, pas d'erreur.
2. **Tentative de supprimer le temp dir lui-même** → retourne `false` (via comparaison `realpath()`).
3. **Le dossier n'existe pas** → retourne `true` (rien à faire, succès idempotent).
4. **Sinon** → délègue à `deleteDirectory`.

### Usage

```php
use function oihana\files\deleteTemporaryDirectory;

// Supprime /tmp/old_reports et son contenu
deleteTemporaryDirectory( 'old_reports' ) ;

// Avec segments
deleteTemporaryDirectory( [ 'tmp123' , 'cache' , 'images' ] ) ;

// Idempotent : ne lève pas d'exception si déjà absent
deleteTemporaryDirectory( 'maybe' ) ;

// Bloqué : impossible d'effacer le temp dir lui-même
deleteTemporaryDirectory( null ) ;
// → false (refus, pas d'exception)
```

> 💡 **Le pattern recommandé** : utiliser `makeTemporaryDirectory` pour créer + `deleteTemporaryDirectory` pour nettoyer. Voir [temporary.md](temporary.md) pour le workflow complet.

---

## Tableau récapitulatif

| Fonction                     | Cible            | Validation par défaut | Mode permissif | Idempotent |
|------------------------------|------------------|-----------------------|----------------|------------|
| `deleteFile`                 | Fichier          | `assertFile`          | `assertable: false` | Non |
| `deleteDirectory`            | Dossier + contenu| `assertDirectory`     | `assertable: false` | Non |
| `clearFile`                  | Fichier (contenu)| `assertFile`          | `assertable: false` | Oui (tronquer un fichier vide ne fait rien) |
| `deleteTemporaryDirectory`   | Dossier dans temp| Indirecte (via `deleteDirectory`) | Garde-fous internes | Oui |

---

## Voir aussi

- [Assertions](assertions.md) — `assertFile`, `assertDirectory` (utilisés en amont par toutes ces fonctions).
- [Création](creation.md) — fonctions miroirs.
- [Répertoires temporaires](temporary.md) — workflow complet.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Vue d'ensemble](README.md).
