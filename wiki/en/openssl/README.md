# OpenSSL — `oihana\files\openssl`

The **`oihana\files\openssl`** module exposes a class and **functional helpers** to **encrypt and decrypt files** via OpenSSL:

- [`OpenSSLFileEncryption`](#opensslfileencryption) — the main class.
- [`deriveKey()`](#derivekey) / [`bestAvailableKdf()`](#bestavailablekdf) — key derivation.
- [`kdfIsAvailable()`](#kdfisavailable) — whether the KDF a file names can run here.
- [`encryptionBlockNonce()`](#encryptionblocknonce) / [`encryptionBlockAad()`](#encryptionblockaad) / [`assertBlockSizeExponent()`](#assertblocksizeexponent) — the block layout of the V3 format.
- [`availableMemoryBytes()`](#availablememorybytes) / [`parseMemoryLimit()`](#parsememorylimit) — the memory budget of the current process.
- [`isAeadCipher()`](#isaeadcipher) — AEAD mode detection.
- [`EncryptionFormat`](#encryptionformat) — V3 file format constants.

> 💡 Requires the PHP **`ext-openssl`** extension (enabled by default). The `ext-sodium` extension is used when available to benefit from **Argon2id** (automatic fallback to PBKDF2-SHA256 otherwise).
>
> ⚠ `ext-sodium` is a **suggestion** of this package, not a dependency — so the two ends of an archive's life are not required to agree on it. A file written where it is loaded names Argon2id in its header and **will not read** where it is missing. Since 1.5.0 that refusal names the extension instead of blaming the passphrase — see [`kdfIsAvailable()`](#kdfisavailable). Files written without sodium use PBKDF2-SHA256, which is native and reads anywhere.

## Security properties

> 📖 See also the [global security rubric](../security.md) for the security perimeter of the whole library.

### What the library guarantees (for files written by `encrypt()`)

| Property | How it is achieved |
|---|---|
| **Confidentiality** | AES-256-GCM. Without the passphrase, the plaintext is computationally infeasible to recover. |
| **Integrity / authenticity** | A 16-byte GCM tag **per block**, and every tag covers the header, the block index and a final-block flag (see [`encryptionBlockAad()`](#encryptionblockaad)). Tampering with a block, swapping two, dropping one, cutting the file short or splicing two files all make `decrypt()` throw instead of returning plaintext. |
| **Brute-force resistance on the passphrase** | KDF Argon2id (with `ext-sodium`) or PBKDF2-SHA256 with 600 000 iterations. Random salt **per file** → no rainbow tables possible. |
| **Per-file uniqueness** | Salt (16B) + nonce prefix (8B) regenerated on each `encrypt()`. Two encryptions of the same content produce **independent** files, and the block counter makes a nonce repeat under one key impossible. |
| **Constant memory** | Neither direction ever holds more than one block. Measured on 2026-10-24: a 100 MiB round trip under `memory_limit=48M` peaks at **30 MiB**. |

### What the library does **not** guarantee

- **No forward secrecy**: no per-session ephemeral key. Anyone who obtains the passphrase can decrypt **all** past files.
- **No key revocation**: changing the passphrase does not invalidate old files — you must re-encrypt them.
- **No protection against a compromised endpoint**: if an attacker can read PHP memory while the passphrase is in use, they recover it. The `__destruct()` is best-effort.
- **No protection against a weak passphrase**: KDF slows brute-force, it does not prevent it. Use long random passphrases for sensitive data.

## V3 file format

All files written by `encrypt()` use the **V3** format since 1.5.0: a 31-byte header, then a sequence of frames each sealed on its own.

```
┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────┐
│ MAGIC   │ VERSION │ KDF     │ BLOCKEXP │ SALT     │ NONCE PREFIX │
│ 4 bytes │ 1 byte  │ 1 byte  │ 1 byte   │ 16 bytes │ 8 bytes      │
└─────────┴─────────┴─────────┴──────────┴──────────┴──────────────┘
followed by one or more frames:
┌──────────────────────────┬──────────┐
│ encrypted block          │ GCM TAG  │   × N
│ ≤ 2^BLOCKEXP bytes       │ 16 bytes │
└──────────────────────────┴──────────┘
```

| Field | Size | Role |
|---|---|---|
| `MAGIC` | 4 bytes | `"OPHE"` — identifies a library file. |
| `VERSION` | 1 byte | `0x03` — format version. |
| `KDF` | 1 byte | `0x01` = Argon2id, `0x02` = PBKDF2-SHA256. |
| `BLOCKEXP` | 1 byte | Block size as a power of two. `0x17` (2^23 = 8 MiB) by default, bounded to 2^16–2^30 **on read as well as on write**. |
| `SALT` | 16 bytes | Random salt used by the KDF to derive the key. |
| `NONCE PREFIX` | 8 bytes | Per-file random prefix. The nonce of block *i* is `PREFIX ‖ pack('N', i)` — 8 + 4 = 12 bytes, the NIST-recommended GCM IV length. |
| frame | ≤ 2^BLOCKEXP + 16 | Encrypted block followed by its tag. Every frame is exactly `2^BLOCKEXP + 16` bytes except the last. |

### What every tag covers, and why it matters

A GCM tag proves that **a** block was sealed with the key, nothing more. Sealed with no associated data, every block would authenticate on its own, and four rewrites — all performable **without the key** — would go undetected. Hence:

```
AAD(i) = header ‖ pack('N', i) ‖ chr( isFinal ? 1 : 0 )
```

| Attack | What stops it |
|---|---|
| Cut the file short | the final flag: the reader computes it from the cursor against the file size, so the new last frame was sealed as non-final and fails |
| Swap two frames | the block index |
| Drop a frame from the middle | the block index of every frame after it |
| Splice two files encrypted with the same passphrase | the header, whose salt and nonce prefix are per-file |

Two consequences that show up in practice:

- **an empty file still holds one frame** — an empty block and its tag, 47 bytes in all. Were a header alone enough to mean *empty*, stripping every frame from a one-block file would produce something that decrypts to nothing, silently: the one truncation a per-block tag cannot catch;
- **the block-size exponent is bounded on read**, before any allocation: it is one byte of a header nobody has authenticated yet, and it could ask for one-byte blocks (millions of tag verifications) or a 2^255-byte allocation.

### The KDF byte says which one, not whether it is available

It is stored so a reader never has to guess, and so a file stays readable after the library's default changes. It does not make that KDF **available**: Argon2id needs `ext-sodium` at decryption time as much as at encryption time. A file written where the extension is loaded therefore does not read where it is missing, and the library says so by naming the extension (see [`kdfIsAvailable()`](#kdfisavailable)).

## Legacy formats: V2 and V1

Both remain **readable**, and `decrypt()` recognises them on its own.

- **V2** (`OPHE\x02…`, versions 1.1 to 1.4): a single AES-256-GCM blob with one tag.
- **V1** (versions ≤ 1.0): no header at all, `IV (16B) + AES-CBC ciphertext`, passphrase used directly as key, **no integrity check**.

### Why those two cannot be read as a stream

PHP exposes no incremental AEAD API: verifying a single tag means holding the entire ciphertext in memory, and `openssl_decrypt()` allocates the plaintext beside it — about **four times the file size**. Measured on 2026-10-24: 300 MB cost 1.18 GB of peak memory, and 1 GB had the process **killed by the kernel**, with no exception and no stack trace.

Since 1.5.0 that read is therefore guarded: when the file does not fit the memory budget ([`availableMemoryBytes()`](#availablememorybytes)), `decrypt()` **refuses with a sentence** naming the format, the size, the cost, the budget it was measured against and what the operator can do — instead of disappearing. The guard is inert when the budget is unknown, and does not apply to V3.

**`encrypt()` always produces V3.** No more V1 or V2 files are created.

### Migrating an older file to V3

No forced migration. To re-encrypt an old file in the current format:

```php
$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->decrypt( '/old/file.enc' , '/tmp/plain.txt' ) ;   // reads V1 or V2 (auto-detected)
$crypto->encrypt( '/tmp/plain.txt' , '/new/file.enc' ) ;   // writes V3
deleteFile( '/tmp/plain.txt' ) ;                            // cleanup
```

> ⚠ **A V3 file is not readable by an earlier release of this library.** Readers and writers must be upgraded together; archives already written in V2 are unaffected.

## `OpenSSLFileEncryption`

### Constructor

```php
public function __construct(
    string   $passphrase ,
    string   $cipher            = EncryptionFormat::LEGACY_CIPHER , // 'aes-256-cbc'
    ?int     $maxInputBytes     = null ,
    ?int     $blockSizeExponent = null                              // 23 → 8 MiB
)
```

- `$passphrase` — secret used to derive the key. Must be non-empty.
- `$cipher` — only used for **decrypting legacy V1 files**. For V3, AES-256-GCM is always used. Historical default preserved.
- `$maxInputBytes` — optional cap (in bytes) on the size of files passed to `encrypt()` / `decrypt()`. When set, any file whose size exceeds this value is rejected **before** being read, via `RuntimeException`. Default `null` = no limit (historical behaviour). Now that both directions stream, this cap is policy rather than survival.
- `$blockSizeExponent` — block size of the V3 files this instance writes, as a power of two. Default `23` (8 MiB). Validated **here** rather than at `encrypt()` time, so a bad value fails before a file has been opened.

**Throws `InvalidArgumentException`** if:
- the passphrase is empty;
- the requested cipher is not in `openssl_get_cipher_methods()`;
- AES-256-GCM (V3 cipher) is not available in the local OpenSSL build (rare in 2026);
- the block-size exponent falls outside 2^16–2^30.

**Destructor**: attempts `sodium_memzero($this->passphrase)` if available, falls back to string overwrite (best-effort, see limitations).

> 💡 **Example with `maxInputBytes`**: to process externally-sourced files while staying safe from OOM, instantiate with a hard cap.
>
> ```php
> // Reject any file > 50 MiB during encrypt() / decrypt().
> $crypto = new OpenSSLFileEncryption( $passphrase , 'aes-256-cbc' , 50 * 1024 * 1024 ) ;
> ```

### Public property: `ivLength`

```php
public int $ivLength ;
```

IV length of the **legacy cipher** (16 for CBC). Preserved for compatibility — the detection helpers `hasEncryptedFileSize` / `isEncryptedFile` use it for V1 files.

### `encrypt()` — V3 encryption, streaming

```php
public function encrypt( string $inputFile , ?string $outputFile = null ) : string
```

Internal pipeline:

1. `random_bytes(16)` → salt, `random_bytes(8)` → nonce prefix;
2. KDF (Argon2id or PBKDF2 depending on availability) → 32-byte key;
3. write the header: `MAGIC | VERSION | KDF | BLOCKEXP | salt | prefix`;
4. then, block by block: nonce = `prefix ‖ pack('N', i)`, AAD = `header ‖ pack('N', i) ‖ final flag`, and `ciphertext | tag` is appended;
5. `rename()` the `.part` file onto the destination.

Two details that are not details:

- the **final flag is decided from the byte count**, never from `feof()`: an `fread()` that returns a full block leaves `feof()` false, so a plaintext of exactly one or two blocks would be sealed as non-final and then read as final, and a sound file would fail;
- the output goes through **`<destination>.part` then `rename()`**, because a failure half way would otherwise leave a partially written file, which looks exactly like a complete one.

```php
$crypto = new OpenSSLFileEncryption( 'my-passphrase' ) ;
$encrypted = $crypto->encrypt( '/path/to/archive.tar.gz' ) ;
// → '/path/to/archive.tar.gz.enc' (V3 format), whatever its size
```

### `decrypt()` — V3, V2 or legacy V1

```php
public function decrypt( string $inputFile , ?string $outputFile = null ) : string
```

**Automatic detection**, on the first 31 bytes:

- `OPHE\x03` → V3 path, one frame at a time, each tag verified against the header, the block index and the final-block flag before its plaintext is written. Constant memory.
- `OPHE\x02` → V2 path, read whole, behind the memory guard.
- anything else → legacy V1 (IV + CBC ciphertext, raw passphrase), behind the same guard.
- `OPHE` followed by an unknown version → **refused by name**, rather than run through the V1 path, which has no integrity check and would emit whatever AES-CBC made of it.

**One uniform message for every cryptographic failure**, whatever the format:

```
RuntimeException: Decryption failed due to incorrect passphrase or corrupted data.
```

→ No oracle between "wrong passphrase" and "tampered file": that is intentional, and an unknown KDF byte keeps this message too, since it is a forged or corrupt header.

**What does get a message of its own** — because it says nothing about the key or the plaintext, and a generic message would send the reader looking in the wrong place:

| Cause | What the refusal says |
|---|---|
| `ext-sodium` absent for an Argon2id file | the missing extension, and that **the passphrase is not in question** |
| V1/V2 larger than the memory budget | the format, the size, the cost, the budget, and what to do |
| block-size exponent out of bounds | the accepted range |
| unknown format version | that it was written by a newer release |

### `hasEncryptedFileSize()` / `isEncryptedFile()`

Unchanged in API:

- `hasEncryptedFileSize()` relies on `filesize()`.
- `isEncryptedFile()` **reads the first 31 bytes only** — deciding what a file is does not require loading it. It recognises the magic, checks the minimum viable size for the version named, and falls back to the V1 heuristic when there is no magic. A file bearing our magic with an unknown version answers `true`: it is ours, it is simply newer than this reader.

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

Returns `EncryptionFormat::KDF_ARGON2ID` if `ext-sodium` is loaded, otherwise `KDF_PBKDF2_SHA256`. Auto-selection helper, **for writing**.

### `kdfIsAvailable()`

```php
kdfIsAvailable( int $algorithm , ?bool $sodiumLoaded = null ) : bool
```

Its counterpart **for reading**: can the KDF this file names be run here. `false` only for Argon2id without `ext-sodium`; PBKDF2-SHA256 is native, so always `true`.

An **unknown** identifier answers `true`, and that is deliberate: it names no implementation that could be missing. It is a corrupt, forged or newer header — which `deriveKey()` rejects, under the generic message, so a forger gets no confirmation of which byte they got wrong.

`$sodiumLoaded` is a testing seam; `null` asks `function_exists()`.

### `assertBlockSizeExponent()`

```php
assertBlockSizeExponent( int $exponent ) : int
```

Bounds the block-size exponent to 2^16–2^30 and returns it, so it can be validated in place: `1 << assertBlockSizeExponent( $e )`. Called on both sides — on write so the library never produces a file it would refuse to read, and on read because the exponent is one byte of an unauthenticated header.

### `encryptionBlockNonce()`

```php
encryptionBlockNonce( string $noncePrefix , int $index ) : string
```

The nonce of block *i*: `prefix (8B) ‖ pack('N', i) (4B)`. A counter rather than 12 random bytes per block, because a counter cannot collide within a file where random 96-bit nonces collide by birthday after about 2^32 blocks. The index is bounded: `pack('N', 2**32)` would wrap silently to zero, and a reused nonce under one AES-GCM key does not weaken security, it collapses it.

### `encryptionBlockAad()`

```php
encryptionBlockAad( string $header , int $index , bool $isFinal ) : string
```

The associated data binding a block to its file, its position and the end of the stream — the table of the four attacks it stops is [above](#what-every-tag-covers-and-why-it-matters).

### `availableMemoryBytes()`

```php
availableMemoryBytes( ?string $memoryLimit = null , string $meminfoPath = '/proc/meminfo' ) : ?int
```

The memory budget of the current process: `memory_limit` first, then `/proc/meminfo`'s `MemAvailable` **only** when it is `-1`. `null` means *unknown*, never *unlimited* — a caller must then let the operation through rather than refuse on a number it does not have. Both inputs are testing seams.

### `parseMemoryLimit()`

```php
parseMemoryLimit( string $value ) : ?int
```

Reads a `memory_limit`-style value in bytes: `'256M'`, `'1G'`, a plain count. `null` for `-1`, `0`, an empty string — and for `'128MB'`, because PHP reads 128 **bytes** there: answering 134 217 728 would overstate the budget by a factor of a million.

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

Constants class for the V3 format — magic, versions, lengths, block-size exponent bounds, KDF identifiers, default cipher. See [enums.md](../enums.md) for the detailed catalogue.

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

The V3 format closes the memory gap and the four key-less rewrites, but some areas remain **by design**:

- **The KDF must be available where the file is read**: an Argon2id archive requires `ext-sodium` on the machine that restores it. Check it before you need it — the refusal is clear, but it arrives at the worst moment.
- **A V3 file is not readable by an earlier release** of this library.
- **The legacy formats still cannot be streamed**: V1 and V2 hold a single tag for the whole file, and PHP has no incremental AEAD API. The memory guard reports it instead of being killed, but it does not solve it: re-encrypt in V3.
- **No metadata signing**: if you rename `important.enc` to `unimportant.enc`, decryption succeeds — the AAD binds the header, the block index and the end of the stream, not the **file name**. Authenticating metadata (name, timestamps) would mean feeding it into the AAD, which this class does not expose.
- **Best-effort destructor**: see explanation in the `__destruct()` doc.

For those cases, see the [security best practices](../security.md#user-best-practices).

## See also

- [Global security rubric](../security.md) — security perimeter of the library.
- [Enums](../enums.md) — `EncryptionFormat`, `FileExtension::ENCRYPTED`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`, `RuntimeException`.
- [Assertions](../files/assertions.md) — used internally.
- Glossary: [IV](../getting-started/glossary.md#iv-initialization-vector), [Cipher](../getting-started/glossary.md#cipher-symmetric-encryption).
- [English TOC](../README.md).
