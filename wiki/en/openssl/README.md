# OpenSSL — `oihana\files\openssl`

The **`oihana\files\openssl`** module exposes a single class — [`OpenSSLFileEncryption`](#openssl­fileencryption) — to **encrypt and decrypt files** via OpenSSL, with automatic **IV (Initialization Vector)** handling.

> 💡 Requires the PHP **`ext-openssl`** extension (enabled by default). See [Installation](../getting-started/installation.md) for verification.

## Principle in one sentence

An `OpenSSLFileEncryption($passphrase, $cipher)` object produces **self-contained** encrypted files: the IV is prepended in the output file, so decryption only requires the same passphrase and cipher — no need to store the IV separately.

```
┌────────────────────────────────────────────┐
│  IV (16 bytes for AES)  │  encrypted data │
└────────────────────────────────────────────┘
```

## Default cipher

**`aes-256-cbc`** (Advanced Encryption Standard, 256-bit key, Cipher Block Chaining mode). Solid cryptographic recommendation for general use.

Any algorithm listed by `openssl_get_cipher_methods()` is accepted — the constructor validates via `in_array`.

## `OpenSSLFileEncryption`

### Constructor

```php
public function __construct(
    string $passphrase ,
    string $cipher = 'aes-256-cbc'
)
```

**Throws `InvalidArgumentException`** if:
- the requested cipher is not in `openssl_get_cipher_methods()`;
- the passphrase is empty.

**Safe destructor**: the passphrase is **wiped from memory** (`str_repeat("\0", ...)`) when the object is destroyed — good practice to limit memory exposure.

```php
use oihana\files\openssl\OpenSSLFileEncryption;

$crypto = new OpenSSLFileEncryption( 'my-secret-passphrase' ) ;

// Custom cipher
$crypto = new OpenSSLFileEncryption( $passphrase , 'aes-128-gcm' ) ;

// Unknown cipher
$crypto = new OpenSSLFileEncryption( $passphrase , 'foo' ) ;
// → InvalidArgumentException: Cipher method 'foo' is not available
```

### Public property: `ivLength`

```php
public int $ivLength ;
```

IV length in bytes for the chosen cipher (determined via `openssl_cipher_iv_length`). Read-only (PHP 8.4 property hook).

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ; // aes-256-cbc
echo $crypto->ivLength ; // 16 (for AES in CBC)
```

---

### `encrypt`

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Encrypts a file. Returns the output file path.

**Steps:**

1. `assertFile` on the input.
2. Determine output path: `$outputFile` if provided, otherwise `$inputFile . '.enc'` (extension via `FileExtension::ENCRYPTED`).
3. Read content.
4. **Generate a secure IV** via `openssl_random_pseudo_bytes($this->ivLength, $cryptoStrong)`. If `$cryptoStrong` is `false` → `RuntimeException` (non-cryptographically-strong randomness source).
5. `openssl_encrypt` in `OPENSSL_RAW_DATA` mode.
6. Validation: output directory writable (`assertDirectory(..., isWritable: true)`), output file writable if it exists.
7. Write: **IV concatenated at the start + encrypted data**.

**Exceptions:**

| Exception | Case |
|---|---|
| `FileException` | Invalid input (via `assertFile`). |
| `DirectoryException` | Output directory not writable. |
| `RuntimeException` | Read failure, non-crypto-strong IV, encryption failure, write failure. |

```php
use oihana\files\openssl\OpenSSLFileEncryption;

$crypto = new OpenSSLFileEncryption( 'secret' ) ;

// Auto output: appends .enc
$encrypted = $crypto->encrypt( '/path/to/file.txt' ) ;
// → '/path/to/file.txt.enc'

// Custom output
$encrypted = $crypto->encrypt( '/path/to/file.txt' , '/secure/file.enc' ) ;
// → '/secure/file.enc'
```

---

### `decrypt`

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

Decrypts a previously encrypted file.

**Steps:**

1. `assertFile` on the input.
2. Determine output path: `$outputFile` if provided, otherwise `$inputFile` with `.enc` stripped (`str_replace`).
3. Read.
4. Size check: the file must be at least `$ivLength` bytes (`RuntimeException` otherwise).
5. **Extract IV** from the first `$ivLength` bytes, **encrypted data** from the rest.
6. `openssl_decrypt`. Failure → `RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.")` (no distinction between wrong passphrase and corruption — standard practice to avoid leaking the info).
7. Validation: existing + writable output directory, writable file if exists.
8. Write decrypted data.

```php
$crypto = new OpenSSLFileEncryption( 'secret' ) ;

// Auto output: strips .enc
$decrypted = $crypto->decrypt( '/path/to/file.txt.enc' ) ;
// → '/path/to/file.txt'

// Custom output
$decrypted = $crypto->decrypt( '/path/to/file.txt.enc' , '/restored/file.txt' ) ;
```

---

### `hasEncryptedFileSize`

```php
public function hasEncryptedFileSize( string $filePath ) : bool
```

**Quick size test**: true if the file exists AND is at least `$ivLength` bytes. **Does not validate the content**, just the minimum size required to contain an IV.

```php
if ( $crypto->hasEncryptedFileSize( '/path/to/file' ) ) {
    echo "Size compatible with an encrypted file." ;
}
// Not a certainty — just a necessary precondition.
```

---

### `isEncryptedFile`

```php
public function isEncryptedFile( string $filePath ) : bool
```

**Heuristic** check that guesses whether a file looks like it was encrypted by this class.

**Three criteria:**

1. **Size** ≥ `$ivLength` (same as `hasEncryptedFileSize`).
2. **The IV (first bytes) is not all `\0`** — a null IV likely indicates an unencrypted or corrupted file.
3. **The IV does not contain too many printable ASCII characters** (> 80% of bytes in range 32-126) — probably indicates plain text rather than a random IV.

```php
if ( $crypto->isEncryptedFile( '/path/to/file' ) ) {
    echo "Likely encrypted." ;
}
```

> ⚠ **It's a heuristic, not a proof.** An unencrypted binary file (a JPEG image, for instance) starts with non-printable bytes and will pass this test. Conversely, some encryptions may incidentally produce mostly-printable IVs. Combine with other signals (`.enc` extension, application context).

---

## Use case: encrypt a backup file

```php
use oihana\files\openssl\OpenSSLFileEncryption;
use function oihana\files\archive\tar\tarDirectory;
use function oihana\files\deleteFile;
use oihana\files\enums\CompressionType;

// 1. Create a tar.gz of the site
$archive = tarDirectory( '/var/www/site' , CompressionType::GZIP , '/tmp/site.tar.gz' ) ;

// 2. Encrypt
$crypto    = new OpenSSLFileEncryption( $secretPassphrase ) ;
$encrypted = $crypto->encrypt( $archive , '/backups/site.tar.gz.enc' ) ;

// 3. Wipe the plaintext archive
deleteFile( $archive ) ;

// → /backups/site.tar.gz.enc contains the encrypted archive
//   To restore: decrypt() then untar()
```

## Use case: store an application secret

```php
$crypto = new OpenSSLFileEncryption( $masterKey ) ;

// Encrypt once at deployment
$crypto->encrypt( '/etc/myapp/credentials.json' , '/etc/myapp/credentials.json.enc' ) ;
deleteFile( '/etc/myapp/credentials.json' ) ;

// At runtime
$crypto = new OpenSSLFileEncryption( $masterKeyFromVault ) ;
$crypto->decrypt( '/etc/myapp/credentials.json.enc' , '/dev/shm/credentials.json' ) ;
// → tmpfs: wiped on reboot, never written to disk
$config = json_decode( file_get_contents( '/dev/shm/credentials.json' ) , true ) ;
deleteFile( '/dev/shm/credentials.json' ) ;
```

> 💡 **Passphrase storage**: NEVER put it in plain text in code or in a versioned file. Recommended sources: env variables (`getenv`), HashiCorp Vault, AWS Secrets Manager, or a `0400` file outside version control.

## ⚠ Limitations and precautions

- **No authentication (HMAC)**: `aes-256-cbc` encrypts but does not protect **integrity**. An attacker can tamper with the ciphertext and decryption will return invalid data — without an explicit error. For authenticated encryption, use a GCM cipher (`aes-256-gcm`).
- **No format versioning**: if you change the cipher between `encrypt` and `decrypt`, it breaks silently. Encode it in your workflow.
- **No streaming**: `file_get_contents` loads everything in memory. For very large files (> available RAM), use a block-based approach with `openssl_encrypt_init` / `update` / `final` (not provided by this class).
- **The destructor is not an absolute guarantee**: the `passphrase` string is overwritten in `__destruct`, but temporary copies may persist in RAM (PHP allocations, GC). For maximum security, consider Sodium or HSM extensions.

## See also

- [Enums](../enums.md) — `FileExtension::ENCRYPTED` (`.enc` suffix).
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [Assertions](../files/assertions.md) — `assertFile`, `assertDirectory` used internally.
- Glossary: [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-symmetric-encryption).
- [English TOC](../README.md).
