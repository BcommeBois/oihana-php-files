# Security

This page describes **what the library guarantees**, **what it does not guarantee**, and **user best practices**. This is the security contract of `oihana/php-files`.

## Quick overview by module

| Module | Security guarantees | What to watch on the caller side |
|---|---|---|
| [`openssl/`](openssl/README.md) | AES-256-GCM + KDF (Argon2id/PBKDF2) + GCM tag integrity; auto-detect tampering | Passphrase storage; manual rotation |
| [`archive/tar/`](archive/README.md) | Path-traversal detection (`..`) in safe mode (`overwrite: false` or `dryRun: true`) | Always pre-scan in `dryRun` for untrusted archives |
| [`path/`](path/README.md) | `isBasePath` enables anti path-traversal | **Always** canonicalise user paths before comparison |
| [`files/`](files/README.md) | Typed assertions (`assertFile`, `assertDirectory`) | Regex patterns in `findFiles`/`shouldExcludeFile`: do not accept untrusted patterns (ReDoS) |
| [`files/requireAndMergeArrays`](files/reading.md#requireandmergearrays) | Path validation + `.php` extension + optional `$allowedBase` | Always provide `$allowedBase` when paths are not 100% trusted |
| [`toml/`](toml/README.md) | TOML: pure data format (no execution) | OK by construction |

## Threat model covered

This section describes the **threat model** the library was designed against, and the attacks it does **not** cover.

### ✅ Covered threats

| Threat | Module | Mechanism |
|---|---|---|
| **Path traversal** (`../../etc/passwd`) in user input | `path/`, `files/`, `archive/` | `canonicalizePath` + `isBasePath` recommended; `..` detection at tar extraction (in safe mode) |
| **Arbitrary file inclusion** (RCE via `require` on attacked path) | `requireAndMergeArrays` | Path validation + `.php` extension + `$allowedBase` |
| **Tampering on encrypted file** | `openssl/` | GCM tag, explicit failure at `decrypt()` |
| **Passphrase brute-force** | `openssl/` | Expensive KDF (Argon2id/PBKDF2 600k) + per-file salt |
| **Rainbow tables** on common passphrases | `openssl/` | Random 16B salt per file |
| **Format identification** of an encrypted file | `openssl/` | Magic header `OPHE\x02` for V2; auto-detect legacy |
| **IV reuse** (catastrophic for GCM) | `openssl/` | `random_bytes(12)` on every `encrypt()` |

### ❌ **Not** covered threats

| Threat | Why | Caller-side mitigation |
|---|---|---|
| **Forward secrecy** | No per-session ephemeral key — simple symmetric design | If critical, use an asymmetric protocol (libsodium, age) |
| **Key revocation** | Changing the passphrase does not rewrite past files | Manually re-encrypt sensitive files after rotation |
| **Compromised endpoint** (memory dump, malware on host) | Out of scope for a file-level library | OS hardening, external secrets management (Vault) |
| **Weak passphrase** | KDF slows brute-force, does not prevent it | Long passphrases (≥ 16 random chars) |
| **Side channels** (timing attacks on OpenSSL) | Depends on the underlying OpenSSL implementation | Up-to-date OpenSSL build, CPU with AES-NI |
| **File metadata** (name, timestamps, size) | GCM tag authenticates content, not name | Non-semantic naming, external signature |
| **Decompression bombs** (tar bomb) | No size limit at extraction by default | Compute size via `dryRun` before real `untar()` (cf. [tips](tips.md#tar-decompression-bombs)) |
| **ReDoS** on user-supplied regex patterns | PHP `preg_match` has no timeout | Never accept regex from direct user input; validate patterns upstream |
| **Polyglot files** (valid as both PDF and image) | `mime_content_type` reads first bytes | Combine MIME detection + extension + business validation |

## User best practices

### 1. For file encryption

```php
// ✅ Good: passphrase from a trusted source, long, random
$passphrase = getenv('APP_ENCRYPTION_KEY') ;  // from a .env outside VCS or a vault
assert( strlen( $passphrase ) >= 32 ) ;

$crypto = new OpenSSLFileEncryption( $passphrase ) ;
$crypto->encrypt( $sensitive , $encrypted ) ;
deleteFile( $sensitive ) ; // wipe the plaintext

// ❌ Bad: hardcoded passphrase
$crypto = new OpenSSLFileEncryption( 'admin' ) ;  // short + dictionary
```

### 2. For paths coming from external input

```php
use function oihana\files\path\{ canonicalizePath , isBasePath } ;

$base = '/var/www/uploads' ;
$userPath = canonicalizePath( $_POST[ 'path' ] ) ;

// ✅ Good: refuse if outside the allowed directory
if ( !isBasePath( $base , $userPath ) ) {
    throw new \RuntimeException( "Path traversal blocked: $userPath" ) ;
}
```

### 3. For archives coming from user upload

```php
use function oihana\files\archive\tar\untar ;
use oihana\files\enums\TarOption;

// ✅ Good: dryRun pre-scan to compute size and detect traversal
$preview = untar( $uploaded , $dest , [ TarOption::DRY_RUN => true ] ) ;

if ( count( $preview ) > 10_000 ) {
    throw new \RuntimeException( "Archive too large (> 10k files)" ) ;
}

// Safe extraction: refuse to overwrite an existing file
untar( $uploaded , $dest , [ TarOption::OVERWRITE => false ] ) ;
```

### 4. For dynamic loading of PHP files

```php
use function oihana\files\{ requireAndMergeArrays , recursiveFilePaths } ;

$baseDir = __DIR__ . '/definitions' ;

// ✅ Good: $baseDir passed as allowed root
$definitions = requireAndMergeArrays(
    recursiveFilePaths( $baseDir , [ 'extensions' => [ 'php' ] ] ) ,
    true ,
    $baseDir ,  // ← defense in depth
) ;
```

### 5. For user-supplied regex patterns (find, exclude)

```php
// ❌ Bad: direct pattern from $_GET → ReDoS vector
findFiles( $dir , [ 'pattern' => $_GET[ 'pattern' ] ] ) ;

// ✅ Good: validate it's a simple glob, or whitelist patterns
$allowed = [ '*.txt' , '*.log' , '*.json' ] ;
$pattern = in_array( $_GET[ 'pattern' ] , $allowed , true ) ? $_GET[ 'pattern' ] : '*' ;
findFiles( $dir , [ 'pattern' => $pattern ] ) ;
```

### 6. For sensitive configs (credentials, tokens)

- Encrypt **at rest** via `OpenSSLFileEncryption` when the file must stay on disk.
- Load into memory at runtime via a `tmpfs` directory (`/dev/shm` on Linux) that never touches the disk.
- Wipe immediately after use (`deleteFile`).

## Security history

| Date | Version | Change |
|---|---|---|
| 2026-05 | 1.1.0 (upcoming) | `requireAndMergeArrays`: path/extension validation, `$allowedBase` parameter (mitigates RCE via attacked path inclusion) |
| 2026-05 | 1.1.0 (upcoming) | `OpenSSLFileEncryption`: V2 refactor — AES-256-GCM (AEAD), KDF (Argon2id/PBKDF2 600k), `random_bytes`, soft-break for V1 backward compatibility |

For technical details: see [CHANGELOG.md](../../CHANGELOG.md) `Security` section.

## How to report a vulnerability

If you identify a security vulnerability in `oihana/php-files`, **do not open a public issue**. Contact directly via email: [marc@ooop.fr](mailto:marc@ooop.fr) with:

- Problem description;
- Reproduction steps;
- Affected version;
- Suggested mitigation if you have one.

Response within 7 days, fix published as soon as possible with attribution in the CHANGELOG (if desired).

## See also

- [OpenSSL — crypto details](openssl/README.md)
- [Archive (tar)](archive/README.md) — extraction security section
- [Path](path/README.md) — `isBasePath` for anti path-traversal
- [requireAndMergeArrays](files/reading.md#requireandmergearrays) — PHP path validation
- [Tips and pitfalls](tips.md) — broader checklist including performance
- [English TOC](README.md)
