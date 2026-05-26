# MIME and validation

Two functions to detect and validate MIME types of files.

- [`validateMimeType`](#validatemimetype) — checks that a file's MIME is in an allowed list.
- [`getImageMimeType`](#getimagemimetype) — resolves and validates an image's MIME with format/MIME mapping.

> 💡 Both functions rely on the PHP **`ext-fileinfo`** extension (required by `oihana/php-files`). For the typed MIME catalogue, see [enums.md](../enums.md) (`FileMimeType`, `ImageMimeType`, `AudioMimeType`, `VideoMimeType`).

---

## `validateMimeType`

```php
validateMimeType( string $file , array $allowedMimeTypes ) : void
```

Checks that the **file's MIME** (detected via `mime_content_type()`) is in the provided list. Throws `FileException` otherwise.

### Detection

Uses native `mime_content_type()`, which relies on `libmagic`'s *magic* database via `ext-fileinfo`. Detection is based on **actual content** (first bytes) — a `.txt` renamed `.pdf` is detected as `text/plain`.

### List normalisation

The `$allowedMimeTypes` parameter accepts:

- a flat list: `['image/png', 'image/jpeg']`;
- a list of lists (useful for grouping): `[['image/png', 'image/jpeg'], ['application/pdf']]`.

Nested lists are flattened via `array_merge`, then all values are **lowercased** and de-duplicated.

### Comparison

Strict match (`in_array(..., strict: true)`) on the lowercased MIME. **No wildcards** (`image/*` is not supported — list types explicitly).

### Examples

```php
use function oihana\files\validateMimeType;
use oihana\files\exceptions\FileException;

// 1. Simple validation
validateMimeType( '/upload/doc.pdf' , [ 'application/pdf' ] ) ;
// OK if the file really is a PDF

validateMimeType( '/upload/doc.pdf' , [ 'image/png' , 'image/jpeg' ] ) ;
// FileException: Invalid MIME type for file ".../doc.pdf".
// Expected one of [image/png, image/jpeg], but got "application/pdf".

// 2. Group acceptance
validateMimeType( '/upload/photo.jpg' , [
    [ 'image/png' , 'image/jpeg' , 'image/webp' ] ,
    [ 'application/pdf' ] ,
]) ;
// OK for PNG, JPEG, WEBP and PDF

// 3. With typed ImageMimeType
use oihana\files\enums\ImageMimeType;

validateMimeType( $uploadedFile , [
    ImageMimeType::JPG ,
    ImageMimeType::PNG ,
    ImageMimeType::WEBP ,
]) ;
```

### Possible errors

| Case                                       | Exception | Message |
|--------------------------------------------|-----------|---------|
| `mime_content_type()` returns `false`      | `FileException` | "Unable to determine MIME type for file ..." |
| MIME not in the list                       | `FileException` | "Invalid MIME type for file ... Expected one of [...], but got ..." |

> 💡 **Typical use case**: user upload validation. Combine with `assertFile` which can take an `$expectedMimeTypes` parameter directly (see [assertions.md](assertions.md#assertfile)).
>
> ```php
> assertFile( $uploadedPath , [ ImageMimeType::JPG , ImageMimeType::PNG ] ) ;
> // One line: exists + readable + MIME allowed
> ```

---

## `getImageMimeType`

```php
getImageMimeType(
    string  $file ,
    ?string $format = null ,
    array   $allowedFormats = [ /* defaults below */ ]
) : string
```

**Resolves and validates** an image's MIME, with an optional **expected-format → MIME** mapping.

### Default mapping

```php
[
    ImageFormat::AVIF => ImageMimeType::AVIF , // 'image/avif'
    ImageFormat::JPG  => ImageMimeType::JPG  , // 'image/jpeg'
    ImageFormat::JPEG => ImageMimeType::JPEG , // 'image/jpeg'
    ImageFormat::PNG  => ImageMimeType::PNG  , // 'image/png'
    ImageFormat::GIF  => ImageMimeType::GIF  , // 'image/gif'
    ImageFormat::SVG  => ImageMimeType::SVG  , // 'image/svg+xml'
    ImageFormat::WEBP => ImageMimeType::WEBP , // 'image/webp'
]
```

### Return logic

1. **`$format` is provided AND present in `$allowedFormats`**:
   - If the file's actual MIME contains the expected MIME → returns the **canonical MIME** (`ImageMimeType::*`).
   - Otherwise → returns the **actual** detected MIME.

2. **`$format` is null or absent from the mapping**:
   - Returns the **actual** detected MIME.

> 💡 The function thus allows:
>
> - **canonicalising** a MIME (e.g., detected `image/x-png` → return `image/png` if `'png'` is the expected format);
> - falling back gracefully when the actual MIME does not match the expected one (instead of throwing).

### Technical detection

Uses `finfo_open(FILEINFO_MIME_TYPE)` + `finfo_file()` — functional equivalent of `mime_content_type()` but more configurable.

Calls [`assertFile`](assertions.md#assertfile) upstream — throws `FileException` if the file does not exist or is not readable.

### Examples

```php
use function oihana\files\images\getImageMimeType;
use oihana\files\enums\ImageFormat;
use oihana\files\enums\ImageMimeType;

// Without format: returns the detected MIME
echo getImageMimeType( '/path/to/image.png' ) ;
// → 'image/png'

// With format: canonicalisation
echo getImageMimeType( '/path/to/image.jpg' , 'jpg' ) ;
// → 'image/jpeg'  (canonicalised via JPG mapping)

// With ImageFormat enum
echo getImageMimeType( $path , ImageFormat::WEBP ) ;
// → 'image/webp'

// Custom mapping (restrict accepted formats)
echo getImageMimeType( $path , 'png' , [
    ImageFormat::PNG  => ImageMimeType::PNG ,
    ImageFormat::JPEG => ImageMimeType::JPEG ,
]) ;
// Implicitly refuses other formats — if the file is WEBP, returns the actual MIME ('image/webp')
```

### Use case: upload pipeline

```php
use function oihana\files\images\getImageMimeType;
use function oihana\files\validateMimeType;
use oihana\files\enums\ImageMimeType;

function handleUpload( string $uploadPath , string $declaredFormat ) {
    $mime = getImageMimeType( $uploadPath , $declaredFormat ) ;

    // Verify the canonical MIME against a strict whitelist
    validateMimeType( $uploadPath , [
        ImageMimeType::JPG ,
        ImageMimeType::PNG ,
        ImageMimeType::WEBP ,
    ]) ;

    // At this point, we have a canonical MIME and an accepted file
    // ...
}
```

---

## Quick comparison

| Function             | MIME detection      | Whitelist | Format→MIME mapping | Throws |
|----------------------|---------------------|-----------|---------------------|---|
| `validateMimeType`   | `mime_content_type` | Yes (mandatory) | No | If not in list |
| `getImageMimeType`   | `finfo_*` | Indirect (via `$allowedFormats`) | Yes | If file does not exist |
| `assertFile`         | Delegates to `validateMimeType` (3rd arg) | Yes (optional) | No | If validation fails |

---

## See also

- [Assertions](assertions.md#assertfile) — `assertFile` with built-in MIME validation.
- [Enums](../enums.md) — `FileMimeType` (full catalogue), `ImageMimeType`, `AudioMimeType`, `VideoMimeType`, `ImageFormat`.
- [Exceptions](../exceptions.md) — `FileException`.
- [Overview](README.md).
