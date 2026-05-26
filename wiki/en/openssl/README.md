# OpenSSL — `oihana\files\openssl`

The **`oihana\files\openssl`** module exposes a class and **functional helpers** to **encrypt and decrypt files** via OpenSSL:

- [`OpenSSLFileEncryption`](#openssl­fileencryption) — the main class.
- [`deriveKey()`](#derivekey) / [`bestAvailableKdf()`](#bestavailablekdf) — key derivation.
- [`isAeadCipher()`](#isaeadcipher) — AEAD mode detection.
- [`EncryptionFormat`](#encryptionformat) — V2 file format constants.

> 💡 Requires the PHP **`ext-openssl`** extension (enabled by default). The `ext-sodium` extension is used when available to benefit from **Argon2id** (automatic fallback to PBKDF2-SHA256 otherwise).

## Security properties

> 📖 See also the [global security rubric](../security.md) for the security perimeter of the whole library.

### What the library guarantees (for files written by `encrypt()`)

| Property | How it is achieved |
|---|---|
| **Confidentiality** | AES-256-GCM. Without the passphrase, the plaintext is computationally infeasible to recover. |
| **Integrity / authenticity** | GCM authentication tag (16 bytes). Any tampering with the encrypted file makes `decrypt()` throw `RuntimeException` instead of returning corrupted plaintext. |
| **Brute-force resistance on the passphrase** | KDF Argon2id (with `ext-sodium`) or PBKDF2-SHA256 with 600 000 iterations. Random salt **per file** → no rainbow tables possible. |
| **Per-file uniqueness** | Salt (16B) + IV (12B) regenerated on each `encrypt()`. Two encryptions of the same content produce **independent** encrypted files. |

### What the library does **not** guarantee

- **No forward secrecy**: no per-session ephemeral key. Anyone who obtains the passphrase can decrypt **all** past files.
- **No key revocation**: changing the passphrase does not invalidate old files — you must re-encrypt them.
- **No protection against a compromised endpoint**: if an attacker can read PHP memory while the passphrase is in use, they recover it. The `__destruct()` is best-effort.
- **No protection against a weak passphrase**: KDF slows brute-force, it does not prevent it. Use long random passphrases for sensitive data.

## V2 file format

All files written by `encrypt()` use the **V2** format.

```
┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────────────┐
│ MAGIC   │ VERSION │ KDF     │ SALT     │ IV       │ ciphertext + TAG     │
│ 4 bytes │ 1 byte  │ 1 byte  │ 16 bytes │ 12 bytes │ variable + 16 bytes  │
└─────────┴─────────┴─────────┴──────────┴──────────┴──────────────────────┘
```

| Field | Size | Role |
|---|---|---|
| `MAGIC` | 4 bytes | `"OPHE"` — identifies a library file. |
| `VERSION` | 1 byte | `0x02` — format version. |
| `KDF` | 1 byte | `0x01` = Argon2id, `0x02` = PBKDF2-SHA256. |
| `SALT` | 16 bytes | Random salt used by the KDF to derive the key. |
| `IV` | 12 bytes | Random IV for AES-GCM (12 bytes = NIST recommendation). |
| `ciphertext + TAG` | variable + 16 bytes | Ciphertext followed by the GCM authentication tag. |

The KDF byte is stored so a different environment (e.g. PHP without `ext-sodium`) knows which KDF to use to decrypt. If a file was encrypted with Argon2id and is read on an environment without `ext-sodium`, the library throws a clear `RuntimeException`.

## Backward compatibility (legacy V1)

Files written by `oihana/php-files` ≤ 1.0 use the **legacy V1 format**: no magic header, `IV (16B) + AES-CBC ciphertext`, passphrase used directly as key, **no integrity check**.

`decrypt()` **automatically detects** the absence of the `OPHE\x02` magic and reads V1 files with the old logic (CBC, raw passphrase). Your old files remain readable.

**`encrypt()` always produces V2.** No more V1 files are created.

### Migrating a V1 file to V2

No forced migration. To re-encrypt an old file in the modern format:

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->decrypt( '/old/file.enc' , '/tmp/plain.txt' ) ;   // reads V1 (auto-detected)
$crypto->encrypt( '/tmp/plain.txt' , '/new/file.enc' ) ;   // writes V2
deleteFile( '/tmp/plain.txt' ) ;                            // cleanup
```

## `OpenSSLFileEncryption`

### Constructor

```php
public function __construct(
    string $passphrase ,
    string $cipher = EncryptionFormat::LEGACY_CIPHER  // 'aes-256-cbc'
)
```

- `$passphrase` — secret used to derive the key. Must be non-empty.
- `$cipher` — only used for **decrypting legacy V1 files**. For V2, AES-256-GCM is always used. Historical default preserved.

**Throws `InvalidArgumentException`** if:
- the passphrase is empty;
- the requested cipher is not in `openssl_get_cipher_methods()`;
- AES-256-GCM (V2 cipher) is not available in the local OpenSSL build (rare in 2026).

**Destructor**: attempts `sodium_memzero($this->passphrase)` if available, falls back to string overwrite (best-effort, see limitations).

### Public property: `ivLength`

```php
public int $ivLength ;
```

IV length of the **legacy cipher** (16 for CBC). Preserved for compatibility — the detection helpers `hasEncryptedFileSize` / `isEncryptedFile` use it for V1 files.

### `encrypt()` — V2 encryption

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Internal pipeline:

1. `random_bytes(16)` → salt;
2. `random_bytes(12)` → IV;
3. KDF (Argon2id or PBKDF2 depending on availability) → 32-byte key;
4. `openssl_encrypt(..., 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16)`;
5. Write: `MAGIC | VERSION | KDF | salt | IV | ciphertext | tag`.

```php
$crypto = new OpenSSLFileEncryption( 'my-passphrase' ) ;
$encrypted = $crypto->encrypt( '/path/to/file.txt' ) ;
// → '/path/to/file.txt.enc' (V2 format)
```

### `decrypt()` — V2 or legacy V1 decryption

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

**Automatic detection:**

- If the first 5 bytes are `OPHE\x02` → V2 path (reads salt, IV, ciphertext, tag; KDF identified by byte 6; verifies the tag).
- Otherwise → legacy V1 path (IV + CBC ciphertext, raw passphrase).

**Uniform error message** regardless of format:

```
RuntimeException: Decryption failed due to incorrect passphrase or corrupted data.
```

→ No oracle between "wrong passphrase" and "tampered file" — intentional.

### `hasEncryptedFileSize()` / `isEncryptedFile()`

Unchanged in API, improved internally:

- `hasEncryptedFileSize()` now uses `filesize()` (instead of loading the whole file).
- `isEncryptedFile()` detects V2 magic on a fast path + verifies minimum viable size, falls back to V1 heuristic.

---

## Functional helpers

### `deriveKey()`

```php
deriveKey(
    string $passphrase ,
    string $salt ,
    int    $algorithm  // EncryptionFormat::KDF_ARGON2ID or KDF_PBKDF2_SHA256
) : string
```

Derives a 32-byte key via Argon2id or PBKDF2-SHA256 (explicit choice).

**Security conditions covered:**
- Mandatory 16-byte salt (otherwise `RuntimeException`).
- Non-empty passphrase.
- Argon2id: `INTERACTIVE` parameters (≈ 64 MB, ≈ 350 ms on modern CPU).
- PBKDF2: 600 000 SHA-256 iterations (OWASP 2023+ recommendation).

**Conditions not covered:**
- Re-using the same salt across multiple files → rainbow table possible. Always `random_bytes(16)`.

**Use directly** for advanced cases (custom encryption outside file flow). Otherwise `OpenSSLFileEncryption` calls it internally.

### `bestAvailableKdf()`

```php
bestAvailableKdf() : int
```

Returns `EncryptionFormat::KDF_ARGON2ID` if `ext-sodium` is loaded, otherwise `KDF_PBKDF2_SHA256`. Auto-selection helper.

### `isAeadCipher()`

```php
isAeadCipher( string $cipher ) : bool
```

Returns `true` if the cipher name designates an AEAD mode (GCM, CCM, OCB). Purely textual detection, case-insensitive.

```php
isAeadCipher('aes-256-gcm') ;  // true
isAeadCipher('aes-256-cbc') ;  // false
```

### `EncryptionFormat`

Constants class for V2 format — magic, version, lengths, KDF identifiers, default cipher. See [enums.md](../enums.md) for the detailed catalogue.

---

## Use cases

### Encrypt a backup

```php
use oihana\files\openssl\OpenSSLFileEncryption;
use function oihana\files\archive\tar\tarDirectory;
use function oihana\files\deleteFile;
use oihana\files\enums\CompressionType;

$archive   = tarDirectory( '/var/www/site' , CompressionType::GZIP , '/tmp/site.tar.gz' ) ;
$crypto    = new OpenSSLFileEncryption( $secretPassphrase ) ;
$encrypted = $crypto->encrypt( $archive , '/backups/site.tar.gz.enc' ) ;
deleteFile( $archive ) ; // wipe the plaintext
```

### Store an application secret

```php
$crypto = new OpenSSLFileEncryption( $masterKey ) ;
$crypto->encrypt( '/etc/myapp/credentials.json' , '/etc/myapp/credentials.json.enc' ) ;
deleteFile( '/etc/myapp/credentials.json' ) ;

// At runtime:
$crypto = new OpenSSLFileEncryption( $masterKeyFromVault ) ;
$crypto->decrypt( '/etc/myapp/credentials.json.enc' , '/dev/shm/credentials.json' ) ; // tmpfs
$config = json_decode( file_get_contents( '/dev/shm/credentials.json' ) , true ) ;
deleteFile( '/dev/shm/credentials.json' ) ;
```

> 💡 **Passphrase storage**: NEVER put it in plain text in code or a versioned file. Recommended sources: env variables, HashiCorp Vault, AWS Secrets Manager, `0400` file outside version control.

---

## ⚠ Residual limitations

The V2 format fixes the main gaps, but some areas remain **by design**:

- **No streaming**: `file_get_contents` loads everything into RAM. For files > available RAM, use a block-based approach (not provided here — the native API `openssl_encrypt_init` / `update` / `final` allows this in pure PHP).
- **No metadata signing**: if you rename `important.enc` to `unimportant.enc`, decryption succeeds — authentication protects the **content**, not the **name**. To authenticate metadata (filename, timestamps), use the AAD parameter of `openssl_encrypt` (not exposed by this class).
- **Best-effort destructor**: see explanation in the `__destruct()` doc.

For those cases, see the [security best practices](../security.md#user-best-practices).

## See also

- [Global security rubric](../security.md) — security perimeter of the library.
- [Enums](../enums.md) — `EncryptionFormat`, `FileExtension::ENCRYPTED`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`, `RuntimeException`.
- [Assertions](../files/assertions.md) — used internally.
- Glossary: [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-symmetric-encryption).
- [English TOC](../README.md).
