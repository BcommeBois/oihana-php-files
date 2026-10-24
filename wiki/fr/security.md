# Sécurité

Cette page décrit **ce que la library garantit**, **ce qu'elle ne garantit pas**, et les **bonnes pratiques utilisateur**. C'est le contrat de sécurité de `oihana/php-files`.

## Aperçu rapide par module

| Module | Garanties de sécurité | À surveiller côté caller |
|---|---|---|
| [`openssl/`](openssl/README.md) | AES-256-GCM par blocs + KDF (Argon2id/PBKDF2) + un tag GCM par bloc liant l'en-tête, l'indice et la fin du flux ; mémoire constante | Stockage de la passphrase ; rotation manuelle ; disponibilité du KDF sur la machine qui relit |
| [`archive/tar/`](archive/README.md) | Détection path-traversal (`..`) au pré-scan, **et refus par GNU tar lui-même** dans le cas courant ; protection bombes de décompression opt-in via `TarOption::MAX_EXTRACTED_SIZE` | Toujours pre-scan en `dryRun` et plafonner `maxExtractedSize` pour les archives non fiables |
| [`path/`](path/README.md) | `isBasePath` permet l'anti path-traversal | Canonicaliser **toujours** les chemins user avant comparaison |
| [`files/`](files/README.md) | Assertions typées (`assertFile`, `assertDirectory`) | Patterns regex `findFiles`/`shouldExcludeFile` : ne pas accepter de patterns non fiables (ReDoS) |
| [`files/requireAndMergeArrays`](files/reading.md#requireandmergearrays) | Validation chemin + extension `.php` + optionnel `$allowedBase` | Toujours fournir `$allowedBase` quand les chemins ne sont pas 100% fiables |
| [`toml/`](toml/README.md) | TOML : format de données pur (pas d'exécution) | OK par construction |

## Périmètre des menaces couvertes

Cette section décrit le **modèle de menace** auquel la library a été conçue pour résister, et les attaques qu'elle ne couvre **pas**.

### ✅ Menaces couvertes

| Menace | Module | Mécanisme |
|---|---|---|
| **Path traversal** (`../../etc/passwd`) en input user | `path/`, `files/`, `archive/` | `canonicalizePath` + `isBasePath` recommandés ; détection `..` à l'extraction tar (en mode safe) |
| **Arbitrary file inclusion** (RCE via `require` sur chemin attaqué) | `requireAndMergeArrays` | Validation chemin + extension `.php` + `$allowedBase` |
| **Tampering sur fichier chiffré** | `openssl/` | Un tag GCM par bloc, échec explicite à `decrypt()` |
| **Troncature, réordonnancement, suppression de bloc, recollage** de deux fichiers — tous faisables **sans la clé** | `openssl/` | L'AAD de chaque bloc lie l'en-tête, l'indice du bloc et un drapeau « dernier bloc » |
| **Brute-force passphrase** | `openssl/` | KDF coûteuse (Argon2id/PBKDF2 600k) + sel par fichier |
| **Rainbow tables** sur passphrases courantes | `openssl/` | Sel aléatoire 16B par fichier |
| **Identification format** d'un fichier chiffré | `openssl/` | Magic header `OPHE\x03` pour V3 ; détection auto des legacy V2 et V1 ; une version inconnue est refusée au lieu d'être passée au chemin V1 sans intégrité |
| **Réutilisation de nonce** (catastrophe en GCM) | `openssl/` | Préfixe de 8 octets tiré à chaque `encrypt()`, puis un compteur de blocs borné : aucun nonce ne se répète dans un fichier, et `pack('N', …)` ne peut pas repasser à zéro sans être refusé |
| **Decompression bombs** (tar bomb) — opt-in | `archive/tar/` | `TarOption::MAX_EXTRACTED_SIZE` : pré-scan + abandon avant écriture si dépassement |
| **OOM via fichier volumineux** (lectures directes) — opt-in | `files/` | `$maxBytes` sur `getFileLines`, `requireAndMergeArrays` : check de taille avant lecture ; `$maxInputBytes` sur le constructeur `OpenSSLFileEncryption` |
| **OOM, puis OOM-kill silencieux, au chiffrement** | `openssl/` | Depuis la 1.5.0 le V3 travaille **par blocs** dans les deux sens ; la lecture des legacy V1/V2, qui ne peut pas l'être, **refuse en expliquant** quand le fichier dépasse le budget mémoire |

### ❌ Menaces **non** couvertes

| Menace | Pourquoi | Mitigation côté caller |
|---|---|---|
| **Forward secrecy** | Pas de clé éphémère par session — design symétrique simple | Si critique, utiliser un protocole asymétrique (libsodium, age) |
| **Révocation de clé** | Changer la passphrase ne ré-écrit pas les fichiers passés | Rechiffrer manuellement les fichiers sensibles après rotation |
| **Endpoint compromis** (memory dump, malware sur l'hôte) | Hors scope d'une library file-level | OS hardening, secrets management externe (Vault) |
| **Passphrase faible** | KDF ralentit le brute-force, ne l'empêche pas | Passphrases longues (≥ 16 caractères aléatoires) |
| **Side channels** (timing attacks sur OpenSSL) | Dépendant de l'implémentation OpenSSL sous-jacente | Build OpenSSL à jour, CPU avec AES-NI |
| **Métadonnées de fichier** (nom, timestamps, taille) | Le tag GCM authentifie le contenu, pas le nom | Conventions de nommage non sémantiques, signature externe |
| **Decompression bombs** par défaut | Sans `TarOption::MAX_EXTRACTED_SIZE`, aucune limite — opt-in laissé au caller | Toujours définir `TarOption::MAX_EXTRACTED_SIZE` pour les archives non fiables (cf. [tips](tips.md#bombes-de-décompression-tar)) |
| **ReDoS** sur patterns regex utilisateur | `preg_match` PHP sans timeout | Ne jamais accepter de regex depuis input user direct ; valider les patterns en amont |
| **Polyglot files** (fichier valide comme PDF et image) | `mime_content_type` lit les premiers octets | Combiner détection MIME + extension + validation métier |

## Bonnes pratiques utilisateur

### 1. Pour le chiffrement de fichiers

```php
// ✅ Bon : passphrase venant d'une source de confiance, longue, aléatoire
$passphrase = getenv('APP_ENCRYPTION_KEY') ;  // venant d'un .env hors VCS ou d'un vault
assert( strlen( $passphrase ) >= 32 ) ;

$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->encrypt( $sensitive , $encrypted ) ;
deleteFile( $sensitive ) ; // wipe le clair

// ❌ Mauvais : passphrase hardcodée
$crypto = new OpenSSLFileEncryption( 'admin' ) ;  // courte + dictionnaire
```

### 2. Pour les chemins venant d'un input externe

```php
use function oihana\files\path\{ canonicalizePath , isBasePath } ;

$base = '/var/www/uploads' ;
$userPath = canonicalizePath( $_POST[ 'path' ] ) ;

// ✅ Bon : refus si en dehors du dossier autorisé
if ( !isBasePath( $base , $userPath ) ) {
    throw new \RuntimeException( "Path traversal blocked: $userPath" ) ;
}
```

### 3. Pour les archives venant d'un upload utilisateur

```php
use function oihana\files\archive\tar\untar ;
use oihana\files\enums\TarOption;

// ✅ Bon : pre-scan en dryRun + cap taille pour bombes de décompression + traversal
$preview = untar( $uploaded , $dest , [
    TarOption::DRY_RUN            => true ,
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 , // 100 Mio max décompressé
] ) ;

if ( count( $preview ) > 10_000 ) {
    throw new \RuntimeException( "Archive trop grosse (> 10k fichiers)" ) ;
}

// Extraction safe : refuse d'écraser, replafonne la taille
untar( $uploaded , $dest , [
    TarOption::OVERWRITE          => false ,
    TarOption::MAX_EXTRACTED_SIZE => 100 * 1024 * 1024 ,
] ) ;
```

### 4. Pour le chargement dynamique de fichiers PHP

```php
use function oihana\files\{ requireAndMergeArrays , recursiveFilePaths } ;

$baseDir = __DIR__ . '/definitions' ;

// ✅ Bon : $baseDir passé comme racine autorisée
$definitions = requireAndMergeArrays(
    recursiveFilePaths( $baseDir , [ 'extensions' => [ 'php' ] ] ) ,
    true ,
    $baseDir ,  // ← defense in depth
) ;
```

### 5. ReDoS sur patterns regex utilisateur

**Fonctions concernées :** [`findFiles()`](files/discovery.md#findfiles), [`shouldExcludeFile()`](files/discovery.md#shouldexcludefile), [`copyFilteredFiles()`](files/copying.md#copyfilteredfiles).

**Qu'est-ce que ReDoS ?** Regular-Expression Denial of Service. Le moteur PHP `preg_match()` effectue du *backtracking* pour trouver une correspondance. Une regex malicieusement construite comme `/^(a+)+$/` — ou ses cousines réelles `/^(\w+)+$/`, `/^(a|a)*$/`, `/(x+x+)+y/` — combinée à une chaîne d'entrée spécifique, provoque un **catastrophic backtracking** : le moteur explore un nombre exponentiel de chemins. Résultat : un seul appel à `preg_match` consomme des secondes ou minutes de CPU.

**Pourquoi ces fonctions sont vulnérables :** elles appellent `preg_match()` avec le pattern fourni par le caller. PHP a un `pcre.backtrack_limit` (défaut 1 000 000) qui finit par sortir, mais **le moteur brûle du CPU** jusqu'à la limite. Une poignée de requêtes malveillantes peut saturer le pool de workers.

**Ce que la library ne fait PAS :**

- Pas de validation des patterns contre les constructions ReDoS (pas d'analyseur statique).
- Pas de timeout d'exécution (le moteur regex PHP ne le supporte pas nativement).
- Pas de sandbox pour l'évaluation des regex.

**Contrat caller :** ces fonctions sont conçues pour des **patterns de confiance** — fichiers de config, code interne hardcodé, listes whitelist. Elles ne sont **pas** sûres avec des patterns construits depuis user input direct.

```php
// ❌ Mauvais : pattern direct depuis $_GET → vector ReDoS
findFiles( $dir , [ 'pattern' => $_GET[ 'pattern' ] ] ) ;

// ✅ Bon : whitelist de patterns fixes
$allowed = [ '*.txt' , '*.log' , '*.json' ] ;
$pattern = in_array( $_GET[ 'pattern' ] , $allowed , true ) ? $_GET[ 'pattern' ] : '*' ;
findFiles( $dir , [ 'pattern' => $pattern ] ) ;

// ✅ Bon : construire le pattern dans le code depuis des morceaux validés
$ext = preg_quote( $validatedExtension , '/' ) ;
findFiles( $dir , [ 'pattern' => "*.$ext" ] ) ;  // glob, pas regex
```

**Options de defense-in-depth si tu dois accepter des patterns utilisateur :**

- Baisser `pcre.backtrack_limit` à ~100 000 dans `php.ini` pour les workers concernés.
- Préférer le **glob** (`fnmatch()`) à la regex quand la puissance expressive n'est pas nécessaire — le glob n'a pas de catastrophic backtracking.
- Faire tourner le matching dans un processus séparé avec un timeout dur (`pcntl_alarm`, subprocess, isolation fastcgi).
- Utiliser un linter de regex pour rejeter les patterns connus dangereux en amont.

### 6. Pour les configs sensibles (credentials, tokens)

- Chiffrer **au repos** via `OpenSSLFileEncryption` quand le fichier doit rester sur disque.
- Charger en mémoire au runtime via un dossier `tmpfs` (`/dev/shm` sur Linux) qui ne touche jamais le disque.
- Effacer immédiatement après usage (`deleteFile`).

## Historique de sécurité

| Date | Version | Changement |
|---|---|---|
| 2026-06 | 1.1.0 | `requireAndMergeArrays` : validation chemin/extension, paramètre `$allowedBase` (mitige RCE via inclusion de chemin attaqué) |
| 2026-06 | 1.1.0 | `OpenSSLFileEncryption` : refactor V2 — AES-256-GCM (AEAD), KDF (Argon2id/PBKDF2 600k), `random_bytes`, soft-break pour rétro-compat V1 |
| 2026-06 | 1.1.0 | Documentation de la surface **ReDoS** sur `findFiles` / `shouldExcludeFile` / `copyFilteredFiles` (patterns de confiance uniquement) |
| 2026-06 | 1.1.0 | `untar` : protection opt-in contre les **bombes de décompression** via `TarOption::MAX_EXTRACTED_SIZE` |
| 2026-10 | 1.5.0 | `untar` : extraction par le **GNU tar** du système quand il y en a un — qui refuse de lui-même les entrées en `..`, là où le pré-scan ne couvrait que les modes `dryRun` / `overwrite: false`. Les gardes des deux moteurs sont écrites une seule fois, et une ligne de listage illisible lève au lieu d'être ignorée |
| 2026-06 | 1.1.0 | **Bornes de taille** opt-in sur les lectures : `$maxBytes` (`getFileLines`, `requireAndMergeArrays`) et `$maxInputBytes` (`OpenSSLFileEncryption`) |
| 2026-10 | 1.5.0 | `OpenSSLFileEncryption` : **format V3 par blocs** — mémoire constante (100 Mio sous `memory_limit=48M` → pic à 30 Mio), **AAD liant l'en-tête, l'indice du bloc et le drapeau final** (troncature, réordonnancement, suppression, recollage : les quatre refusés), exposant de bloc borné à la lecture, sortie atomique par `.part` + `rename()` |
| 2026-10 | 1.5.0 | Lecture des legacy V1/V2 **gardée par le budget mémoire** : un refus explicite remplace l'OOM-kill, qui ne laissait ni exception ni trace |
| 2026-10 | 1.5.0 | Un fichier Argon2id lu sans `ext-sodium` **nomme l'extension manquante** au lieu d'annoncer « incorrect passphrase or corrupted data » sur une archive intacte |

Pour le détail technique : voir [CHANGELOG.md](../../CHANGELOG.md) section `Security`.

## Comment signaler une vulnérabilité

Si tu identifies une vulnérabilité de sécurité dans `oihana/php-files`, **ne pas ouvrir d'issue publique**. Contacter directement par email : [marc@ooop.fr](mailto:marc@ooop.fr) avec :

- Description du problème ;
- Étapes de reproduction ;
- Version concernée ;
- Suggestion de mitigation si tu en as une.

Réponse sous 7 jours, fix publié dès que possible avec attribution dans le CHANGELOG (si tu le souhaites).

## Voir aussi

- [OpenSSL — détail crypto](openssl/README.md)
- [Archive (tar)](archive/README.md) — section sécurité extraction
- [Path](path/README.md) — `isBasePath` pour l'anti path-traversal
- [requireAndMergeArrays](files/reading.md#requireandmergearrays) — validation chemins PHP
- [Tips et pièges](tips.md) — checklist plus large incluant performance
- [Sommaire FR](README.md)
