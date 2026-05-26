# Extraire une archive tar

Cinq fonctions pour extraire et valider des archives tar.

- [`untar`](#untar) — extraction (avec `dryRun`, `keepPermissions`, `overwrite`).
- [`assertTar`](#asserttar) — validation combinée (extension + MIME + structure).
- [`hasTarExtension`](#hastarextension) — check rapide par extension.
- [`hasTarMimeType`](#hastarmimetype) — check par MIME type via `finfo`.
- [`validateTarStructure`](#validatetarstructure) — parse + itération des premières entrées.

---

## `untar`

```php
untar(
    string $tarFile ,
    string $outputPath ,
    array  $options = []
) : true|array
```

Extrait une archive tar (compressée ou non) dans un dossier de sortie.

### Options

| Clé (string ou enum) | Type | Défaut | Effet |
|---|---|---|---|
| `'dryRun'` / `TarOption::DRY_RUN` | `bool` | `false` | N'extrait rien — retourne **la liste des chemins relatifs** qui seraient extraits. |
| `'overwrite'` / `TarOption::OVERWRITE` | `bool` | `true` | Si `false`, lève `RuntimeException` au premier fichier déjà existant. |
| `'keepPermissions'` / `TarOption::KEEP_PERMISSIONS` | `bool` | `false` | Restaure les permissions originales via [`preservePharFilePermissions`](../phar/README.md). |
| `'maxExtractedSize'` / `TarOption::MAX_EXTRACTED_SIZE` | `int\|null` | `null` | Si défini, plafonne la taille décompressée totale (en octets). Lève `RuntimeException` **avant** toute écriture si dépassement. Voir [Protection contre les bombes de décompression](#protection-contre-les-bombes-de-décompression). |

### Retour

- `true` si extraction réussie ;
- `string[]` (chemins relatifs) en mode `dryRun`.

### Exceptions

- **`FileException`** : `$tarFile` invalide ou inaccessible (via `assertTar`).
- **`DirectoryException`** : impossible de créer `$outputPath`.
- **`RuntimeException`** :
  - **path traversal détecté** dans une entrée (`..` dans le chemin) ;
  - tentative d'écrasement avec `overwrite: false` ;
  - **taille décompressée totale supérieure à `maxExtractedSize`** (bombe de décompression) ;
  - autre erreur durant l'extraction.

### Pipeline interne

1. `assertTar( $tarFile )` — validation.
2. `makeDirectory( $outputPath )` — création du dossier de sortie si nécessaire.
3. Si l'archive est compressée → `decompress()` vers un `.tar` temporaire.
4. Si `overwrite: false` OU `dryRun: true` OU `maxExtractedSize !== null` → parcours d'abord pour détecter les `..`, les conflits **et accumuler la taille décompressée**.
5. Si `dryRun: true` → retourne la liste.
6. Sinon → `extractTo( $outputPath )`.
7. Si `keepPermissions` → restauration des perms.
8. Nettoyage du `.tar` temporaire si décompression effectuée.

### Protection path-traversal

`untar` parcourt les noms d'entrées de l'archive **avant** extraction et lève une `RuntimeException` si une entrée contient `..` :

```php
// Archive forgée avec entrée "../../etc/passwd"
untar( '/uploads/malicious.tar' , '/var/www/uploads' ) ;
// → RuntimeException: Path traversal attempt detected in tar file: ../../etc/passwd
```

⚠ **Cette protection s'active uniquement si `overwrite: false` OU `dryRun: true`** — c'est-à-dire dans la phase de pré-scan. En mode `overwrite: true` (défaut), elle dépend du comportement de `PharData::extractTo` (qui en théorie applique aussi des protections, mais c'est moins explicite).

> 💡 **Recommandation sécurité** : pour extraire une archive d'origine externe (upload utilisateur, téléchargement), **toujours** utiliser `dryRun: true` d'abord pour valider, puis extraire avec `overwrite: false`. Coût : double parcours, mais sécurité maximale.

### Protection contre les bombes de décompression

Une **bombe de décompression** est une archive de quelques kilooctets qui se décompresse en plusieurs gigaoctets — elle peut saturer l'espace disque, la RAM, ou provoquer un déni de service. L'option `maxExtractedSize` plafonne la taille décompressée totale acceptée :

```php
use function oihana\files\archive\tar\untar;
use oihana\files\enums\TarOption;

// Refuse toute archive dont les fichiers cumulent plus de 100 Mio.
untar( $uploadedArchive , $extractDir , [
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 ,
]) ;
// → RuntimeException: untar() aborted: extracted size exceeds maximum 104857600 bytes (potential decompression bomb).
```

**Comment ça marche.** Quand `maxExtractedSize` est défini, `untar()` force un **pré-scan** de l'archive avant toute écriture, accumule la taille décompressée de chaque entrée et lève une `RuntimeException` dès que le cumul dépasse la limite. **Aucun fichier n'est écrit** dans `$outputPath` lorsque la limite est franchie.

**Points-clés.**

- Le check est **opt-in** : `null` (défaut) conserve le comportement historique sans limite — **backward compatible**.
- Le seuil s'applique à la **somme** des tailles décompressées des entrées de l'archive (pas par fichier).
- Activer cette option déclenche un parcours supplémentaire de l'archive — léger surcoût mais protection systématique.
- La protection fonctionne en mode `dryRun` aussi (lever d'exception au pré-scan).
- Combinable avec `overwrite: false` et avec la protection path-traversal — les checks s'enchaînent dans la même passe.

> 💡 **Recommandation** : pour toute archive d'origine externe (upload, téléchargement), définir une valeur raisonnable en fonction du quota disque / RAM acceptable côté serveur (typiquement quelques centaines de Mio).

### Exemples

```php
use function oihana\files\archive\tar\untar;
use oihana\files\enums\TarOption;

// 1. Extraction basique
untar( '/path/to/archive.tar' , '/output/dir' ) ;

// 2. Avec options
untar( '/path/to/archive.tar.gz' , '/output/dir' , [
    TarOption::OVERWRITE        => false ,
    TarOption::KEEP_PERMISSIONS => true ,
]) ;

// 3. Dry-run : preview du contenu sans extraire
$files = untar( '/path/to/archive.tar' , '/output/dir' , [
    TarOption::DRY_RUN => true ,
]) ;
print_r( $files ) ;
// ['file1.txt', 'subdir/file2.php', ...]

// 4. Workflow sécurisé pour upload utilisateur
$preview = untar( $uploadedArchive , $extractDir , [
    TarOption::DRY_RUN           => true ,
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // refuse > 100 Mio
]) ;

if ( count( $preview ) > 10_000 ) {
    throw new \RuntimeException( "Archive trop grosse" ) ;
}

untar( $uploadedArchive , $extractDir , [
    TarOption::OVERWRITE          => false ,             // refuse d'écraser un fichier existant
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // re-vérifie au pré-scan
]) ;
```

---

## `assertTar`

```php
assertTar( string $filePath , bool $strictMode = false ) : bool
```

**⚠ Attention au nom trompeur** : contrairement aux autres `assert*` du namespace `oihana\files`, cette fonction **retourne un `bool`** et ne lève pas systématiquement.

**Lève `FileException`** uniquement si le fichier n'existe pas (via `assertFile`).

### Logique de validation

1. **`hasTarExtension`** — extension reconnue ? Sinon → `false`.
2. **`hasTarMimeType`** — MIME tar ? Sinon → `false`.
3. **Mode strict** (`$strictMode: true`) — `validateTarStructure` (parse PharData + itération de 10 entrées max).

```php
use function oihana\files\archive\tar\assertTar;

// Validation rapide (extension + MIME)
assertTar( '/archives/sample.tar' ) ;
// → true

// Validation profonde (avec parse PharData)
assertTar( '/archives/sample.tar' , strictMode: true ) ;
// → true si structurellement valide

// Fichier inexistant
assertTar( '/path/missing.tar' ) ;
// → FileException
```

> 💡 Pour un check rapide sans lecture du fichier, préférer [`hasTarExtension`](#hastarextension) ou [`tarIsCompressed`](tar.md#tariscompressed).

---

## `hasTarExtension`

```php
hasTarExtension(
    string $filePath ,
    array  $tarExtensions = [
        FileExtension::TAR ,      // '.tar'
        FileExtension::TGZ ,      // '.tgz'
        FileExtension::GZ ,       // '.gz'
        FileExtension::TAR_GZ ,   // '.tar.gz'
        FileExtension::TAR_BZ2 ,  // '.tar.bz2'
        FileExtension::BZ2 ,      // '.bz2'
    ]
) : bool
```

Check **rapide et purement textuel** : reconnaît les extensions simples (`.tar`, `.gz`) et **composées** (`.tar.gz`, `.tar.bz2`).

```php
use function oihana\files\archive\tar\hasTarExtension;

hasTarExtension( '/path/archive.tar'      ) ; // true
hasTarExtension( '/path/archive.tar.gz'   ) ; // true
hasTarExtension( '/path/archive.tgz'      ) ; // true
hasTarExtension( '/path/archive.tar.bz2'  ) ; // true
hasTarExtension( '/path/archive.zip'      ) ; // false
hasTarExtension( '/path/README.md'        ) ; // false
```

**Liste custom** :

```php
hasTarExtension( '/path/file.dat' , [ '.dat' , '.bin' ] ) ;
// → true (réutilise la même mécanique d'extensions simples + composées)
```

> ⚠ Les extensions seules `.gz` et `.bz2` sont incluses par défaut, ce qui peut surprendre — `file.gz` (non-tar) est reconnu. Ajuste la liste si tu veux strictement les tars.

---

## `hasTarMimeType`

```php
hasTarMimeType(
    string $filePath ,
    array  $mimeTypes = [
        'application/x-tar' ,
        'application/tar' ,
        'application/gzip' ,
        'application/x-gzip' ,
        'application/x-bzip2' ,
        'application/bzip2' ,
        'application/x-compressed-tar' ,
    ]
) : bool
```

Check **par MIME type** via `finfo` (analyse des premiers octets du fichier).

**Match si le MIME détecté contient** une des chaînes de la liste (`str_contains`) — accepte les MIME avec `; charset=...`.

```php
use function oihana\files\archive\tar\hasTarMimeType;

hasTarMimeType( '/archives/file.tar.gz' ) ;
// → true (MIME: application/gzip)

hasTarMimeType( '/archives/file.tar' ) ;
// → true (MIME: application/x-tar)

hasTarMimeType( '/archives/missing.tar' ) ;
// → false (fichier inexistant)

// Liste custom
hasTarMimeType( '/path/file.tar' , [ 'application/x-tar' , 'application/x-custom-tar' ] ) ;
```

> 💡 Plus fiable que `hasTarExtension` pour les fichiers renommés malicieusement, mais plus lent (lit le fichier).

---

## `validateTarStructure`

```php
validateTarStructure( string $filePath ) : bool
```

Vérifie la **structure interne** d'un tar **non compressé** en tentant de l'ouvrir via `PharData` et d'itérer sur **les 10 premières entrées max** (limite de perf).

```php
use function oihana\files\archive\tar\validateTarStructure;

validateTarStructure( '/path/to/archive.tar'     ) ; // true ou false
validateTarStructure( '/path/to/invalid.tar'     ) ; // false (parse error)
validateTarStructure( '/path/to/archive.tar.gz'  ) ; // false ⚠ NON SUPPORTÉ
validateTarStructure( '/path/to/not_a_tar.txt'   ) ; // false
validateTarStructure( '/nonexistent/file.tar'    ) ; // false (fichier absent)
```

### ⚠ Limitations

- **Ne supporte pas les tars compressés** (`.tar.gz`, `.tar.bz2`) — `PharData` requiert un `.tar` brut pour cette opération. `assertTar` strict-mode appelle cette fonction **après** avoir éventuellement décompressé.
- **Tronquée à 10 entrées** — un tar valide aux 10 premières entrées mais corrompu plus loin renvoie quand même `true`. Compromis perf/fiabilité.

### Choix de fonction de validation : matrice

| Niveau | Vitesse | Fonction | Vérifie |
|---|---|---|---|
| 1 (très rapide) | µs | `tarIsCompressed` | Extension seulement (`.tar.gz`, etc.) |
| 2 (rapide) | µs | `hasTarExtension` | Extension reconnue (tar/tgz/gz/bz2). |
| 3 (modéré) | ms | `hasTarMimeType` | MIME via `finfo` (lecture du début du fichier). |
| 4 (lent) | ms+ | `validateTarStructure` | Parse PharData + itération (non-compressé). |
| 5 (combiné) | ms+ | `assertTar` (strict) | 1 + 2 + 3 + 4. |

---

## Voir aussi

- [Créer une archive](tar.md) — `tar`, `tarDirectory`, `tarFileInfo`, `tarIsCompressed`.
- [Vue d'ensemble](README.md).
- [Énumérations](../enums.md) — `TarExtension`, `TarOption`, `FileExtension`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Phar](../phar/README.md) — `preservePharFilePermissions` utilisée par `keepPermissions`.
