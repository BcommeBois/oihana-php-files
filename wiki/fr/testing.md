# Tests & couverture

![Langue](https://img.shields.io/badge/langue-Français-blue)

La librairie est validée par une **suite de tests unitaires PHPUnit**, exécutée à chaque commit et en CI. Il n'y a pas de tests « live » : tout est testable en isolation, sur le système de fichiers local (répertoires temporaires réels + `vfsStream`).

Le workflow contributeur synthétique est résumé dans [CONTRIBUTING.md](../../CONTRIBUTING.md) ; cette page en est la référence détaillée.

## Lancer les tests

La suite vit dans [`tests/`](../../tests) et se lance avec :

```shell
composer test                                        # = ./vendor/bin/phpunit
./vendor/bin/phpunit --filter MakeAbsoluteTest       # un seul cas
```

Configuration : [phpunit.xml](../../phpunit.xml). Points clés :

- **Périmètre de couverture** : `./src` uniquement (balise `<source>`).
- **Mode strict** : `failOnWarning`, `failOnRisky`, `failOnSkipped`, `failOnIncomplete`, `beStrictAboutOutputDuringTests`… Un test « risqué » (sans assertion, qui produit de la sortie, qui déclenche un *warning* PHP…) fait **échouer** la suite. C'est voulu : un test qui ne vérifie rien ne protège de rien.

> **Sur macOS, `composer test` peut sortir en erreur sans rien casser.** Trois tests de
> [`TarEngineTest`](../../tests/oihana/files/archive/tar/TarEngineTest.php) exercent le moteur
> binaire de `tar` ; sans GNU tar sur la machine ils se *skippent*, et `failOnSkipped` transforme
> ces skips en échec de la suite : la sortie affiche « OK, but some tests were skipped! » puis
> Composer signale un `error code 1`. Le `/usr/bin/tar` d'Apple est `bsdtar`, que la bibliothèque
> refuse délibérément ([pourquoi](archive/tar-engine.md#quel-binaire-et-pourquoi-pas-tous)). Un
> `brew install gnu-tar` suffit à faire passer les trois tests. La CI tourne sous Linux et n'est
> pas concernée.

## Ce qu'on teste, et comment

La librairie est faite de **fonctions autonomes** et de quelques classes ; trois niveaux de testabilité se dégagent :

| Tier | Cible | Technique |
|---|---|---|
| 1 | Fonctions **pures** (`path/**`, énumérations, helpers MIME) | Entrée → sortie attendue, souvent via un *data provider*. Aucun *mock*. |
| 2 | Opérations **système de fichiers** (`makeDirectory`, `findFiles`, `tar`/`untar`, `OpenSSLFileEncryption`…) | Répertoires temporaires réels sous `sys_get_temp_dir()` **et** [`vfsStream`](https://github.com/bovigo/vfsStream) pour simuler des permissions. Nettoyage en `tearDown`. |
| 3 | **Chemins d'erreur** | Provoqués de façon déterministe : `chmod 0444/0555` (dossier/fichier non inscriptible), `chown`/`chgrp` vers un utilisateur inexistant, écriture vers un chemin de répertoire, payloads volontairement corrompus. Les tests dépendant des permissions sont *skippés* sur Windows. |

> **Tests de caractérisation.** Quand on couvre du code existant, on écrit des tests qui décrivent ce que le code **fait réellement**, branche par branche (`if` / `else` / `match`). Ce travail révèle régulièrement de vrais bugs ou des incohérences (exemple de docblock erroné, garde inversée, *feature* morte…). **Règle d'or** : si un comportement surprenant pourrait être utilisé en aval, on le **gèle dans un test** et on le signale — on ne change pas une API publique sans validation explicite. Un vrai bug trouvé en route est corrigé dans un commit `fix(...)` **séparé** (avec entrée CHANGELOG si l'impact est visible), jamais noyé dans un commit de test.

## Couverture de code

PHPUnit mesure quelles lignes de `./src` sont exécutées par la suite. Il faut **activer le mode coverage de Xdebug** (ou PCOV) ; sinon PHPUnit affiche `No tests executed!` et un *warning* `XDEBUG_MODE=coverage … has to be set`. Les scripts `composer` ci-dessous positionnent la variable d'environnement pour toi :

```shell
composer coverage       # suite + couverture : texte au terminal, Clover + HTML sous build/coverage/
composer coverage:md    # régénère build/coverage/COVERAGE.md (résumé Markdown, zones rouges en tête)
```

Les sorties vont dans `build/coverage/` — **gitignoré, jamais commité** : un snapshot de chiffres se périme au commit suivant et pollue les diffs. On régénère à la demande. L'outil de conversion Clover → Markdown vit dans [`tools/clover-to-markdown.php`](../../tools/clover-to-markdown.php).

### Évolution entre deux runs

Chaque génération horodate le rapport et écrit un snapshot dans `build/coverage/history.json` (lui aussi gitignoré). Au run suivant, le résumé compare au **run précédent enregistré** et affiche un delta par métrique : `▲ +0.14 pts (+12 lines)` / `▼ -0.30 pts (-5 methods)` / `= ±0.00 pts (+0 lines)`. L'historique est borné aux 50 derniers runs et reste **purement local** (pour un suivi partagé, publier le rapport via la CI plutôt que le committer).

### Lire le rapport

- **Lignes** = la métrique de référence (% de lignes exécutées).
- Une barre vide = code **jamais testé** → bug potentiel non détecté.
- ⚠️ **100 % ≠ zéro bug.** Une ligne « traversée » sans assertion solide est *couverte* mais pas vraiment *vérifiée*. On vise donc des tests qui **affirment un résultat précis**, pas qui passent simplement à travers le code.

## Politique de couverture

Le principe est : **tester tout ce qui est atteignable**. Quand une ligne n'est pas couverte, deux issues seulement :

1. **La rendre atteignable et la tester** (cas par défaut) — y compris via une configuration dégénérée ou une entrée forgée.
2. Si c'est du **code défensif réellement inatteignable** sous test, préférer d'abord **supprimer/simplifier** le code (refactor) ; en dernier recours seulement, l'annoter avec une directive **nue** `@codeCoverageIgnore`, précédée d'un commentaire qui explique *pourquoi* la ligne est inatteignable.

Sont considérées inatteignables et annotées, par exemple :

- les **branches spécifiques à un OS** (ex. chemin absolu Windows alors que la CI tourne sous Linux) ;
- les gardes **TOCTOU** (`fopen`/`file_get_contents` qui échouerait après un `assertFile()` réussi) ;
- les appels qui **n'échouent jamais** dans l'environnement requis (`finfo_open`, `random_bytes`, `openssl_encrypt` avec des paramètres valides, `Phar::canCompress` avec `zlib`/`bz2` présents) ;
- les gardes de **défense en profondeur** que la couche sous-jacente neutralise déjà (ex. `PharData` assainit les entrées `..`, rendant la garde anti-*path-traversal* d'`untar` non déclenchable via une archive créée par PharData).

État au **2026-06-09** : **100 % de lignes** (1416 / 1416) et **100 % de méthodes** (38 / 38), **636 tests** verts.

## Intégration continue

Le workflow [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) exécute, sur **PHP 8.4** (extensions `fileinfo`, `openssl`, `posix`, `zlib`, `sodium`) :

1. `composer validate --strict` ;
2. l'installation des dépendances (avec cache Composer) ;
3. `vendor/bin/phpunit`.

La couverture n'est pas mesurée en CI (`coverage: none`) : c'est une vérification locale, à la demande.

## Voir aussi

- [Sécurité](security.md) — périmètre de sécurité, menaces couvertes / non couvertes.
- [Astuces et pièges](tips.md) — chemins Windows, *symlinks*, permissions, encodage.
- [CONTRIBUTING.md](../../CONTRIBUTING.md) — guide contributeur synthétique.
