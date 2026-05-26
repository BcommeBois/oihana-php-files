# Répertoires temporaires — workflow

Trois fonctions qui forment ensemble un workflow propre pour travailler dans le **dossier temporaire système** (`sys_get_temp_dir()` → `/tmp` sur Unix, `C:\Windows\Temp` sur Windows) :

- [`getTemporaryDirectory`](#gettemporarydirectory) — calcule le chemin sans rien créer.
- [`makeTemporaryDirectory`](#maketemporarydirectory) — crée le dossier (ou retourne le chemin s'il existe déjà).
- [`deleteTemporaryDirectory`](#deletetemporarydirectory) — supprime le dossier (avec garde-fous).

> 💡 Cette page documente le **workflow** et les détails de `getTemporaryDirectory`. Les deux autres fonctions sont aussi listées dans [creation.md](creation.md#maketemporarydirectory) et [deletion.md](deletion.md#deletetemporarydirectory) — la doc canonique est ici.

---

## Pourquoi ce trio ?

L'API PHP native pour le temp est minimaliste :

- `sys_get_temp_dir()` → renvoie le chemin du dossier temp.
- `tempnam()` → crée un fichier au nom unique dans un dossier.

Mais aucune fonction native ne :

- accepte un **sous-chemin** structuré (`['my', 'app', 'cache']`) ;
- combine **calcul + création** en un seul appel ;
- protège contre la **suppression accidentelle** du temp dir lui-même.

`oihana/php-files` comble ce gap avec un trio cohérent.

---

## `getTemporaryDirectory`

```php
getTemporaryDirectory(
    string|array|null $path       = null ,
    bool              $assertable = false ,
    bool              $isReadable = true ,
    bool              $isWritable = false
) : string
```

**Calcule** le chemin d'un sous-dossier dans le temp système. **Ne crée rien**.

### Logique de résolution du paramètre `$path`

| `$path`                | Résultat sur Unix                | Résultat sur Windows           |
|------------------------|----------------------------------|--------------------------------|
| `null` ou `''`         | `/tmp`                           | `C:\Windows\Temp`              |
| `'cache'`              | `/tmp/cache`                     | `C:\Windows\Temp\cache`        |
| `['my', 'app']`        | `/tmp/my/app`                    | `C:\Windows\Temp\my\app`       |
| `'/var/tmp/myapp'` (Unix absolu) | `/var/tmp/myapp` (tel quel) | n/a |
| `'C:\Temp\custom'` (Windows absolu) | n/a | `C:\Temp\custom` (tel quel) |

> 💡 **Chemin absolu = bypass.** Si tu passes un chemin déjà absolu (Unix avec `/`, Windows avec `C:\`), il est retourné tel quel — `sys_get_temp_dir()` est ignoré. Pratique pour rediriger vers un temp custom (`/var/tmp` au lieu de `/tmp`).

### Options de validation

| Paramètre        | Effet quand `true` |
|------------------|---|
| `$assertable`    | Valide le dossier via `assertDirectory` (existe + accessible). |
| `$isReadable`    | Active la vérification de lecture (uniquement si `$assertable: true`). |
| `$isWritable`    | Active la vérification d'écriture (uniquement si `$assertable: true`). |

**Lève `DirectoryException`** uniquement si `$assertable: true` et le dossier ne passe pas les vérifications.

### Exemples

```php
use function oihana\files\getTemporaryDirectory;

// 1. Récupérer le temp dir lui-même
echo getTemporaryDirectory() ;
// → /tmp

// 2. Construire un sous-chemin
echo getTemporaryDirectory( 'myapp/cache' ) ;
// → /tmp/myapp/cache  (n'existe peut-être pas — pas vérifié)

// 3. Avec segments
echo getTemporaryDirectory( [ 'myapp' , 'logs' , 'errors' ] ) ;
// → /tmp/myapp/logs/errors

// 4. Avec validation : lève si /tmp/uploads n'existe pas
$dir = getTemporaryDirectory( 'uploads' , assertable: true , isWritable: true ) ;

// 5. Bypass sys_get_temp_dir
echo getTemporaryDirectory( '/var/tmp/myapp' ) ;
// → /var/tmp/myapp (tel quel)
```

---

## Workflow type 1 : dossier de travail jetable

```php
use function oihana\files\{ makeTemporaryDirectory , deleteTemporaryDirectory } ;
use function oihana\files\path\joinPaths ;

$workDir = makeTemporaryDirectory( [ 'myapp' , 'process-' . uniqid() ] ) ;
// → /tmp/myapp/process-6f8c1a4b

try {
    // Travailler dans $workDir
    file_put_contents( joinPaths( $workDir , 'data.json' ) , json_encode( $payload ) ) ;
    runHeavyProcess( $workDir ) ;
}
finally {
    // Nettoyage garanti même en cas d'exception
    deleteTemporaryDirectory( [ 'myapp' , basename( $workDir ) ] ) ;
}
```

> 💡 Le `try/finally` garantit que le dossier est nettoyé même si `runHeavyProcess` lève une exception.

---

## Workflow type 2 : sous-dossiers permanents par environnement

Pour un cache ou un pool de fichiers qui survit à plusieurs requêtes/sessions :

```php
use function oihana\files\{ makeTemporaryDirectory , deleteTemporaryDirectory } ;

// Au boot de l'application
$cacheDir = makeTemporaryDirectory( [ 'myapp' , 'cache' , 'v2' ] , 0700 ) ;

// Utilisation au fil des requêtes
file_put_contents( $cacheDir . '/key.dat' , $value ) ;

// Au déploiement de v3 : nettoyer v2
deleteTemporaryDirectory( [ 'myapp' , 'cache' , 'v2' ] ) ;
```

> 💡 Le mode `0700` (propriétaire uniquement) est recommandé si le cache contient des données sensibles, sinon n'importe quel utilisateur de la machine peut le lire dans `/tmp`.

---

## `makeTemporaryDirectory`

Voir [creation.md#maketemporarydirectory](creation.md#maketemporarydirectory) — la signature est rappelée ici pour référence rapide :

```php
makeTemporaryDirectory(
    string|array|null $path ,
    int               $permission = 0755
) : string
```

Internement : appelle `getTemporaryDirectory($path)` puis `mkdir($dir, $permission, recursive: true)` si nécessaire.

---

## `deleteTemporaryDirectory`

Voir [deletion.md#deletetemporarydirectory](deletion.md#deletetemporarydirectory). Garde-fous récapitulés :

1. `null` ou `''` → `false` (refus).
2. Tentative de supprimer le temp dir lui-même → `false` (refus, comparaison `realpath`).
3. Dossier inexistant → `true` (idempotent).
4. Sinon → délègue à `deleteDirectory`.

```php
deleteTemporaryDirectory( null ) ;        // false (refus)
deleteTemporaryDirectory( '' ) ;          // false (refus)
deleteTemporaryDirectory( 'myapp' ) ;     // /tmp/myapp supprimé (ou true si absent)
```

---

## Bonnes pratiques

- **Toujours utiliser un préfixe d'application** (`['myapp', ...]`) pour ne pas polluer `/tmp` avec des dossiers anonymes.
- **Cleanup en `finally`** pour les workflows transactionnels.
- **Permissions `0700`** si le contenu est sensible (par défaut `0755` est *world-readable*).
- **Ne pas dépendre de la persistance** : le système peut nettoyer `/tmp` au reboot (sur la plupart des Linux), ou via `tmpfiles.d`.
- **Ne pas stocker de chemins absolus** vers `/tmp/...` en base de données — pas portable entre machines.

## Voir aussi

- [Création](creation.md) — autres fonctions de création.
- [Suppression](deletion.md) — `deleteDirectory` (utilisée en interne par `deleteTemporaryDirectory`).
- [Système](system.md) — `getDirectory`, `getHomeDirectory`.
- Glossaire : [Temporaire](../getting-started/glossary.md#temporaire-fichierdossier).
- [Vue d'ensemble](README.md).
