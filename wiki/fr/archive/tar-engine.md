# Comment une archive tar est construite

[`tar`](tar.md#tar) et [`tarDirectory`](tar.md#tardirectory) confient le travail au `tar` du
système quand elles y trouvent un **GNU tar**, et construisent l'archive en PHP avec `PharData`
sinon.

Les archives sont les mêmes dans les deux cas — mêmes entrées, mêmes noms, interchangeables
avec tout ce qu'une version antérieure de cette bibliothèque a écrit. Rien ne change dans l'API,
et il n'y a rien à configurer.

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

## Quel binaire, et pourquoi pas tous

| Plateforme | Moteur | Pourquoi |
|---|---|---|
| **Debian / Ubuntu / la plupart des Linux** | GNU tar | Stocke les noms en octets bruts, exactement comme `PharData` |
| **macOS** | `PharData` | `/usr/bin/tar` est `bsdtar` |
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

## Quand le moteur binaire s'efface

Le moteur est choisi **avant que quoi que ce soit ne soit écrit**, et jamais remis en cause
ensuite. Il s'efface quand :

- il n'y a pas de GNU tar ;
- plusieurs chemins sont archivés ensemble sans parent commun — une invocation de `tar` couvre
  un répertoire de base, et on ne peut pas ajouter à une archive déjà compressée ;
- le compresseur manque. `tar` ne compresse pas : il passe par `gzip`, `bzip2` ou `xz`, là où
  `PharData` utilise les extensions PHP. Une machine avec `ext-bz2` et sans programme `bzip2`
  garde le moteur qui fonctionne pour elle.

Un binaire présent qui **échoue** lève au lieu de se rabattre. Il signale quelque chose que
`PharData` rencontrerait aussi — un disque plein, un chemin illisible — et réessayer en PHP
passerait des minutes à atteindre le même mur, en jetant la cause d'origine.

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

## Forcer un choix

`OIHANA_TAR_BINARY` remplace la recherche :

| Valeur | Effet |
|---|---|
| absente | Chercher un GNU tar aux endroits habituels |
| un chemin | Utiliser ce binaire, s'il s'agit d'un GNU tar utilisable |
| vide | Construire en PHP avec `PharData` |

La forme vide est celle qu'utilise la suite de tests de cette bibliothèque pour faire passer les
mêmes arbres témoins dans les deux moteurs et comparer ce qui en sort.

## Voir aussi

- [Créer une archive tar](tar.md)
- [Extraire une archive tar](untar.md)
- [Vue d'ensemble des archives](README.md)
