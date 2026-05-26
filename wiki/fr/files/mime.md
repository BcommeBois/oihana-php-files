# MIME et validation

Deux fonctions pour détecter et valider les types MIME des fichiers.

- [`validateMimeType`](#validatemimetype) — vérifie que le MIME d'un fichier figure dans une liste autorisée.
- [`getImageMimeType`](#getimagemimetype) — résout et valide le MIME d'une image avec mapping format/MIME.

> 💡 Les deux fonctions s'appuient sur l'extension PHP **`ext-fileinfo`** (requise par `oihana/php-files`). Pour le catalogue typé des MIME, voir [enums.md](../enums.md) (`FileMimeType`, `ImageMimeType`, `AudioMimeType`, `VideoMimeType`).

---

## `validateMimeType`

```php
validateMimeType( string $file , array $allowedMimeTypes ) : void
```

Vérifie que le **MIME du fichier** (détecté par `mime_content_type()`) figure dans la liste fournie. Lève `FileException` sinon.

### Détection

Utilise `mime_content_type()` natif, qui s'appuie sur la base *magic* de `libmagic` via `ext-fileinfo`. La détection est basée sur le **contenu réel** du fichier (premiers octets) — un `.txt` renommé en `.pdf` est détecté comme `text/plain`.

### Normalisation de la liste

Le paramètre `$allowedMimeTypes` accepte :

- une liste plate : `['image/png', 'image/jpeg']` ;
- une liste de listes (utile pour grouper) : `[['image/png', 'image/jpeg'], ['application/pdf']]`.

Les listes imbriquées sont aplaties via `array_merge`, puis toutes les valeurs sont **lowercased** et dédupliquées.

### Comparaison

Match strict (`in_array(..., strict: true)`) sur le MIME en minuscules. **Pas de wildcard** (`image/*` n'est pas supporté — il faut lister les types explicitement).

### Exemples

```php
use function oihana\files\validateMimeType;
use oihana\files\exceptions\FileException;

// 1. Validation simple
validateMimeType( '/upload/doc.pdf' , [ 'application/pdf' ] ) ;
// OK si le fichier est bien un PDF

validateMimeType( '/upload/doc.pdf' , [ 'image/png' , 'image/jpeg' ] ) ;
// FileException: Invalid MIME type for file ".../doc.pdf".
// Expected one of [image/png, image/jpeg], but got "application/pdf".

// 2. Acceptation d'un groupe
validateMimeType( '/upload/photo.jpg' , [
    [ 'image/png' , 'image/jpeg' , 'image/webp' ] ,
    [ 'application/pdf' ] ,
]) ;
// OK pour PNG, JPEG, WEBP et PDF

// 3. Avec ImageMimeType (typed)
use oihana\files\enums\ImageMimeType;

validateMimeType( $uploadedFile , [
    ImageMimeType::JPG ,
    ImageMimeType::PNG ,
    ImageMimeType::WEBP ,
]) ;
```

### Erreurs possibles

| Cas                                       | Exception | Message |
|-------------------------------------------|-----------|---------|
| `mime_content_type()` retourne `false`    | `FileException` | "Unable to determine MIME type for file ..." |
| MIME pas dans la liste                    | `FileException` | "Invalid MIME type for file ... Expected one of [...], but got ..." |

> 💡 **Cas d'usage typique** : validation d'upload utilisateur. Combiner avec `assertFile` qui peut prendre directement un paramètre `$expectedMimeTypes` (voir [assertions.md](assertions.md#assertfile)).
>
> ```php
> assertFile( $uploadedPath , [ ImageMimeType::JPG , ImageMimeType::PNG ] ) ;
> // En une ligne : existe + lisible + MIME autorisé
> ```

---

## `getImageMimeType`

```php
getImageMimeType(
    string  $file ,
    ?string $format = null ,
    array   $allowedFormats = [ /* défauts ci-dessous */ ]
) : string
```

**Résout et valide** le MIME d'une image, avec un mapping optionnel **format attendu → MIME**.

### Mapping par défaut

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

### Logique de retour

1. **`$format` est fourni ET présent dans `$allowedFormats`** :
   - Si le MIME réel du fichier contient le MIME attendu → retourne le **MIME canonique** (`ImageMimeType::*`).
   - Sinon → retourne le **MIME réel** détecté.

2. **`$format` est null ou absent du mapping** :
   - Retourne le **MIME réel** détecté.

> 💡 La fonction permet donc :
>
> - de **canonicaliser** un MIME (par exemple, `image/x-png` détecté → retourner `image/png` si le format `'png'` est attendu) ;
> - de fallback gracieusement quand le MIME réel ne correspond pas à l'attendu (au lieu de lever une exception).

### Détection technique

Utilise `finfo_open(FILEINFO_MIME_TYPE)` + `finfo_file()` — équivalent fonctionnel de `mime_content_type()` mais plus configurable.

Appelle [`assertFile`](assertions.md#assertfile) en amont — lève `FileException` si le fichier n'existe pas ou n'est pas lisible.

### Exemples

```php
use function oihana\files\images\getImageMimeType;
use oihana\files\enums\ImageFormat;
use oihana\files\enums\ImageMimeType;

// Sans format : retourne le MIME détecté
echo getImageMimeType( '/path/to/image.png' ) ;
// → 'image/png'

// Avec format : canonicalisation
echo getImageMimeType( '/path/to/image.jpg' , 'jpg' ) ;
// → 'image/jpeg'  (canonicalisé via le mapping JPG)

// Avec ImageFormat enum
echo getImageMimeType( $path , ImageFormat::WEBP ) ;
// → 'image/webp'

// Mapping custom (limiter aux formats acceptés)
echo getImageMimeType( $path , 'png' , [
    ImageFormat::PNG  => ImageMimeType::PNG ,
    ImageFormat::JPEG => ImageMimeType::JPEG ,
]) ;
// Refuse implicitement les autres formats — si le fichier est WEBP, retourne le MIME réel ('image/webp')
```

### Cas d'usage : pipeline d'upload

```php
use function oihana\files\images\getImageMimeType;
use function oihana\files\validateMimeType;
use oihana\files\enums\ImageMimeType;

function handleUpload( string $uploadPath , string $declaredFormat ) {
    $mime = getImageMimeType( $uploadPath , $declaredFormat ) ;

    // Vérifier que le MIME canonique fait partie d'une whitelist stricte
    validateMimeType( $uploadPath , [
        ImageMimeType::JPG ,
        ImageMimeType::PNG ,
        ImageMimeType::WEBP ,
    ]) ;

    // À ce stade, on a un MIME canonique et un fichier accepté
    // ...
}
```

---

## Comparaison rapide

| Fonction             | Détection MIME      | Whitelist | Mapping format→MIME | Lève exception |
|----------------------|---------------------|-----------|---------------------|---|
| `validateMimeType`   | `mime_content_type` | Oui (obligatoire) | Non | Si pas dans liste |
| `getImageMimeType`   | `finfo_*` | Indirect (via `$allowedFormats`) | Oui | Si fichier inexistant |
| `assertFile`         | Délègue à `validateMimeType` (3e arg) | Oui (optionnel) | Non | Si validation échoue |

---

## Voir aussi

- [Assertions](assertions.md#assertfile) — `assertFile` avec validation MIME intégrée.
- [Énumérations](../enums.md) — `FileMimeType` (catalogue complet), `ImageMimeType`, `AudioMimeType`, `VideoMimeType`, `ImageFormat`.
- [Exceptions](../exceptions.md) — `FileException`.
- [Vue d'ensemble](README.md).
