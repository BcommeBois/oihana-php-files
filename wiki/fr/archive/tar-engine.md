# Comment une archive tar est construite, et extraite

[`tar`](tar.md#tar), [`tarDirectory`](tar.md#tardirectory) et [`untar`](untar.md#untar) confient
le travail au `tar` du système quand elles y trouvent un **GNU tar**, et retombent sur `PharData`
sinon.

Les archives sont les mêmes dans les deux cas — mêmes entrées, mêmes noms, interchangeables
avec tout ce qu'une version antérieure de cette bibliothèque a écrit — et les arbres extraits
aussi. Rien ne change dans l'API, et il n'y a rien à configurer.

> Le choix du moteur à l'écriture date de la **1.3.0**, celui à l'extraction de la **1.5.0**, et
> c'est un défaut qui l'a imposé : entre les deux, la bibliothèque ne savait plus relire certaines
> archives qu'elle venait d'écrire. [Voir plus bas](#et-à-lextraction-ce-nest-pas-la-vitesse).

## Pourquoi

`PharData` écrit les archives tar en PHP pur. Sur un petit arbre, ça ne se voit pas. Sur un vrai,
si :

| Arbre | GNU tar | `PharData` |
|---|---|---|
| 2,8 Mo / 365 fichiers | 0,03 s | 0,60 s |
| 96 Mo / 7 554 fichiers | **2,1 s** | **311,8 s** |

La même archive sort des deux. L'écart n'est pas constant : il grandit avec la taille, parce que
`PharData` écrit le tar puis le relit intégralement pour le compresser.

Il y a aussi un aspect justesse. `PharData` produit le format `ustar`, dont l'en-tête porte un
préfixe de répertoire de 155 octets et un composant final de 100. Un **chemin** peut donc être
long, mais pas un **nom de fichier** : au-delà de 100 octets, `PharData` refuse l'archive plutôt
que de tronquer le nom.

Ce n'est pas un cas d'école. Une installation WordPress standard contient un tel fichier, de
103 octets, dans une extension très répandue — assez pour que le site entier ne puisse pas être
archivé. GNU tar l'écrit sans broncher, avec les extensions que le format a justement acquises
pour ça.

## Et à l'extraction, ce n'est pas la vitesse

À l'extraction, `PharData` tient parfaitement la comparaison : **0,98 s contre 0,82 s** pour GNU
tar sur la même archive de 57 Mo. Si l'extraction a quand même changé de moteur, c'est pour une
autre raison, et elle tient à une seule ligne — `PharData::decompress()` demande à `PharData`
d'**écrire** un `.tar` avant de le lire.

Donc la limite des 100 octets ci-dessus, qui est une limite d'**écriture**, s'appliquait aussi à
la **lecture** d'une archive compressée. Mesuré le 2026-10-24 : un `.tar.gz` contenant un nom de
154 octets refusait de s'extraire, avec le message
`filename ... is too long for tar file format`, alors que la **même archive non compressée**
s'extrayait sans problème.

Autrement dit : depuis la 1.3.0, `tar()` écrivait par GNU tar des archives que `untar()` ne savait
plus relire. Et comme un WordPress standard contient un tel chemin, une sauvegarde pouvait être
écrite sans pouvoir être restaurée — le message, lui, se lisait comme une archive abîmée.

Trois autres coûts venaient de la même reconstruction :

| Coût | Mesure |
|---|---|
| Mémoire | **× 2,26** d'une archive de 60 Mo, **× 2,04** d'une de 200 Mo — soit ~5 Go pour une sauvegarde de 2,45 Go |
| Disque | une copie **décompressée** écrite à côté de l'archive, le temps de l'extraction |
| Répétition | extraire deux fois la même archive compressée dans un processus échouait, le `.tar` restant inscrit dans une liste interne de `Phar` |

Le dernier est contourné plutôt que nettoyé : [`untarUniqueAlias`](#les-fonctions-du-moteur) donne
à l'archive un lien dur à nom unique — aucun octet copié — dont le `.tar` hérite.
`Phar::unlinkArchive()`, la méthode documentée pour vider cette liste, **corrompt le tas de Zend**
appelée depuis `untar()`.

## Quel binaire, et pourquoi pas tous

| Plateforme | Moteur | Pourquoi |
|---|---|---|
| **Debian / Ubuntu / la plupart des Linux** | GNU tar | Stocke les noms en octets bruts, exactement comme `PharData` |
| **macOS** | `PharData` | `/usr/bin/tar` est `bsdtar` — sauf `gtar` installé, [voir plus bas](#sur-un-mac) |
| **Windows** | `PharData` | `tar.exe` est également `bsdtar` |
| **Alpine / BusyBox** | `PharData` | Implémentation réduite, fidélité non vérifiée |

Seul GNU tar est accepté, et on le lui demande au lieu de le supposer : le binaire est exécuté
avec `--version` et doit le dire lui-même.

La raison tient à l'encodage des noms. Vérifié sur un arbre de noms accentués, idéographiques,
avec guillemets et espaces, plus un répertoire vide, un lien symbolique et un chemin profond :
GNU tar et `PharData` produisent des **listes d'entrées identiques**. `bsdtar` non : sur macOS il
normalise les noms en **NFD**, si bien que `été.txt` entre dans l'archive sous la forme
`e´te´.txt`. Une archive écrite sur un Mac puis restaurée sur un serveur porterait des noms
différents des originaux — pour un site aux médias accentués, des URL différentes.

Plus lent et identique vaut mieux que plus rapide et subtilement différent.

### Sur un Mac

```shell
brew install gnu-tar
```

Homebrew installe un vrai GNU tar sous le nom `gtar`, sans toucher au `/usr/bin/tar` du système —
dans `/opt/homebrew/bin/gtar` sur Apple Silicon, dans `/usr/local/bin/gtar` sur Intel. Les deux
chemins sont cherchés : il n'y a rien à configurer, le moteur binaire prend le relais.

C'est un confort de développement — des archives construites en secondes plutôt qu'en minutes, et
la [suite de tests](../testing.md#lancer-les-tests) qui ne se skippe plus — pas un changement de
doctrine : un Mac reste une machine de développement plutôt qu'une cible de sauvegarde.

## Quand le moteur binaire s'efface

Le moteur est choisi **avant que quoi que ce soit ne soit écrit**, et jamais remis en cause
ensuite. Il s'efface quand :

- il n'y a pas de GNU tar ;
- **à l'écriture seulement**, plusieurs chemins sont archivés ensemble sans parent commun — une
  invocation de `tar` couvre un répertoire de base, et on ne peut pas ajouter à une archive déjà
  compressée ;
- le compresseur manque. `tar` ne compresse ni ne décompresse : il passe par `gzip`, `bzip2`,
  `xz` ou `zstd`, là où `PharData` utilise les extensions PHP. Une machine avec `ext-zlib` et sans
  programme `gzip` a donc un GNU tar parfaitement valide qui ne sait pas ouvrir un `.tar.gz`, et
  garde le moteur qui fonctionne pour elle.

Pour `xz` et `zstd`, il n'y a pas de repli : `PharData` ne connaît ni l'un ni l'autre. Ces deux
formats n'existent donc **que** par le `tar` du système, à l'écriture comme à la lecture, et les
deux bouts le disent clairement plutôt que de laisser `Phar` parler d'un format que personne n'a
mentionné. C'est le même piège inter-machines qu'une archive Argon2id sur une machine sans
`ext-sodium`.

> ⚠ `proc_open()` à qui l'on donne un environnement **remplace** celui du processus. Comme `tar`
> lance son compresseur par un shell, un enfant sans `PATH` retombait sur le défaut du shell —
> `/usr/bin:/bin` sur un Mac — et trouvait `gzip` sans trouver `xz` installé ailleurs, alors que
> `tarCompressorExists()` venait de le trouver sur le `PATH` de **ce** processus. Le contrôle
> regardait un environnement et le travail tournait dans un autre. Corrigé en 1.5.0 par
> `tarProcessEnvironment()`.

Un binaire présent qui **échoue** lève au lieu de se rabattre. Il signale quelque chose que
`PharData` rencontrerait aussi — un disque plein, un chemin illisible — et réessayer en PHP
passerait des minutes à atteindre le même mur, en jetant la cause d'origine.

## Ce que PharData ne peut pas écrire

Deux choses, et dans les deux cas il **refuse** plutôt que d'écrire autre chose.

**Un nom de plus de 100 octets** — la limite `ustar` vue plus haut.

**Un lien symbolique.** Phar n'expose aucun moyen d'écrire une entrée de type lien : il n'y a pas
de `addSymlink`, et le drapeau de type `ustar` est hors de portée de l'API. Avant la 1.6.0, cette
branche stockait autre chose à la place — un dossier vide pour un lien vers un dossier, les octets
de la cible pour un lien vers un fichier, une erreur `phar` pour un lien orphelin. Le même arbre
donnait donc une archive par le binaire et une autre par `PharData`, sans un mot. `tar()` lit
maintenant l'arbre **avant d'écrire quoi que ce soit** et refuse en nommant le lien, avec les trois
issues : installer GNU tar, archiver les chemins un par un pour qu'il soit utilisé, ou passer par
[`zip`](zip.md#zip) — les trois gardent le lien comme lien.

> ⚠ Le troisième cas de la liste ci-dessus est le piège : **plusieurs chemins sans parent commun**
> envoient le travail à `PharData` sur n'importe quelle machine, GNU tar installé ou non. Un lien
> archivé seul est gardé ; le même lien archivé avec un chemin d'un autre parent est refusé.

**Et il ne peut pas en lire un non plus.** Son itérateur rapporte une entrée lien comme un fichier
normal de 0 octet, `isLink()` répond faux, et `extractTo()` écrit un fichier vide à sa place. Donc
[`untar`](untar.md#liens-symboliques) refuse aussi une archive qui contient un lien quand c'est ce
moteur qui lit — en lisant les en-têtes de l'archive **avant** que `PharData::decompress()` ne la
réécrive et n'efface le lien qu'il aurait fallu refuser.

## Savoir quel moteur est en place

```php
use function oihana\files\archive\tar\tarBinary ;

$binary = tarBinary() ;

echo $binary === null
    ? 'les archives sont construites en PHP — lent sur les gros arbres'
    : sprintf( 'les archives sont construites par %s' , $binary ) ;
```

À faire remonter là où un exploitant regarde vraiment : un contrôle de santé, une commande
`doctor`, un journal de démarrage. Une machine qui se rabat produit des archives qui risquent de
ne plus tenir dans la fenêtre qu'on leur donne, et rien d'autre ne le dira. Une page comme
celle-ci se lit une fois ; un contrôle de santé se lit tous les jours.

`tarBinary()` met sa réponse en cache ; passer `refresh: true` pour chercher de nouveau.

## Les fonctions du moteur

Rarement appelées directement — `tar()` et `untar()` s'en servent — mais publiques, et utiles pour
un contrôle de santé.

| Fonction | Réponse |
|---|---|
| [`tarBinary()`](#savoir-quel-moteur-est-en-place) | le GNU tar trouvé, ou `null` |
| `tarWritableCompressions()` | ce que **cette machine** sait écrire — `['none','gzip','bzip2']` sans binaire, plus `xz` et `zstd` avec |
| `tarProcessEnvironment()` | l'environnement donné à chaque `tar` lancé : `LC_ALL=C` et le `PATH` de ce processus |
| `tarBinaryIsUsable( $chemin )` | ce binaire est-il un GNU tar exécutable — on le lui demande avec `--version` |
| `tarBinaryHandles( $entrées , $compression )` | ce binaire peut-il **écrire** cette archive |
| `tarCompressorExists( $compression )` | le programme de compression est-il présent |
| `tarCompressionOfFile( $archive )` | comment cette archive est compressée, lu dans ses **octets** et non dans son nom |
| `untarBinaryHandles( $archive )` | ce binaire peut-il **extraire** cette archive, décompresseur compris |
| `untarEntries( $binaire , $archive )` | ce que l'archive contient — noms, tailles — en une passe de flux |
| `untarAssertExtractable( $entrées , $sortie , $overwrite , $max )` | les gardes communes aux deux moteurs : traversée, écrasement, bombe |
| `untarWithBinary( $binaire , $archive , $sortie , $permissions )` | l'extraction elle-même |
| `untarUniqueAlias( $archive , $compression )` | un lien dur à nom unique, pour que `PharData` puisse décompresser deux fois |

## Forcer un choix

`OIHANA_TAR_BINARY` remplace la recherche :

| Valeur | Effet |
|---|---|
| absente | Chercher un GNU tar aux endroits habituels |
| un chemin | Utiliser ce binaire, s'il s'agit d'un GNU tar utilisable |
| vide | Construire en PHP avec `PharData` |

La forme vide est celle qu'utilise la suite de tests de cette bibliothèque pour faire passer les
mêmes arbres témoins dans les deux moteurs et comparer ce qui en sort — à l'écriture comme à
l'extraction : la suite entière tourne deux fois, avec et sans binaire.

## Voir aussi

- [Créer une archive tar](tar.md)
- [Extraire une archive tar](untar.md)
- [Vue d'ensemble des archives](README.md)
