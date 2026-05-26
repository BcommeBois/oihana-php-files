# Assertions

Trois fonctions qui **lèvent une exception** si un fichier ou un dossier n'est pas dans l'état attendu. À utiliser systématiquement en début de fonction pour garantir des préconditions et éviter d'enchaîner des `if (!is_file(...)) throw ...`.

- [`assertFile`](#assertfile) — fichier existant + accessibilité + MIME optionnel.
- [`assertDirectory`](#assertdirectory) — dossier existant + accessibilité + permissions optionnelles.
- [`assertWritableDirectory`](#assertwritabledirectory) — raccourci pour `assertDirectory( ..., isWritable: true )`.

> 💡 Toutes les assertions ne **retournent rien** (`void`) — leur effet de bord est la **garantie d'état** après l'appel. Si elles reviennent normalement, tu peux faire tes opérations sans vérifier à nouveau.

---

## `assertFile`

```php
assertFile(
    ?string $file ,
    ?array  $expectedMimeTypes = null ,
    bool    $isReadable        = true ,
    bool    $isWritable        = false
) : void
```

Vérifie qu'un fichier :

1. **n'est ni `null` ni vide** (après `trim`) ;
2. **existe et est un fichier** (pas un dossier ni un device) — `is_file()` ;
3. **est lisible** (si `$isReadable`, défaut `true`) ;
4. **est inscriptible** (si `$isWritable`, défaut `false`) ;
5. **a un MIME type acceptable** (si `$expectedMimeTypes` n'est pas vide).

**Lève `FileException`** dès qu'une vérification échoue, avec un message explicite désignant le chemin.

### Usage de base

```php
use function oihana\files\assertFile;

assertFile( '/etc/hosts' ) ;
// OK : ne fait rien

assertFile( '/path/missing.txt' ) ;
// FileException: The file path "/path/missing.txt" is not a valid file.
```

### Vérification du MIME type

```php
assertFile( '/upload/document.pdf' , [ 'application/pdf' ] ) ;
// OK si le fichier est bien un PDF

assertFile( '/upload/document.pdf' , [ 'image/png' , 'image/jpeg' ] ) ;
// FileException : le MIME ne correspond pas
```

> 💡 La vérification MIME utilise [`validateMimeType`](mime.md#validatemimetype) en interne, qui s'appuie sur l'extension `ext-fileinfo`. Voir [mime.md](mime.md) pour les détails.

### Vérification d'écriture

```php
assertFile( '/etc/config.ini' , null , isReadable: true , isWritable: true ) ;
// Lève FileException si tu n'as pas les droits d'écriture
```

### Cas d'erreur explicites

```php
assertFile( null ) ;
// FileException: The file path must not be null.

assertFile( '' ) ;
// FileException: The file path must not be empty.

assertFile( '   ' ) ;
// FileException: The file path must not be empty. (trim appliqué)

assertFile( '/var/log' ) ;
// FileException: The file path "/var/log" is not a valid file.
//   (c'est un dossier, pas un fichier)
```

---

## `assertDirectory`

```php
assertDirectory(
    ?string $path ,
    bool    $isReadable          = true ,
    bool    $isWritable          = false ,
    ?int    $expectedPermissions = null
) : void
```

Vérifie qu'un dossier :

1. **n'est ni `null` ni vide** (après `trim`) ;
2. **existe et est un dossier** — `is_dir()` ;
3. **est lisible** (si `$isReadable`, défaut `true`) ;
4. **est inscriptible** (si `$isWritable`, défaut `false`) ;
5. **a les permissions exactes attendues** (si `$expectedPermissions` est fourni — mask `0o777`).

**Lève `DirectoryException`** dès qu'une vérification échoue.

### Usage de base

```php
use function oihana\files\assertDirectory;

assertDirectory( '/var/www' ) ;
// OK

assertDirectory( '/var/www' , isWritable: true ) ;
// Lève DirectoryException si non-inscriptible
```

### Vérification des permissions

```php
assertDirectory( '/var/log/myapp' , true , true , 0755 ) ;
// Vérifie : lisible + inscriptible + permissions exactement 0755

// Si /var/log/myapp est en 0777 :
// DirectoryException: The directory "..." has permissions "777", expected "755".
```

> ⚠ La comparaison est **stricte sur le masque `0o777`** (les bits SUID/SGID/sticky sont ignorés). Utile pour audit de sécurité.

### Workflow typique

```php
try {
    assertDirectory( $userInput , isWritable: true ) ;
    // À partir d'ici, tu sais que $userInput est un dossier inscriptible
    file_put_contents( $userInput . '/output.txt' , $data ) ;
}
catch ( DirectoryException $e ) {
    http_response_code( 403 ) ;
    echo $e->getMessage() ;
}
```

---

## `assertWritableDirectory`

```php
assertWritableDirectory( ?string $directory ) : void
```

**Raccourci** strictement équivalent à :

```php
assertDirectory( $directory , isWritable: true ) ;
```

À utiliser quand l'intent est *"je vais écrire ici, refuse si pas possible"* — la lecture du code est plus claire.

```php
use function oihana\files\assertWritableDirectory;

assertWritableDirectory( sys_get_temp_dir() ) ;
// OK (le temp dir est toujours inscriptible)

assertWritableDirectory( '/etc' ) ;
// DirectoryException si pas root
```

---

## Combinaisons et anti-patterns

### ✅ Combiner assertions avant opération

```php
// Pour copier un fichier vers un dossier :
assertFile( $src , isReadable: true ) ;
assertWritableDirectory( $destDir ) ;
copy( $src , $destDir . '/' . basename( $src ) ) ;
```

### ❌ Anti-pattern : try/catch interne pour ignorer

```php
// MAUVAIS : on perd l'info d'erreur sans même la logger
try { assertFile( $path ) ; } catch ( FileException $e ) {}
```

→ Préférer la branche `assertable: false` des fonctions destructives (`deleteFile`, `clearFile`) si tu veux *tenter* sans lever d'exception.

---

## Voir aussi

- [Création](creation.md) — `makeFile`, `makeDirectory` (qui valident eux aussi en interne).
- [Suppression](deletion.md) — `deleteFile`, `deleteDirectory` (qui acceptent un paramètre `$assertable`).
- [MIME](mime.md) — `validateMimeType` utilisé par `assertFile`.
- [Exceptions](../exceptions.md) — détail de `FileException` et `DirectoryException`.
- [Vue d'ensemble](README.md).
