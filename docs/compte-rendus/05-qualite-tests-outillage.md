# 05 — Qualité, tests et outillage

## Tests

### Inventaire

34 fichiers de test et environ **110 méthodes de test**, tous dans `tests/Unit/` :

| Zone | Fichiers | Tests |
|---|---|---|
| Application / UseCase Catalog | 5 | 10 |
| Application / UseCase User | 6 | 16 |
| Application / Mapper | 3 | 8 |
| Domain (modèles, VO) | 3 | 9 |
| Infrastructure / Persistence (repos, entités, mappers) | 11 | 45 |
| Infrastructure / Controller | 1 (`ProductControllerTest`) | 7 |
| Infrastructure / Presenter, Service, Storage, Listener | 5 | 15 |

### Points positifs
- Chaque classe importante a son test, avec une bonne discipline Arrange / Act / Assert.
- Les use cases sont testés avec des mocks de repositories : c'est le bon niveau pour l'hexagonal.
- PHPUnit est configuré de façon stricte (`failOnWarning`, `failOnNotice`, `failOnDeprecation`).

### Problèmes
| # | Constat |
|---|---|
| T1 | **Aucun test d'intégration réel** : les repositories Doctrine sont testés avec un `EntityManager` mocké. Les bugs B1 (repository Picture) et B2 (mapping `inversedBy`) passent donc inaperçus. Il faut des tests `KernelTestCase` contre une vraie base de test. |
| T2 | **Pas de test du `UserController`**, ni de test HTTP bout-en-bout du login JWT. |
| T3 | `ProductControllerTest` est un `WebTestCase` (donc un test fonctionnel) rangé dans `tests/Unit/`, avec le namespace `App\Tests\Integration\…` : incohérent avec son emplacement, ce qui génère un avertissement d'autoload PSR-4. |
| T4 | Deux tests ont un namespace sans le préfixe `App\` (`Tests\Unit\…\ProductPresenterTest`, `Tests\Unit\…\AttributeTest`) : non conformes à l'`autoload-dev`. |
| T5 | Le dernier cache PHPUnit (`.phpunit.cache/test-results`, mai 2026) contient **5 tests en erreur** (`ProductMapperTest` ×3, `ProductControllerTest::testCreateProduct`, `testUploadPictureSuccess`). Le cache est ancien : relancer la suite pour connaître l'état actuel. |
| T6 | Les fichiers de test HTTP (`tests/http/*.http`, collection Bruno) appellent `POST /product`, alors que la route réelle est `/create-product`. Ils sont aussi exclus de git par `.gitignore`. |
| T7 | Pas de fixtures produits ni utilisateurs pour les tests fonctionnels. |

### Couverture
Le dernier rapport (`var/coverage/`, 22/05/2026) indique **94,8 % des lignes**, 87 % des méthodes et 69 % des classes, mais sur seulement 26 classes : il date d'**avant** l'ajout du module Utilisateur. Il est à regénérer :
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage
```

## Analyse statique et style

| Outil | État | Problème |
|---|---|---|
| **PHPStan** (niveau 6) | ⚠️ | Le script `composer lint:phpstan` pointe vers `phpstan.neon`, qui est **ignoré par git** et absent. Le fichier présent s'appelle `phpstan.dist.neon`. → Retirer `--configuration` (PHPStan charge le `.dist` tout seul). |
| **Psalm** | ❌ | Le fichier s'appelle `pslam.xml` (faute de frappe), alors que le script attend `psalm.xml`. Il référence des dossiers inexistants (`src/Domain`, `src/Controller`, `src/Traits`…), des plugins inexistants (`phpstan-php-di`, `PsalmPlugin.php`) et un stub Laminas non installé. **Inutilisable en l'état** : à réécrire ou à supprimer, PHPStan suffit. |
| **PHP-CS-Fixer** | ⚠️ | `.php-cs-fixer.dist.php` (règles `@Symfony`) cohabite avec un ancien `.php_cs` (format v2, obsolète) à supprimer. Le code présente des écarts visibles : indentation, espaces, `public  function`, `map (array`. |
| **strict_types** | ⚠️ | Seul le module User déclare `declare(strict_types=1)`. Tout le module Catalog et l'Infrastructure sont en mode coercitif. |

## Lisibilité du code

- Nombreux commentaires pédagogiques en tutoiement (« Tu peux aussi… », « ton mapper ») : pratique en apprentissage, à nettoyer avant une mise en production.
- Propriétés non typées dans `Attribute` (`private $weight;`…).
- Des getters/setters partout dans `Attribute` : un objet-valeur immuable (`readonly`) serait plus cohérent avec le reste du domaine.
- Aucun `README` : pas d'instructions d'installation (docker compose, migrations, génération des clés JWT, fixtures).

## Git

- Deux branches : `main` (à jour avec `origin`) et `feat/data-product` (filtrage produit, mergée).
- Les messages de commit sont descriptifs mais longs. Adopter les *Conventional Commits* (`feat(catalog): …`) aiderait.
- Pas de CI (aucun dossier `.github/workflows`) : tests et linters ne tournent pas automatiquement.
