---
name: nouvelle-fonctionnalite
description: Procédure pas à pas pour ajouter une fonctionnalité de bout en bout à l'API Parfumée (domaine, use case, persistance, endpoint, tests) en respectant l'architecture hexagonale. À utiliser dès qu'on ajoute un modèle, un use case ou un endpoint dans api/.
---

# Ajouter une fonctionnalité à l'API

Suis les étapes **dans l'ordre**. Prends toujours exemple sur le code existant le plus proche (module Catalog ou User). Tous les chemins sont relatifs à `api/src/Ecommerce/`. Remplace `<Module>` par `Catalog`, `User`, `Cart`, `Order`…

## 0. Cadrer
- Retrouve la tâche dans `docs/compte-rendus/07-roadmap.md` (ID, dépendances) et vérifie que ses dépendances sont faites.
- Écris en 3 lignes :
  - ce que la fonctionnalité fait ;
  - qui l'appelle : `/api/shop` (public ou `ROLE_USER`) ou `/api/admin` (`ROLE_ADMIN`) ;
  - les règles métier concernées.

## 1. Domaine (`Domain/`)
- **Modèle** dans `Model/<Module>/`, ou méthode métier sur un modèle existant. Les règles de validation métier vont **dans** le modèle (exemple : `Product::setPrice`).
- **Objets-valeurs** dans `ValueObject/<Module>/`. Ils sont immuables (`readonly`) et se valident dans le constructeur.
- **Exceptions** dans `Exception/<Module>/`, nommées `XxxNotFoundException`, `XxxConflictException`… Elles implémentent `DomainExceptionInterface`.
- **Port** dans `Repository/<Module>/XxxRepositoryInterface.php` ou `Service/<Module>/`. Il n'utilise que des types du domaine et des scalaires, sans Symfony ni Doctrine.

## 2. Application (`Application/`)
- **DTO d'entrée** dans `DTO/<Module>/XxxDto.php` : `readonly`, avec les contraintes `Assert` (NotBlank, Length, Positive, Uuid…).
- **Use case** dans `UseCase/<Module>/<Verbe><Objet>UseCase.php`, avec une seule méthode `execute()`. Il :
  - charge via le port ;
  - lève une exception métier si l'objet est absent ;
  - appelle la logique du modèle ;
  - sauvegarde via le port ;
  - renvoie un objet du domaine (ou son id).

## 3. Persistance (`Infrastructure/Persistence/`)
- **Entité Doctrine** dans `Entity/<Module>/DoctrineXxx.php` (mapping par attributs). Vérifie que chaque `inversedBy` / `mappedBy` existe des deux côtés.
- **Mapper** dans `Mapper/<Module>/XxxMapper.php`, avec `toDomain()`, `toInfrastructure()` et `mapDomainToEntity()`. Tous les champs sont mappés dans les deux sens, sans valeur « placeholder ».
- **Repository** dans `Doctrine/<Module>/DoctrineXxxRepository.php`. Il implémente le port, interroge `DoctrineXxx::class` et **renvoie des objets du domaine** via le mapper.
- **Migration :** `php bin/console make:migration`, puis relis le SQL. Une colonne `NOT NULL` sur une table existante doit avoir un `DEFAULT` ou être ajoutée en deux temps.
- **Vérification :** `php bin/console doctrine:schema:validate`.

## 4. Exposition HTTP (`Infrastructure/`)
- **Contrôleur** dans `Controller/<Module>/` :
  - une route REST sous `/api/shop/...` ou `/api/admin/...` ;
  - `requirements: ['id' => Requirement::UUID]` sur les ids ;
  - le DTO reçu via `#[MapRequestPayload]` ou `#[MapQueryString]` ;
  - `#[IsGranted(...)]` si nécessaire ;
  - pas de `try/catch`.
- **Presenter** dans `Presenter/<Module>/` : il n'expose que les champs utiles au front concerné. Un presenter shop n'expose ni données internes ni données d'autres clients.
- **Codes HTTP :** 201 + `Location` en création, 200 en lecture et mise à jour, 204 en suppression. Les erreurs sont gérées par `ExceptionListener`.
- **Sécurité :** ajoute la règle `access_control` ou un Voter si la ressource appartient à un utilisateur.
- **Exception métier nouvelle :** vérifie qu'elle est bien mappée dans `ExceptionListener`.
- **OpenAPI :** documente l'endpoint (attributs `#[OA\...]` de nelmio).

## 5. Tests (déléguer à l'agent `test-writer` si besoin)
- **Unitaires :** règles du modèle, use case (cas nominal + exception), mapper (aller-retour).
- **Intégration :** le repository contre la base de test.
- **Fonctionnels :** 401 sans token, 403 avec un mauvais rôle, 422 sur une entrée invalide, 404 sur un id inconnu, et le cas nominal.

## 6. Clôture
- Lance, depuis `api/` : `vendor/bin/phpunit`, `vendor/bin/phpstan analyse` et `vendor/bin/php-cs-fixer fix --dry-run --diff`. Tout doit être vert.
- Fais relire par l'agent `reviewer`.
- Applique la skill `fin-de-session` : mise à jour de `docs/STATUS.md` et proposition de commit.
