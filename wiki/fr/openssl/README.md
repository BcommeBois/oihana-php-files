# OpenSSL — `oihana\files\openssl`

Le module **`oihana\files\openssl`** expose une classe et des **helpers fonctionnels** pour **chiffrer et déchiffrer des fichiers** via OpenSSL :

- [`OpenSSLFileEncryption`](#openssl­fileencryption) — la classe principale.
- [`deriveKey()`](#derivekey) / [`bestAvailableKdf()`](#bestavailablekdf) — dérivation de clé.
- [`isAeadCipher()`](#isaeadcipher) — détection des modes AEAD.
- [`EncryptionFormat`](#encryptionformat) — constantes du format de fichier V2.

> 💡 Requiert l'extension PHP **`ext-openssl`** (activée par défaut). L'extension `ext-sodium` est utilisée si disponible pour bénéficier d'**Argon2id** (sinon fallback automatique sur PBKDF2-SHA256).

## Propriétés de sécurité

> 📖 Voir aussi la [rubrique sécurité globale](../security.md) pour le périmètre de sécurité de toute la library.

### Ce que la library garantit (pour les fichiers écrits par `encrypt()`)

| Propriété | Comment c'est obtenu |
|---|---|
| **Confidentialité** | AES-256-GCM. Sans la passphrase, le clair est computationnellement irrécupérable. |
| **Intégrité / authenticité** | Tag d'authentification GCM (16 bytes). Toute altération du fichier chiffré fait échouer `decrypt()` avec une `RuntimeException` au lieu de retourner du clair corrompu. |
| **Résistance au brute-force sur la passphrase** | KDF Argon2id (avec `ext-sodium`) ou PBKDF2-SHA256 600 000 itérations. Sel aléatoire **par fichier** → pas de rainbow tables possibles. |
| **Unicité par fichier** | Sel (16B) + IV (12B) régénérés à chaque `encrypt()`. Deux chiffrements du même contenu produisent deux fichiers chiffrés **indépendants**. |

### Ce que la library **ne** garantit **pas**

- **Pas de forward secrecy** : pas de clé éphémère par session. Quiconque obtient la passphrase peut déchiffrer **tous** les fichiers passés.
- **Pas de révocation de clé** : changer la passphrase n'invalide pas les anciens fichiers — il faut tous les rechiffrer.
- **Pas de protection contre un endpoint compromis** : si un attaquant lit la RAM PHP pendant l'usage, il récupère la passphrase. Le `__destruct()` est best-effort.
- **Pas de protection contre une passphrase faible** : le KDF ralentit le brute-force, il ne l'empêche pas. Utiliser des passphrases longues et aléatoires pour les données sensibles.

## Format de fichier V2

Tous les fichiers écrits par `encrypt()` utilisent le format **V2**.

```
┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────────────┐
│ MAGIC   │ VERSION │ KDF     │ SALT     │ IV       │ ciphertext + TAG     │
│ 4 bytes │ 1 byte  │ 1 byte  │ 16 bytes │ 12 bytes │ variable + 16 bytes  │
└─────────┴─────────┴─────────┴──────────┴──────────┴──────────────────────┘
```

| Champ | Taille | Rôle |
|---|---|---|
| `MAGIC` | 4 bytes | `"OPHE"` — identifie un fichier de la library. |
| `VERSION` | 1 byte | `0x02` — version du format. |
| `KDF` | 1 byte | `0x01` = Argon2id, `0x02` = PBKDF2-SHA256. |
| `SALT` | 16 bytes | Sel aléatoire, utilisé par le KDF pour dériver la clé. |
| `IV` | 12 bytes | IV aléatoire pour AES-GCM (12 bytes = recommandation NIST). |
| `ciphertext + TAG` | variable + 16 bytes | Ciphertext suivi du tag d'authentification GCM. |

L'octet KDF est stocké pour qu'un autre environnement (ex. PHP sans `ext-sodium`) sache quelle KDF utiliser pour déchiffrer. Si un fichier a été chiffré avec Argon2id et qu'on essaie de le lire sur un environnement sans `ext-sodium`, la library lèvera une `RuntimeException` claire.

## Compatibilité ascendante (legacy V1)

Les fichiers écrits par les versions ≤ 1.0 utilisent un **format V1 sans magic header** : `IV (16B) + ciphertext AES-CBC`, passphrase utilisée directement comme clé, **aucune vérification d'intégrité**.

`decrypt()` **détecte automatiquement** l'absence du magic `OPHE\x02` et lit les fichiers V1 avec l'ancienne logique (CBC, passphrase brute). Tes anciens fichiers restent lisibles.

**`encrypt()` produit toujours du V2.** Plus aucun fichier V1 n'est créé.

### Migrer un fichier V1 vers V2

Pas de migration forcée. Pour rechiffrer un ancien fichier au format moderne :

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->decrypt( '/old/file.enc' , '/tmp/plain.txt' ) ;   // lit V1 (auto-détecté)
$crypto->encrypt( '/tmp/plain.txt' , '/new/file.enc' ) ;   // écrit V2
deleteFile( '/tmp/plain.txt' ) ;                            // nettoyage
```

## `OpenSSLFileEncryption`

### Constructeur

```php
public function __construct(
    string $passphrase ,
    string $cipher = EncryptionFormat::LEGACY_CIPHER  // 'aes-256-cbc'
)
```

- `$passphrase` — secret utilisé pour dériver la clé. Doit être non-vide.
- `$cipher` — ne sert qu'au **déchiffrement des fichiers legacy V1**. Pour V2, AES-256-GCM est toujours utilisé. Défaut historique préservé.

**Lève `InvalidArgumentException`** si :
- la passphrase est vide ;
- le cipher demandé n'est pas dans `openssl_get_cipher_methods()` ;
- AES-256-GCM (cipher V2) n'est pas dispo dans le build OpenSSL local (rare en 2026).

**Destructeur** : tente `sodium_memzero($this->passphrase)` si dispo, sinon overwrite-string (best-effort, voir limitations).

### Propriété publique : `ivLength`

```php
public int $ivLength ;
```

Longueur d'IV du **cipher legacy** (16 pour CBC). Conservée pour compat — les helpers de détection `hasEncryptedFileSize` / `isEncryptedFile` l'utilisent pour les fichiers V1.

### `encrypt()` — chiffrement V2

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Pipeline interne :

1. `random_bytes(16)` → salt ;
2. `random_bytes(12)` → IV ;
3. KDF (Argon2id ou PBKDF2 selon dispo) → clé 32 bytes ;
4. `openssl_encrypt(..., 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16)` ;
5. Écriture : `MAGIC | VERSION | KDF | salt | IV | ciphertext | tag`.

```php
$crypto = new OpenSSLFileEncryption( 'my-passphrase' ) ;
$encrypted = $crypto->encrypt( '/path/to/file.txt' ) ;
// → '/path/to/file.txt.enc' (format V2)
```

### `decrypt()` — déchiffrement V2 ou legacy V1

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

**Détection automatique :**

- Si les 5 premiers bytes sont `OPHE\x02` → chemin V2 (lit sel, IV, ciphertext, tag ; KDF identifiée par l'octet 6 ; vérifie le tag).
- Sinon → chemin legacy V1 (IV + ciphertext CBC, passphrase brute).

**Message d'erreur uniforme** quel que soit le format :

```
RuntimeException: Decryption failed due to incorrect passphrase or corrupted data.
```

→ Pas d'oracle entre "mauvaise passphrase" et "fichier altéré" — c'est volontaire.

### `hasEncryptedFileSize()` / `isEncryptedFile()`

Inchangées dans leur API, améliorées en interne :

- `hasEncryptedFileSize()` utilise désormais `filesize()` (au lieu de charger tout le fichier).
- `isEncryptedFile()` détecte le magic V2 en fast path + vérifie la taille minimale viable, fallback heuristique pour V1.

---

## Helpers fonctionnels

### `deriveKey()`

```php
deriveKey(
    string $passphrase ,
    string $salt ,
    int    $algorithm  // EncryptionFormat::KDF_ARGON2ID ou KDF_PBKDF2_SHA256
) : string
```

Dérive une clé 32 bytes via Argon2id ou PBKDF2-SHA256 (au choix explicite).

**Conditions de sécurité couvertes :**
- Salt obligatoire de 16 bytes (sinon `RuntimeException`).
- Passphrase non-vide.
- Argon2id : paramètres `INTERACTIVE` (≈ 64 Mo, ≈ 350 ms sur CPU moderne).
- PBKDF2 : 600 000 itérations SHA-256 (recommandation OWASP 2023+).

**Conditions non couvertes :**
- Si tu réutilises le même sel sur plusieurs fichiers → rainbow table possible. Toujours `random_bytes(16)`.

**À utiliser directement** pour les cas avancés (chiffrement custom hors fichier). Sinon `OpenSSLFileEncryption` l'appelle en interne.

### `bestAvailableKdf()`

```php
bestAvailableKdf() : int
```

Renvoie `EncryptionFormat::KDF_ARGON2ID` si `ext-sodium` est chargée, sinon `KDF_PBKDF2_SHA256`. Helper pratique pour l'auto-sélection.

### `isAeadCipher()`

```php
isAeadCipher( string $cipher ) : bool
```

Renvoie `true` si le nom de cipher désigne un mode AEAD (GCM, CCM, OCB). Détection purement textuelle, case-insensitive.

```php
isAeadCipher('aes-256-gcm') ;  // true
isAeadCipher('aes-256-cbc') ;  // false
```

### `EncryptionFormat`

Classe de constantes pour le format V2 — magic, version, longueurs, identifiants KDF, cipher par défaut. Voir [enums.md](../enums.md) pour le catalogue détaillé.

---

## Cas d'usage

### Chiffrer un backup

```php
use oihana\files\openssl\OpenSSLFileEncryption;
use function oihana\files\archive\tar\tarDirectory;
use function oihana\files\deleteFile;
use oihana\files\enums\CompressionType;

$archive   = tarDirectory( '/var/www/site' , CompressionType::GZIP , '/tmp/site.tar.gz' ) ;
$crypto    = new OpenSSLFileEncryption( $secretPassphrase ) ;
$encrypted = $crypto->encrypt( $archive , '/backups/site.tar.gz.enc' ) ;
deleteFile( $archive ) ; // wipe le clair
```

### Stocker un secret applicatif

```php
$crypto = new OpenSSLFileEncryption( $masterKey ) ;
$crypto->encrypt( '/etc/myapp/credentials.json' , '/etc/myapp/credentials.json.enc' ) ;
deleteFile( '/etc/myapp/credentials.json' ) ;

// Au runtime :
$crypto = new OpenSSLFileEncryption( $masterKeyFromVault ) ;
$crypto->decrypt( '/etc/myapp/credentials.json.enc' , '/dev/shm/credentials.json' ) ; // tmpfs
$config = json_decode( file_get_contents( '/dev/shm/credentials.json' ) , true ) ;
deleteFile( '/dev/shm/credentials.json' ) ;
```

> 💡 **Stockage de la passphrase** : NE PAS la mettre en clair dans le code ou un fichier versionné. Sources recommandées : variables d'env, HashiCorp Vault, AWS Secrets Manager, fichier `0400` hors version control.

---

## ⚠ Limitations résiduelles

Le format V2 corrige les principaux trous, mais quelques zones restent **inhérentes au design** :

- **Pas de streaming** : `file_get_contents` charge tout en RAM. Pour des fichiers > RAM disponible, prévoir une approche par blocs (non fournie ici — l'API natif `openssl_encrypt_init` / `update` / `final` permet ça en pur PHP).
- **Pas de signature des métadonnées** : si tu renommes un fichier `important.enc` en `unimportant.enc`, ça déchiffre — l'authentification protège le **contenu**, pas le **nom**. Pour authentifier des métadonnées (filename, timestamps), utiliser le paramètre AAD de `openssl_encrypt` (non exposé par cette classe).
- **Destructeur best-effort** : voir explication dans la doc de `__destruct()`.

Pour ces cas, voir les [bonnes pratiques sécurité](../security.md#bonnes-pratiques-utilisateur).

## Voir aussi

- [Rubrique sécurité globale](../security.md) — périmètre de sécurité de la library.
- [Énumérations](../enums.md) — `EncryptionFormat`, `FileExtension::ENCRYPTED`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`, `RuntimeException`.
- [Assertions](../files/assertions.md) — utilisées en interne.
- Glossaire : [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-chiffrement-symétrique).
- [Sommaire FR](../README.md).
