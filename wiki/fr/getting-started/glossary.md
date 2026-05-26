# Glossaire

Termes récurrents utilisés dans cette documentation et dans le code de `oihana/php-files`. Tri alphabétique.

## A

### Absolu (chemin)

Chemin qui commence par un **séparateur racine** (`/` sur Unix), une **lettre de lecteur** (`C:\` sur Windows), ou un **scheme** (`phar://`, `file://`). Opposé : *relatif*.

Voir [`isAbsolutePath`](../path/absolute-vs-relative.md) pour la détection, [`makeAbsolute`](../path/absolute-vs-relative.md) pour la conversion.

### Assertion

Fonction qui **lance une exception** si une condition n'est pas remplie. Convention dans `oihana/php-files` : préfixe `assert` (`assertFile`, `assertDirectory`, `assertWritableDirectory`, `assertPhar`, `assertTar`). Pas de valeur de retour utile — l'idée est de garantir l'état du système après l'appel.

```php
assertFile('/etc/hosts') ; // OK : ne fait rien si le fichier existe
assertFile('/foo')       ; // FileException levée
```

### Autoload (`composer.autoload.files`)

Mécanisme Composer qui **inclut automatiquement** un fichier PHP au démarrage de chaque requête (avant `vendor/autoload.php`). Utilisé par `oihana/php-files` pour rendre les **fonctions standalone** (`joinPaths`, `findFiles`, etc.) disponibles sans nécessiter un `use function` ni un `require` manuel — il suffit de les appeler avec leur namespace complet (ou un alias `use`).

Voir [`composer.json`](../../composer.json) section `autoload.files`.

## C

### Canonical path (chemin canonique)

Forme **normalisée** d'un chemin :

- les séparateurs sont uniformisés (`/` partout, même sur Windows en interne) ;
- les segments `.` (dossier courant) sont supprimés ;
- les segments `..` (dossier parent) sont résolus quand c'est possible ;
- les barres obliques multiples sont compactées (`//` → `/`).

⚠ Différent de `realpath()` natif PHP, qui **résout les symlinks** et **vérifie l'existence**. `canonicalizePath` est purement textuel : il fonctionne sur des chemins inexistants.

Voir [`canonicalizePath`](../path/joining-and-normalizing.md).

### Cipher (chiffrement symétrique)

Algorithme de chiffrement utilisé par `OpenSSLFileEncryption`. Par défaut **`aes-256-cbc`** (Advanced Encryption Standard, clé 256 bits, mode Cipher Block Chaining). Tout algorithme listé par `openssl_get_cipher_methods()` est accepté.

## E

### Extension de fichier

Suffixe d'un nom de fichier précédé d'un point : `.txt`, `.tar.gz`, `.cose`, etc. Le catalogue exhaustif vit dans l'énumération `FileExtension`. Pour les archives tar, voir `TarExtension`.

Note : `.tar.gz` est une **extension composée** — `getFileExtension` retourne `tar.gz` (pas `gz`) quand l'option appropriée est passée.

## F

### Filesystem virtuel (vfs)

Système de fichiers **simulé en mémoire**, sans I/O réelle sur le disque. Fourni par `mikey179/vfsstream` (dépendance de tests). Permet d'écrire des tests rapides, déterministes et sans nettoyage. La librairie utilise massivement vfsstream pour ses tests internes.

## G

### Glob (motif)

Syntaxe de motif de fichiers basée sur des **jokers** : `*` (zéro ou plusieurs caractères sauf `/`), `?` (un caractère), `[abc]` (un caractère parmi un ensemble), `{a,b}` (alternatives).

Exemples : `*.php`, `test_*.txt`, `src/**/*.md` (récursif avec certaines implémentations).

Distinct de la **regex** (expression régulière), qui utilise une syntaxe différente (`^`, `$`, `.+`, `\w`, etc.). `findFiles` détecte automatiquement le format via `isRegexp` de `oihana/php-core`.

## H

### Hash / Hashage

Fonction de transformation **non réversible** d'une chaîne en empreinte de taille fixe. Distinct du chiffrement (qui est réversible). `oihana/php-files` ne fournit pas de helper de hash — utilise `hash_file()` natif pour cela.

## I

### IV (Initialization Vector)

Bloc de bits **aléatoires** mélangés au premier bloc en clair lors d'un chiffrement symétrique en mode CBC (Cipher Block Chaining). Garantit que deux chiffrements du même fichier avec la même clé donnent des sorties différentes.

`OpenSSLFileEncryption` **préfixe automatiquement le IV** dans le fichier de sortie chiffré (16 bytes pour AES). Le déchiffrement le récupère depuis le début du fichier — l'utilisateur n'a jamais à le manipuler.

## L

### Local (chemin local)

Chemin qui pointe vers le **système de fichiers de la machine** (pas une URL distante). Cela inclut `/var/www`, `C:\Users`, `phar://`. Cela **exclut** `https://`, `ftp://`, `s3://`.

Voir [`isLocalPath`](../path/inspection.md).

## M

### MIME type (Multipurpose Internet Mail Extensions)

Identifiant standardisé du **type de contenu** d'un fichier : `image/jpeg`, `application/json`, `text/plain`, etc. Standardisé par l'IANA.

`oihana/php-files` expose un catalogue typé :

- `FileMimeType` — tous les types (web + spécialisés).
- `ImageMimeType` — uniquement images.
- `AudioMimeType` — uniquement audio.
- `VideoMimeType` — uniquement vidéo.

La détection runtime se fait via `ext-fileinfo` (`mime_content_type`).

## N

### Normaliser (un chemin)

Action de **réécrire un chemin sous une forme standard** : conversion des séparateurs en `/`, suppression des `.` et `..` résolvables, compaction des `//` multiples. Synonyme partiel de *canonicaliser*. Voir [`normalizePath`](../path/joining-and-normalizing.md).

## O

### Options (pattern)

Convention dans `oihana/php-files` (et `oihana/*` plus largement) consistant à **passer la configuration d'une fonction sous forme de tableau associatif** dont les clés sont des **constantes d'énumération**.

Exemple :

```php
findFiles( $dir, [
    FindFilesOption::RECURSIVE => true,
    FindFilesOption::PATTERN   => '*.php',
    FindFilesOption::ORDER     => Order::ASC,
]) ;
```

Préféré à : un constructeur à 10 paramètres positionnels, ou à un *fluent builder*.

Pour les configurations plus structurées (hydratées, sérialisables, formatables), voir la classe abstraite [`Options`](../options/README.md).

## P

### Phar (PHP Archive)

Format d'archive **exécutable** propre à PHP. Un `.phar` est un ZIP/TAR (au choix) avec un *stub* PHP en en-tête, qui peut être inclus avec `require` ou exécuté avec `php archive.phar`. Le scheme `phar://` permet d'**accéder à un fichier interne** sans extraction (`phar:///app/bundle.phar/src/Main.php`).

`oihana/php-files` fournit des helpers Phar dans [`phar/`](../phar/README.md) et s'appuie sur la classe native `PharData` pour les archives tar.

## R

### Relatif (chemin)

Chemin qui ne commence ni par `/`, ni par une lettre de lecteur, ni par un scheme. Sa résolution dépend du **dossier courant** (`getcwd()`) ou d'un **dossier de base** explicite. Voir [`isRelativePath`](../path/absolute-vs-relative.md), [`makeRelative`](../path/absolute-vs-relative.md).

## S

### Scheme

Préfixe d'un chemin/URL identifiant son **protocole** ou **wrapper** PHP : `file://`, `phar://`, `http://`, `https://`, `s3://`, etc. `oihana/php-files` préserve le scheme à travers les opérations de jointure et de normalisation.

Voir [`getSchemeAndHierarchy`](../files/system.md).

### Symlink (lien symbolique)

Fichier spécial Unix qui **pointe vers un autre chemin**. Sur Windows, équivalent géré depuis Vista. La librairie expose une option `followLinks` sur `findFiles` et `recursiveFilePaths` pour décider si on traverse ou non les symlinks lors d'un parcours récursif.

⚠ Attention aux **boucles infinies** quand `followLinks = true` sur des symlinks circulaires. PHP les détecte avec `SKIP_DOTS` mais le coût mémoire augmente.

## T

### Tar (Tape Archive)

Format d'archive Unix historique. Concatène plusieurs fichiers en un seul, avec leurs métadonnées (taille, perms, owner). **Pas compressé par défaut** — d'où les variantes `tar.gz` (gzip) et `tar.bz2` (bzip2) très courantes.

`oihana/php-files` utilise la classe native PHP `PharData` pour créer/extraire des tars. Voir [`tar`](../archive/tar.md), [`untar`](../archive/untar.md).

### Temporaire (fichier/dossier)

Élément créé dans le **dossier temporaire du système** (`/tmp` sur Unix, `%TEMP%` sur Windows), retrouvable via `sys_get_temp_dir()`. `oihana/php-files` ajoute :

- `getTemporaryDirectory` — lecture du dossier temp.
- `makeTemporaryDirectory` — création d'un sous-dossier temporaire unique.
- `deleteTemporaryDirectory` — suppression sécurisée (vérifie que le chemin est bien sous `sys_get_temp_dir()`).

### Timestamped (fichier/dossier horodaté)

Fichier ou dossier dont le nom inclut une **date/heure** au format paramétrable : `backup-2026-05-26.tar.gz`, `log-20260526-153012.txt`, etc. Pratique pour les sauvegardes et les *rotations* simples. Voir `makeTimestampedFile`, `makeTimestampedDirectory`.

## V

### vfsStream

Voir [Filesystem virtuel](#filesystem-virtuel-vfs).

## Et la suite ?

- [Introduction](introduction.md) — vue d'ensemble.
- [Installation](installation.md) — installer la librairie.
- [Dépendances](dependencies.md) — packages tirés par Composer.
- [Sommaire FR](../README.md) — table des matières complète.
