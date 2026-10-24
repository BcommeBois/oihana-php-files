# OpenSSL — `oihana\files\openssl`

Le module **`oihana\files\openssl`** expose une classe et des **helpers fonctionnels** pour **chiffrer et déchiffrer des fichiers** via OpenSSL :

- [`OpenSSLFileEncryption`](#opensslfileencryption) — la classe principale.
- [`deriveKey()`](#derivekey) / [`bestAvailableKdf()`](#bestavailablekdf) — dérivation de clé.
- [`kdfIsAvailable()`](#kdfisavailable) — le KDF nommé par un fichier peut-il tourner ici.
- [`encryptionBlockNonce()`](#encryptionblocknonce) / [`encryptionBlockAad()`](#encryptionblockaad) / [`assertBlockSizeExponent()`](#assertblocksizeexponent) — le découpage en blocs du format V3.
- [`availableMemoryBytes()`](#availablememorybytes) / [`parseMemoryLimit()`](#parsememorylimit) — le budget mémoire du processus.
- [`isAeadCipher()`](#isaeadcipher) — détection des modes AEAD.
- [`EncryptionFormat`](#encryptionformat) — constantes du format de fichier V3.

> 💡 Requiert l'extension PHP **`ext-openssl`** (activée par défaut). L'extension `ext-sodium` est utilisée si disponible pour bénéficier d'**Argon2id** (sinon repli automatique sur PBKDF2-SHA256).
>
> ⚠ `ext-sodium` est une **suggestion** du paquet, pas une dépendance : les deux bouts de la vie d'une archive ne sont donc pas tenus d'être d'accord dessus. Un fichier écrit là où elle est chargée note Argon2id dans son en-tête et **ne se relit pas** là où elle manque. Depuis la 1.5.0 ce refus nomme l'extension au lieu d'accuser la passphrase — voir [`kdfIsAvailable()`](#kdfisavailable). Les fichiers écrits sans sodium utilisent PBKDF2-SHA256, native, qui se relit partout.

## Propriétés de sécurité

> 📖 Voir aussi la [rubrique sécurité globale](../security.md) pour le périmètre de sécurité de toute la library.

### Ce que la library garantit (pour les fichiers écrits par `encrypt()`)

| Propriété | Comment c'est obtenu |
|---|---|
| **Confidentialité** | AES-256-GCM. Sans la passphrase, le clair est computationnellement irrécupérable. |
| **Intégrité / authenticité** | Un tag GCM de 16 octets **par bloc**, et chaque tag couvre l'en-tête, l'indice du bloc et un drapeau « dernier bloc » (voir [`encryptionBlockAad()`](#encryptionblockaad)). Altérer un bloc, en échanger deux, en retirer un, tronquer le fichier ou recoller deux fichiers fait échouer `decrypt()` au lieu de rendre du clair. |
| **Résistance au brute-force sur la passphrase** | KDF Argon2id (avec `ext-sodium`) ou PBKDF2-SHA256 600 000 itérations. Sel aléatoire **par fichier** → pas de rainbow tables possibles. |
| **Unicité par fichier** | Sel (16B) + préfixe de nonce (8B) régénérés à chaque `encrypt()`. Deux chiffrements du même contenu produisent deux fichiers **indépendants**, et le compteur de blocs interdit qu'un nonce se répète sous une même clé. |
| **Mémoire constante** | Aucun des deux sens ne tient plus d'un bloc. Mesuré le 2026-10-24 : 100 Mio d'aller-retour sous `memory_limit=48M` plafonnent à **30 Mio**. |

### Ce que la library **ne** garantit **pas**

- **Pas de forward secrecy** : pas de clé éphémère par session. Quiconque obtient la passphrase peut déchiffrer **tous** les fichiers passés.
- **Pas de révocation de clé** : changer la passphrase n'invalide pas les anciens fichiers — il faut tous les rechiffrer.
- **Pas de protection contre un endpoint compromis** : si un attaquant lit la RAM PHP pendant l'usage, il récupère la passphrase. Le `__destruct()` est best-effort.
- **Pas de protection contre une passphrase faible** : le KDF ralentit le brute-force, il ne l'empêche pas. Utiliser des passphrases longues et aléatoires pour les données sensibles.

## Format de fichier V3

Tous les fichiers écrits par `encrypt()` utilisent le format **V3** depuis la 1.5.0 : un en-tête de 31 octets, puis une suite de trames scellées une par une.

```
┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────┐
│ MAGIC   │ VERSION │ KDF     │ BLOCKEXP │ SALT     │ NONCE PREFIX │
│ 4 bytes │ 1 byte  │ 1 byte  │ 1 byte   │ 16 bytes │ 8 bytes      │
└─────────┴─────────┴─────────┴──────────┴──────────┴──────────────┘
puis une ou plusieurs trames :
┌──────────────────────────┬──────────┐
│ bloc chiffré             │ TAG GCM  │   × N
│ ≤ 2^BLOCKEXP octets      │ 16 bytes │
└──────────────────────────┴──────────┘
```

| Champ | Taille | Rôle |
|---|---|---|
| `MAGIC` | 4 bytes | `"OPHE"` — identifie un fichier de la library. |
| `VERSION` | 1 byte | `0x03` — version du format. |
| `KDF` | 1 byte | `0x01` = Argon2id, `0x02` = PBKDF2-SHA256. |
| `BLOCKEXP` | 1 byte | Taille de bloc en puissance de deux. `0x17` (2^23 = 8 Mio) par défaut, bornée à 2^16–2^30 **à la lecture comme à l'écriture**. |
| `SALT` | 16 bytes | Sel aléatoire, utilisé par le KDF pour dériver la clé. |
| `NONCE PREFIX` | 8 bytes | Préfixe aléatoire par fichier. Le nonce du bloc *i* est `PRÉFIXE ‖ pack('N', i)` — 8 + 4 = 12 octets, la longueur d'IV recommandée par le NIST. |
| trame | ≤ 2^BLOCKEXP + 16 | Bloc chiffré suivi de son tag. Toutes les trames font exactement `2^BLOCKEXP + 16` octets, sauf la dernière. |

### Ce que chaque tag couvre, et pourquoi ça compte

Un tag GCM prouve qu'**un** bloc a été scellé avec la clé, rien de plus. Scellés sans données associées, chaque bloc s'authentifierait seul, et quatre réécritures — faisables **sans la clé** — passeraient inaperçues. D'où :

```
AAD(i) = en-tête ‖ pack('N', i) ‖ chr( dernier ? 1 : 0 )
```

| Attaque | Ce qui l'arrête |
|---|---|
| Tronquer le fichier | le drapeau final : le lecteur le calcule depuis sa position contre la taille du fichier, donc la nouvelle dernière trame a été scellée comme non-finale et échoue |
| Échanger deux trames | l'indice du bloc |
| Retirer une trame du milieu | l'indice de toutes les trames suivantes |
| Recoller deux fichiers chiffrés avec la même passphrase | l'en-tête, dont le sel et le préfixe de nonce sont propres au fichier |

Deux conséquences qui se voient à l'usage :

- **un fichier vide contient quand même une trame** — un bloc vide et son tag, soit 47 octets. Si l'en-tête seul suffisait à représenter le vide, retirer toutes les trames d'un fichier d'un bloc donnerait un fichier qui se déchiffre en rien, en silence : la seule troncature qu'un tag par bloc ne peut pas voir ;
- **l'exposant de bloc est borné à la lecture**, avant toute allocation : c'est un octet d'un en-tête que personne n'a encore authentifié, et il pourrait demander des blocs d'un octet (des millions de vérifications de tag) ou une allocation de 2^255 octets.

### L'octet KDF dit lequel, pas s'il est disponible

Il est stocké pour qu'un lecteur n'ait pas à deviner, et pour qu'un fichier reste lisible après un changement de défaut de la bibliothèque. Il ne rend pas ce KDF **disponible** : Argon2id demande `ext-sodium` au déchiffrement autant qu'au chiffrement. Un fichier écrit là où l'extension est chargée ne se relit donc pas là où elle manque, et la bibliothèque le dit en nommant l'extension (voir [`kdfIsAvailable()`](#kdfisavailable)).

## Formats legacy : V2 et V1

Les deux restent **lisibles**, et `decrypt()` les reconnaît tout seul.

- **V2** (`OPHE\x02…`, versions 1.1 à 1.4) : un unique blob AES-256-GCM avec un seul tag.
- **V1** (versions ≤ 1.0) : pas d'en-tête du tout, `IV (16B) + ciphertext AES-CBC`, passphrase utilisée directement comme clé, **aucune vérification d'intégrité**.

### Pourquoi ces deux-là ne peuvent pas être lus en flux

PHP n'expose aucune API AEAD incrémentale : vérifier un tag unique impose de tenir tout le ciphertext en mémoire, et `openssl_decrypt()` alloue le clair à côté — environ **quatre fois la taille du fichier**. Mesuré le 2026-10-24 : 300 Mo coûtaient 1,18 Go de pic, et 1 Go faisait **tuer le processus par le noyau**, sans exception ni trace.

Depuis la 1.5.0, cette lecture est donc gardée : si le fichier ne tient pas dans le budget mémoire ([`availableMemoryBytes()`](#availablememorybytes)), `decrypt()` **refuse avec une phrase** qui nomme le format, la taille, le coût, le budget mesuré et ce que l'opérateur peut faire — au lieu de disparaître. La garde est inerte quand le budget est inconnu, et ne s'applique pas au V3.

**`encrypt()` produit toujours du V3.** Plus aucun fichier V1 ni V2 n'est créé.

### Migrer un ancien fichier vers V3

Pas de migration forcée. Pour rechiffrer un ancien fichier au format courant :

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->decrypt( '/old/file.enc' , '/tmp/plain.txt' ) ;   // lit V1 ou V2 (auto-détecté)
$crypto->encrypt( '/tmp/plain.txt' , '/new/file.enc' ) ;   // écrit V3
deleteFile( '/tmp/plain.txt' ) ;                            // nettoyage
```

> ⚠ **Un fichier V3 n'est pas lisible par une version antérieure de la library.** Les lecteurs et les écrivains doivent être mis à jour ensemble ; les archives déjà écrites en V2 ne sont pas concernées.

## `OpenSSLFileEncryption`

### Constructeur

```php
public function __construct(
    string   $passphrase ,
    string   $cipher            = EncryptionFormat::LEGACY_CIPHER , // 'aes-256-cbc'
    ?int     $maxInputBytes     = null ,
    ?int     $blockSizeExponent = null                              // 23 → 8 Mio
)
```

- `$passphrase` — secret utilisé pour dériver la clé. Doit être non-vide.
- `$cipher` — ne sert qu'au **déchiffrement des fichiers legacy V1**. Pour V3, AES-256-GCM est toujours utilisé. Défaut historique préservé.
- `$maxInputBytes` — plafond optionnel (en octets) sur la taille des fichiers passés à `encrypt()` / `decrypt()`. Si défini, tout fichier dont la taille dépasse cette valeur est rejeté **avant** la lecture, via `RuntimeException`. Défaut `null` = pas de limite (comportement historique). Depuis que les deux sens travaillent en flux, ce plafond relève de la politique plutôt que de la survie.
- `$blockSizeExponent` — taille de bloc des fichiers V3 écrits par cette instance, en puissance de deux. Défaut `23` (8 Mio). Validée **ici** plutôt qu'au moment du `encrypt()`, pour qu'une mauvaise valeur échoue avant qu'un fichier soit ouvert.

**Lève `InvalidArgumentException`** si :
- la passphrase est vide ;
- le cipher demandé n'est pas dans `openssl_get_cipher_methods()` ;
- AES-256-GCM (cipher V3) n'est pas dispo dans le build OpenSSL local (rare en 2026) ;
- l'exposant de bloc sort de l'intervalle 2^16–2^30.

**Destructeur** : tente `sodium_memzero($this->passphrase)` si dispo, sinon overwrite-string (best-effort, voir limitations).

> 💡 **Exemple `maxInputBytes`** : pour traiter des fichiers d'origine externe en restant à l'abri d'un OOM, instancier avec un plafond ferme.
>
> ```php
> // Refuse tout fichier > 50 Mio lors d'encrypt() / decrypt().
> $crypto = new OpenSSLFileEncryption( $passphrase , 'aes-256-cbc' , 50 * 1024 * 1024 ) ;
> ```

### Propriété publique : `ivLength`

```php
public int $ivLength ;
```

Longueur d'IV du **cipher legacy** (16 pour CBC). Conservée pour compat — les helpers de détection `hasEncryptedFileSize` / `isEncryptedFile` l'utilisent pour les fichiers V1.

### `encrypt()` — chiffrement V3, en flux

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Pipeline interne :

1. `random_bytes(16)` → sel, `random_bytes(8)` → préfixe de nonce ;
2. KDF (Argon2id ou PBKDF2 selon dispo) → clé 32 octets ;
3. écriture de l'en-tête : `MAGIC | VERSION | KDF | BLOCKEXP | sel | préfixe` ;
4. puis, bloc par bloc : nonce = `préfixe ‖ pack('N', i)`, AAD = `en-tête ‖ pack('N', i) ‖ drapeau final`, et on écrit `ciphertext | tag` ;
5. `rename()` du fichier `.part` vers la destination.

Deux détails qui ne sont pas des détails :

- le **drapeau final se décide au compteur d'octets**, jamais sur `feof()` : un `fread()` qui rend un bloc plein laisse `feof()` à faux, donc un clair de pile un ou deux blocs serait scellé non-final puis relu comme final, et un fichier sain échouerait ;
- la sortie passe par **`<destination>.part` puis `rename()`**, parce qu'un échec en cours de route laisserait sinon un fichier à moitié écrit, qui ressemble exactement à un fichier entier.

```php
$crypto = new OpenSSLFileEncryption( 'my-passphrase' ) ;
$encrypted = $crypto->encrypt( '/path/to/archive.tar.gz' ) ;
// → '/path/to/archive.tar.gz.enc' (format V3), quelle que soit sa taille
```

### `decrypt()` — V3, V2 ou legacy V1

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

**Détection automatique**, sur les 31 premiers octets :

- `OPHE\x03` → chemin V3, une trame à la fois, chaque tag vérifié contre l'en-tête, l'indice et le drapeau final avant que son clair soit écrit. Mémoire constante.
- `OPHE\x02` → chemin V2, lecture en mémoire, derrière la garde mémoire.
- autre chose → legacy V1 (IV + ciphertext CBC, passphrase brute), derrière la même garde.
- `OPHE` suivi d'une version inconnue → **refus explicite**, plutôt que de passer le fichier au chemin V1, qui n'a aucun contrôle d'intégrité et rendrait ce qu'AES-CBC en a fait.

**Message uniforme pour tout échec cryptographique**, quel que soit le format :

```
RuntimeException: Decryption failed due to incorrect passphrase or corrupted data.
```

→ Pas d'oracle entre « mauvaise passphrase » et « fichier altéré » : c'est volontaire, et un octet KDF inconnu garde ce message-là aussi, puisque c'est un en-tête forgé ou corrompu.

**Ce qui a, au contraire, son propre message** — parce qu'il ne dit rien sur la clé ni sur le clair, et qu'un message générique ferait chercher au mauvais endroit :

| Cause | Ce que dit le refus |
|---|---|
| `ext-sodium` absente pour un fichier Argon2id | l'extension manquante, et que **la passphrase n'est pas en cause** |
| V1/V2 plus gros que le budget mémoire | le format, la taille, le coût, le budget, et quoi faire |
| exposant de bloc hors bornes | l'intervalle attendu |
| version de format inconnue | qu'il a été écrit par une version plus récente |

### `hasEncryptedFileSize()` / `isEncryptedFile()`

Inchangées dans leur API :

- `hasEncryptedFileSize()` s'appuie sur `filesize()`.
- `isEncryptedFile()` **ne lit que les 31 premiers octets** — décider ce qu'est un fichier ne demande pas de le charger. Elle reconnaît le magic, vérifie la taille minimale viable de la version annoncée, et retombe sur l'heuristique V1 sans magic. Un fichier portant notre magic avec une version inconnue répond `true` : il est bien à nous, il est seulement plus récent que ce lecteur.

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

Renvoie `EncryptionFormat::KDF_ARGON2ID` si `ext-sodium` est chargée, sinon `KDF_PBKDF2_SHA256`. Helper pratique pour l'auto-sélection **à l'écriture**.

### `kdfIsAvailable()`

```php
kdfIsAvailable( int $algorithm , ?bool $sodiumLoaded = null ) : bool
```

Le pendant à la **lecture** : le KDF que ce fichier nomme peut-il tourner ici. `false` seulement pour Argon2id sans `ext-sodium` ; PBKDF2-SHA256 est native, donc toujours `true`.

Un identifiant **inconnu** répond `true`, et c'est voulu : il ne désigne aucune implémentation qui pourrait manquer. C'est un en-tête corrompu, forgé, ou venu d'une version plus récente — ce que `deriveKey()` rejette, sous le message générique, pour ne pas confirmer à un faussaire quel octet il a raté.

`$sodiumLoaded` est une couture de test ; `null` interroge `function_exists()`.

### `assertBlockSizeExponent()`

```php
assertBlockSizeExponent( int $exponent ) : int
```

Borne l'exposant de taille de bloc à 2^16–2^30 et le renvoie, pour valider sur place : `1 << assertBlockSizeExponent( $e )`. Appelée des deux côtés — à l'écriture pour ne jamais produire un fichier qu'on refuserait de lire, et à la lecture parce que l'exposant est un octet d'en-tête non authentifié.

### `encryptionBlockNonce()`

```php
encryptionBlockNonce( string $noncePrefix , int $index ) : string
```

Le nonce du bloc *i* : `préfixe (8B) ‖ pack('N', i) (4B)`. Un compteur plutôt que 12 octets aléatoires par bloc, parce qu'un compteur ne peut pas collisionner dans un fichier là où des nonces aléatoires de 96 bits collisionnent par anniversaire vers 2^32 blocs. L'indice est borné : `pack('N', 2**32)` repasserait silencieusement à zéro, et un nonce réutilisé sous une clé AES-GCM n'affaiblit pas la sécurité, il l'annule.

### `encryptionBlockAad()`

```php
encryptionBlockAad( string $header , int $index , bool $isFinal ) : string
```

Les données associées qui lient un bloc à son fichier, à sa position et à la fin du flux — le tableau des quatre attaques qu'elles arrêtent est [plus haut](#ce-que-chaque-tag-couvre-et-pourquoi-ça-compte).

### `availableMemoryBytes()`

```php
availableMemoryBytes( ?string $memoryLimit = null , string $meminfoPath = '/proc/meminfo' ) : ?int
```

Le budget mémoire du processus : `memory_limit` d'abord, puis `MemAvailable` de `/proc/meminfo` **seulement** s'il vaut `-1`. `null` signifie *inconnu*, jamais *illimité* — un appelant doit alors laisser passer plutôt que refuser sur un chiffre qu'il n'a pas. Les deux entrées sont des coutures de test.

### `parseMemoryLimit()`

```php
parseMemoryLimit( string $value ) : ?int
```

Lit une valeur à la `memory_limit` en octets : `'256M'`, `'1G'`, un compte brut. `null` pour `-1`, `0`, une chaîne vide — et pour `'128MB'`, parce que PHP lit 128 **octets** là : répondre 134 217 728 surestimerait le budget d'un facteur un million.

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

Classe de constantes pour le format V3 — magic, versions, longueurs, bornes de l'exposant de bloc, identifiants KDF, cipher par défaut. Voir [enums.md](../enums.md) pour le catalogue détaillé.

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

Le format V3 ferme le trou mémoire et les quatre réécritures hors-clé, mais quelques zones restent **inhérentes au design** :

- **Le KDF doit être disponible là où on relit** : une archive Argon2id exige `ext-sodium` sur la machine qui la restaure. À vérifier avant d'en avoir besoin — le refus est clair, mais il arrive au pire moment.
- **Un fichier V3 n'est pas lisible par une version antérieure** de la library.
- **Les formats legacy ne se lisent toujours pas en flux** : V1 et V2 tiennent un seul tag pour tout le fichier, et PHP n'a pas d'API AEAD incrémentale. La garde mémoire le dit au lieu de se faire tuer, mais elle ne le résout pas : il faut rechiffrer en V3.
- **Pas de signature des métadonnées** : si tu renommes `important.enc` en `unimportant.enc`, ça déchiffre — l'AAD lie l'en-tête, l'indice du bloc et la fin du flux, pas le **nom du fichier**. Pour authentifier des métadonnées (nom, horodatages), il faudrait les faire entrer dans l'AAD, ce que cette classe n'expose pas.
- **Destructeur best-effort** : voir explication dans la doc de `__destruct()`.

Pour ces cas, voir les [bonnes pratiques sécurité](../security.md#bonnes-pratiques-utilisateur).

## Voir aussi

- [Rubrique sécurité globale](../security.md) — périmètre de sécurité de la library.
- [Énumérations](../enums.md) — `EncryptionFormat`, `FileExtension::ENCRYPTED`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`, `RuntimeException`.
- [Assertions](../files/assertions.md) — utilisées en interne.
- Glossaire : [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-chiffrement-symétrique).
- [Sommaire FR](../README.md).
