# OpenSSL — `oihana\files\openssl`

Le module **`oihana\files\openssl`** expose une seule classe — [`OpenSSLFileEncryption`](#openssl­fileencryption) — pour **chiffrer et déchiffrer des fichiers** via OpenSSL, avec gestion automatique de l'**IV (Initialization Vector)**.

> 💡 Requiert l'extension PHP **`ext-openssl`** (activée par défaut). Voir [Installation](../getting-started/installation.md) pour la vérification.

## Principe en une phrase

Un objet `OpenSSLFileEncryption($passphrase, $cipher)` produit des fichiers chiffrés **auto-portants** : l'IV est préfixé dans le fichier de sortie, donc le déchiffrement ne demande que la même passphrase et le même cipher — pas besoin de stocker l'IV séparément.

```
┌────────────────────────────────────────────┐
│  IV (16 bytes pour AES)  │  données chiffrées │
└────────────────────────────────────────────┘
```

## Cipher par défaut

**`aes-256-cbc`** (Advanced Encryption Standard, clé 256 bits, mode Cipher Block Chaining). Recommandation cryptographique solide pour un usage général.

Tout algorithme listé par `openssl_get_cipher_methods()` est accepté — le constructeur valide via `in_array`.

## `OpenSSLFileEncryption`

### Constructeur

```php
public function __construct(
    string $passphrase ,
    string $cipher = 'aes-256-cbc'
)
```

**Lève `InvalidArgumentException`** si :
- le cipher demandé n'est pas dans `openssl_get_cipher_methods()` ;
- la passphrase est vide.

**Destructeur sécurisé** : la passphrase est **effacée de la mémoire** (`str_repeat("\0", ...)`) à la destruction de l'objet — bonne pratique pour limiter l'exposition mémoire.

```php
use oihana\files\openssl\OpenSSLFileEncryption;

$crypto = new OpenSSLFileEncryption( 'mon-secret-passphrase' ) ;

// Cipher custom
$crypto = new OpenSSLFileEncryption( $passphrase , 'aes-128-gcm' ) ;

// Cipher inconnu
$crypto = new OpenSSLFileEncryption( $passphrase , 'foo' ) ;
// → InvalidArgumentException: Cipher method 'foo' is not available
```

### Propriété publique : `ivLength`

```php
public int $ivLength ;
```

Longueur en bytes de l'IV pour le cipher choisi (déterminée via `openssl_cipher_iv_length`). En lecture seule (property hook PHP 8.4).

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ; // aes-256-cbc
echo $crypto->ivLength ; // 16 (pour AES en CBC)
```

---

### `encrypt`

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Chiffre un fichier. Retourne le chemin du fichier de sortie.

**Étapes :**

1. `assertFile` sur l'input.
2. Détermination du chemin de sortie : `$outputFile` si fourni, sinon `$inputFile . '.enc'` (extension via `FileExtension::ENCRYPTED`).
3. Lecture du contenu.
4. **Génération d'un IV sécurisé** via `openssl_random_pseudo_bytes($this->ivLength, $cryptoStrong)`. Si `$cryptoStrong` est `false` → `RuntimeException` (source d'aléa non cryptographiquement forte).
5. `openssl_encrypt` en mode `OPENSSL_RAW_DATA`.
6. Validation : dossier de sortie inscriptible (`assertDirectory(..., isWritable: true)`), fichier de sortie inscriptible s'il existe.
7. Écriture : **IV concaténé en tête + données chiffrées**.

**Exceptions :**

| Exception | Cas |
|---|---|
| `FileException` | Input invalide (via `assertFile`). |
| `DirectoryException` | Dossier de sortie non inscriptible. |
| `RuntimeException` | Échec lecture, IV non crypto-strong, échec encrypt, échec écriture. |

```php
use oihana\files\openssl\OpenSSLFileEncryption;

$crypto = new OpenSSLFileEncryption( 'secret' ) ;

// Sortie auto : ajoute .enc
$encrypted = $crypto->encrypt( '/path/to/file.txt' ) ;
// → '/path/to/file.txt.enc'

// Sortie custom
$encrypted = $crypto->encrypt( '/path/to/file.txt' , '/secure/file.enc' ) ;
// → '/secure/file.enc'
```

---

### `decrypt`

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

Déchiffre un fichier précédemment chiffré.

**Étapes :**

1. `assertFile` sur l'input.
2. Détermination du chemin de sortie : `$outputFile` si fourni, sinon `$inputFile` avec `.enc` retiré (`str_replace`).
3. Lecture.
4. Vérification de taille : le fichier doit faire au moins `$ivLength` bytes (`RuntimeException` sinon).
5. **Extraction de l'IV** depuis les `$ivLength` premiers bytes, **données chiffrées** depuis le reste.
6. `openssl_decrypt`. Échec → `RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.")` (pas de distinction entre passphrase incorrecte et corruption — comportement classique pour ne pas leaker l'info).
7. Validation : dossier de sortie existant + inscriptible, fichier inscriptible s'il existe.
8. Écriture des données déchiffrées.

```php
$crypto = new OpenSSLFileEncryption( 'secret' ) ;

// Sortie auto : retire .enc
$decrypted = $crypto->decrypt( '/path/to/file.txt.enc' ) ;
// → '/path/to/file.txt'

// Sortie custom
$decrypted = $crypto->decrypt( '/path/to/file.txt.enc' , '/restored/file.txt' ) ;
```

---

### `hasEncryptedFileSize`

```php
public function hasEncryptedFileSize( string $filePath ) : bool
```

**Test rapide de taille** : vrai si le fichier existe ET a au moins `$ivLength` bytes. **Ne valide pas le contenu**, juste la taille minimale nécessaire pour contenir un IV.

```php
if ( $crypto->hasEncryptedFileSize( '/path/to/file' ) ) {
    echo "Taille compatible avec un fichier chiffré." ;
}
// Pas une certitude — juste un prérequis nécessaire.
```

---

### `isEncryptedFile`

```php
public function isEncryptedFile( string $filePath ) : bool
```

**Heuristique** plus avancée pour deviner si un fichier ressemble à un fichier chiffré par cette classe.

**Trois critères :**

1. **Taille** ≥ `$ivLength` (idem `hasEncryptedFileSize`).
2. **L'IV (premiers bytes) ne contient pas que des `\0`** — un IV nul indique probablement un fichier non chiffré ou corrompu.
3. **L'IV ne contient pas trop de caractères ASCII imprimables** (> 80% des bytes en range 32-126) — indique probablement du texte en clair plutôt qu'un IV aléatoire.

```php
if ( $crypto->isEncryptedFile( '/path/to/file' ) ) {
    echo "Probablement chiffré." ;
}
```

> ⚠ **C'est une heuristique, pas une preuve.** Un fichier binaire non-chiffré (image JPEG par exemple) commence par des bytes non-imprimables et passera ce test. Et inversement, certains chiffrements peuvent produire des IVs accidentellement majoritairement imprimables. À combiner avec d'autres signaux (extension `.enc`, contexte applicatif).

---

## Cas d'usage : chiffrer un fichier de backup

```php
use oihana\files\openssl\OpenSSLFileEncryption;
use function oihana\files\archive\tar\tarDirectory;
use function oihana\files\deleteFile;
use oihana\files\enums\CompressionType;

// 1. Créer un tar.gz du site
$archive = tarDirectory( '/var/www/site' , CompressionType::GZIP , '/tmp/site.tar.gz' ) ;

// 2. Chiffrer
$crypto    = new OpenSSLFileEncryption( $secretPassphrase ) ;
$encrypted = $crypto->encrypt( $archive , '/backups/site.tar.gz.enc' ) ;

// 3. Effacer l'archive en clair (le original a déjà été supprimé sur disque, mais on s'assure)
deleteFile( $archive ) ;

// → /backups/site.tar.gz.enc contient l'archive chiffrée
//   Pour restaurer : decrypt() puis untar()
```

## Cas d'usage : stocker un secret applicatif

```php
$crypto = new OpenSSLFileEncryption( $masterKey ) ;

// Chiffrer une fois au déploiement
$crypto->encrypt( '/etc/myapp/credentials.json' , '/etc/myapp/credentials.json.enc' ) ;
deleteFile( '/etc/myapp/credentials.json' ) ;

// Au runtime
$crypto = new OpenSSLFileEncryption( $masterKeyFromVault ) ;
$crypto->decrypt( '/etc/myapp/credentials.json.enc' , '/dev/shm/credentials.json' ) ;
// → tmpfs : effacé au redémarrage, pas écrit sur disque
$config = json_decode( file_get_contents( '/dev/shm/credentials.json' ) , true ) ;
deleteFile( '/dev/shm/credentials.json' ) ;
```

> 💡 **Stockage de la passphrase** : ne JAMAIS la mettre en clair dans le code ou un fichier versionné. Sources recommandées : variables d'env (`getenv`), HashiCorp Vault, AWS Secrets Manager, ou un fichier `0400` hors version control.

## ⚠ Limites et précautions

- **Pas d'authentification (HMAC)** : `aes-256-cbc` chiffre mais ne protège pas l'**intégrité**. Un attaquant peut altérer le ciphertext et le déchiffrement renverra des données invalides — sans erreur explicite. Pour de l'authentifié, utiliser un cipher GCM (`aes-256-gcm`).
- **Pas de versioning du format** : si tu changes de cipher entre `encrypt` et `decrypt`, ça casse silencieusement. À encoder dans ton workflow.
- **Pas de streaming** : `file_get_contents` charge tout en mémoire. Pour les très gros fichiers (> RAM disponible), utiliser une approche par blocs avec `openssl_encrypt_init` / `update` / `final` (non fourni par cette classe).
- **Le destructeur n'est pas une garantie absolue** : la chaîne `passphrase` est écrasée dans `__destruct`, mais des copies temporaires peuvent persister en RAM (allocations PHP, GC). Pour une sécurité maximale, considérer Sodium ou des extensions HSM.

## Voir aussi

- [Énumérations](../enums.md) — `FileExtension::ENCRYPTED` (suffixe `.enc`).
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Assertions](../files/assertions.md) — `assertFile`, `assertDirectory` utilisées en interne.
- Glossaire : [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-chiffrement-symétrique).
- [Sommaire FR](../README.md).
