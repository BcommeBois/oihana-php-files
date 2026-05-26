# Astuces et pièges

Page vivante regroupant les **règles d'or**, les pièges récurrents et les conventions utiles à connaître. À enrichir au fil des incidents et retours d'expérience.

## Règles d'or

### 1. Toujours préférer les helpers du namespace plutôt que les fonctions natives PHP

| Au lieu de... | Utiliser... |
|---|---|
| `$a . '/' . $b` ou `$a . DIRECTORY_SEPARATOR . $b` | [`joinPaths( $a , $b )`](path/joining-and-normalizing.md#joinpaths) |
| `realpath( $path )` (résout les symlinks + valide) | [`canonicalizePath( $path )`](path/joining-and-normalizing.md#canonicalizepath) (purement textuel) |
| `dirname( $path )` (bizarreries Windows) | [`directoryPath( $path )`](path/inspection.md#directorypath) |
| `if ( !is_file( $p ) ) throw ...` | [`assertFile( $p )`](files/assertions.md#assertfile) |
| `if ( !is_dir( $p ) ) throw ...` | [`assertDirectory( $p )`](files/assertions.md#assertdirectory) |
| `glob()` sans flag, manipulations manuelles | [`findFiles( $dir , ... )`](files/discovery.md#findfiles) |
| `array_merge()` sur des configs imbriquées | `deepMerge()` (via `oihana/php-core`) ou [`requireAndMergeArrays`](files/reading.md#requireandmergearrays) |

**Pourquoi :** API homogène, exceptions typées, gestion cohérente des schemes (`phar://`, `file://`...) et des plateformes (Windows vs Unix).

### 2. Toujours canonicaliser les chemins venant de l'utilisateur

```php
use function oihana\files\path\{ canonicalizePath , isBasePath } ;

$userPath = canonicalizePath( $_GET[ 'path' ] ) ;

// Refuser tout chemin qui sort du dossier autorisé
if ( !isBasePath( '/var/www/uploads' , $userPath ) ) {
    throw new \RuntimeException( "Path traversal blocked: $userPath" ) ;
}
```

C'est l'antidote standard aux **attaques path traversal** (`../../etc/passwd`). Voir [`isBasePath`](path/inspection.md#isbasepath).

### 3. Préférer le mode `assertable: false` au try/catch silencieux

```php
// ❌ Mauvais : masque toutes les erreurs y compris celles d'I/O réelles
try { deleteFile( $maybe ) ; } catch ( FileException $e ) {}

// ✅ Bon : explicite sur l'intent ("essaie, retourne false si inutile")
deleteFile( $maybe , assertable: false ) ;
```

Disponible sur `deleteFile`, `deleteDirectory`, `clearFile`, `deleteTemporaryDirectory`, `getDirectory`, `getTemporaryDirectory`, etc.

### 4. Toujours utiliser les énumérations pour les clés d'options

```php
// ❌ Magic strings, IDE ne complète pas, typos silencieuses
makeFile( $path , $content , [
    'permissons' => 0644 , // typo non détectée → ignoré silencieusement
]) ;

// ✅ Constantes typées, refactor-friendly, IDE complète
use oihana\files\enums\MakeFileOption;
makeFile( $path , $content , [
    MakeFileOption::PERMISSIONS => 0644 ,
]) ;
```

---

## Pièges plateforme

### Windows : chemins et séparateurs

- Le module **normalise tout en `/`** en sortie (Unix-style). Si tu construis un chemin pour `exec()` sur Windows et que la commande exige `\`, fais la conversion finale toi-même.
- `directoryPath()` **préserve le séparateur d'entrée** : si tu lui passes un `\`, il renvoie un `\`. Détail à connaître.

### Windows : `ext-posix` absent

- [`getOwnershipInfos`](files/system.md#getownershipinfos) retourne `owner` et `group` à `null` sur Windows (pas d'`ext-posix`). `uid`/`gid` restent disponibles mais avec des valeurs émulées par PHP.
- Les options `'owner'` / `'group'` de `makeFile` / `makeDirectory` sont **silencieusement no-op** sur Windows (`chown` / `chgrp` natifs PHP n'ont pas d'effet).

### macOS : `Darwin` !== `Mac`

- `PHP_OS` vaut `'Darwin'` sur macOS, pas `'Mac'`. La fonction [`isMac()`](files/system.md#ismac) gère ça correctement — mais si tu fais `PHP_OS === 'Mac'` manuellement, ça ne marche pas.

### Linux : symlinks dans `findFiles`

- Par défaut, `followLinks: false`. Si tu actives `followLinks: true` en mode `recursive`, attention aux **boucles infinies** sur des symlinks circulaires (`a` → `b` → `a`).
- PHP avec `FilesystemIterator::FOLLOW_SYMLINKS` détecte les cycles via les inodes, mais le coût mémoire augmente.

---

## Pièges crypto et sécurité

### OpenSSL : limitations actuelles

Le module [`OpenSSLFileEncryption`](openssl/README.md) a plusieurs **angles d'attaque connus** :

1. **CBC sans HMAC** : pas de détection de tampering. Un attaquant peut altérer le ciphertext et le déchiffrement renvoie des données silencieusement corrompues.
2. **GCM cassé** : si tu instancies avec `cipher: 'aes-256-gcm'`, le code ne gère pas le tag — l'`encrypt` "marche" mais le `decrypt` ne valide pas l'intégrité.
3. **Pas de KDF** : la passphrase est utilisée directement comme clé. Passphrase courte = clé faible.
4. **`openssl_random_pseudo_bytes`** : déprécié au profit de `random_bytes`.

Un refactor est planifié — voir le détail dans la doc OpenSSL et le backlog interne.

> 💡 **En attendant** : utiliser pour des fichiers au repos sur disque de confiance (backups locaux), pas pour des fichiers échangés ou stockés sur un disque accessible à un attaquant potentiel.

### Tar : path traversal en mode default

[`untar`](archive/untar.md#untar) **détecte les `..`** dans les entrées d'archive, mais **seulement** si `overwrite: false` ou `dryRun: true`. En mode par défaut (`overwrite: true`), la protection dépend de `PharData::extractTo`.

**Recommandation** pour les archives d'origine externe (uploads, téléchargements) :

```php
// 1. Pre-scan (lève si traversal)
untar( $archive , $dest , [ TarOption::DRY_RUN => true ] ) ;

// 2. Extraction safe
untar( $archive , $dest , [ TarOption::OVERWRITE => false ] ) ;
```

### Tar : symlinks et `chmod`

- `tar` **sérialise les symlinks comme symlinks** (pas leur cible). À l'extraction, ils sont recréés tels quels.
- `untar` avec `keepPermissions: true` restaure le **mode** mais **pas** owner/group. Pour un backup fidèle, faire `chown` manuellement après ou utiliser `rsync` / `cp -p`.

---

## Pièges performance

### `getFileLines` vs `getFileLinesGenerator`

- `getFileLines` charge tout en RAM (`iterator_to_array`). Un fichier de 100 Mo = 100 Mo RAM.
- `getFileLinesGenerator` est **streaming** — une seule ligne en mémoire à la fois.
- À partir de quelques Mo, préférer le générateur.

### `canonicalizePath` cache LRU

Les chemins canonicalisés sont mis en cache statique dans [`CanonicalizeBuffer`](enums.md#canonicalizebuffer) jusqu'à 1250 entrées. Si ta charge inclut des **millions de chemins distincts** dans un même process, le buffer peut consommer de la mémoire avant le cleanup.

**Mitigation** : `CanonicalizeBuffer::$buffer = [] ;` pour vider manuellement.

### `findFiles` recursive + pattern regex

Le pattern est testé sur **chaque fichier** rencontré. Si tu as 100k fichiers et un pattern regex complexe, le coût est sensible. Préférer le glob (`fnmatch`) quand possible — plus rapide que `preg_match`.

### `copyFilteredFiles` sur gros volumes

`copy()` natif PHP charge le fichier en mémoire avant de l'écrire. Pour des **volumes > 1 Go** ou des milliers de fichiers, préférer `rsync` via `exec()` ou un outil dédié. `copyFilteredFiles` est idéal pour les snapshots ponctuels < ~1 Go.

---

## Pièges de typage / API

### `assertTar` retourne `bool`, pas `void`

Contrairement aux autres `assert*` du module, [`assertTar`](archive/untar.md#asserttar) renvoie un booléen au lieu de lever. **À surveiller** :

```php
// ❌ Mauvais : code mort
assertTar( $path ) ;
// La fonction renvoie false sans lever — la suite s'exécute quand même

// ✅ Bon
if ( !assertTar( $path ) ) {
    throw new FileException( "Pas un tar valide : $path" ) ;
}
```

Voir [Exceptions](exceptions.md) pour la liste exacte des fonctions qui lèvent.

### `FindFileOption` vs `FindFilesOption`

Les deux classes existent et ont **exactement les mêmes constantes**. `FindFilesOption` (pluriel) est utilisée par [`findFiles`](files/discovery.md#findfiles). `FindFileOption` (singulier) n'est référencée nulle part en interne — possible doublon historique à clarifier.

→ **Toujours utiliser `FindFilesOption`** par convention.

### `MakeFileOption` (enum de clés) vs `MakeFileOptions` (DTO)

- `oihana\files\enums\MakeFileOption` — **constantes string** pour les clés d'un tableau (`['append' => true]`).
- `oihana\files\options\MakeFileOptions` — **classe DTO** typée étendant [`Options`](options/README.md).

Même piège pour `OwnershipInfo` (singulier, enum) vs `OwnershipInfos` (pluriel, DTO). Voir [Énumérations](enums.md#conventions-de-nommage).

### Pas de hiérarchie d'exceptions

Les 3 exceptions (`FileException`, `DirectoryException`, `UnsupportedCompressionException`) héritent **directement de `\Exception`** — pas de parent commun pour les attraper en bloc autrement que via `\Exception` ou `\Throwable`.

Si tu veux un catch ciblé "tout sauf le reste" :

```php
catch ( FileException | DirectoryException | UnsupportedCompressionException $e ) {
    // ...
}
```

Voir [Exceptions](exceptions.md#hiérarchie-absente--à-savoir).

---

## Conventions internes

### Les dépendances `oihana/*` sont en `dev-main`

`oihana/php-core`, `oihana/php-reflect`, `oihana/php-enums` sont versionnés sur `dev-main` (pas `^1.0`). Conséquence : un `composer update` peut amener des changements transitifs. Si tu déploies en production, **pin les versions** dans ton `composer.lock` ou attends une stabilisation.

### Pas de subprocess dans le module

Aucune fonction du module ne fait `exec()` ou `shell_exec()`. **Tout est pur PHP**. Ça rend le code portable et testable, mais ça impose des limites sur les très gros volumes (voir [Performance](#pièges-performance)).

### Tests internes via `vfsStream`

Le module est testé extensivement avec [`mikey179/vfsstream`](https://github.com/bovigo/vfsStream) qui simule un système de fichiers en mémoire. Conséquence pratique : **les tests passent en CI sans I/O réel**, et tu peux toi aussi tester tes consommateurs du module sans toucher au disque.

```php
use org\bovigo\vfs\vfsStream;

$root = vfsStream::setup( 'myapp' ) ;
vfsStream::create([ 'config.toml' => 'debug = true' ] , $root ) ;

$config = resolveTomlConfig( vfsStream::url( 'myapp/config.toml' ) ) ;
```

---

## Voir aussi

- [Introduction](getting-started/introduction.md) — la philosophie qui sous-tend ces conventions.
- [Exceptions](exceptions.md) — détail des 3 exceptions.
- [Énumérations](enums.md) — catalogue complet.
- [Sommaire FR](README.md).
