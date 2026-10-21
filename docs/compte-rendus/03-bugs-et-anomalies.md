# 03 — Bugs et anomalies relevés

Légende : 🔴 bloquant / perte de données · 🟠 comportement faux · 🟡 fragilité / dette

Les chemins sont relatifs à `src/Ecommerce/`.

## 🔴 Bloquants

### B1. `DoctrinePictureRepository` interroge le modèle domaine au lieu de l'entité
`Infrastructure/Persistence/Doctrine/Catalog/DoctrinePictureRepository.php:34, 39, 44`

```php
return $this->entityManager->getRepository(Picture::class)->find($id);
```
`Picture` est le modèle **domaine** : ce n'est pas une entité Doctrine. `findById`, `findByProductId` et `findAll` lèvent donc une exception à l'exécution. De plus, `findByProductId` filtre sur un champ `productId` qui n'existe pas (le champ s'appelle `product`). Les méthodes devraient renvoyer des `Picture` domaine via `PictureMapper::toDomain`.
*Les tests unitaires mockent l'EntityManager, ce qui masque le problème.*

### B2. Relation `DoctrinePicture → DoctrineCategory` mal mappée
`Infrastructure/Persistence/Entity/Catalog/DoctrinePicture.php:29`

```php
#[ORM\ManyToOne(targetEntity: DoctrineCategory::class, inversedBy: 'images')]
```
`DoctrineCategory` n'a pas de propriété `images` (ni `pictures`). `bin/console doctrine:schema:validate` signalera un mapping invalide. Il faut soit ajouter la collection côté catégorie, soit retirer `inversedBy`.

### B3. Migration non rejouable sur une base peuplée
`migrations/Version20260612051158.php` ajoute `first_name` et `last_name` en `NOT NULL` sans `DEFAULT`. Elle échoue dès qu'un utilisateur existe en base.

## 🟠 Comportements faux

### B4. Slug produit perdu
- `Infrastructure/Persistence/Mapper/Catalog/ProductMapper.php:57` : `toDomain()` renvoie `"slug-placeholder"` pour **tous** les produits.
- La table `products` n'a pas de colonne `slug`, donc le slug calculé à la création n'est jamais enregistré.
- La génération (`strtolower(str_replace(' ', '-', $name))`) ne gère ni les accents ni la ponctuation : « Bougie Été à l'Ambre » donne `bougie-été-à-l'ambre`. Le `SluggerInterface` de Symfony, déjà utilisé dans `LocalFileStorage`, ferait le travail.

### B5. Poids `0` au lieu de `null` + warning PHP
`Application/Mapper/AttributesMapper.php:13` et `:33`

```php
weight: (float) $dto->attributes['weight'] ?? null,
```
Le cast `(float)` est prioritaire sur `??`. Si `weight` est absent, PHP émet `Warning: Undefined array key "weight"` et le poids vaut `0.0`. Vérifié en exécutant le code. Comme `phpunit.dist.xml` a `failOnWarning="true"`, tout test qui passe par ce chemin échoue.
Correctif : `weight: isset($a['weight']) ? (float) $a['weight'] : null`.
Note : `"180g"` (utilisé dans les fichiers `.http`) est converti silencieusement en `180.0`.

### B6. Mise à jour produit sans validation du prix
`Domain/Model/Catalog/Product.php:60` : `updateDetails()` fait `$this->price = $price;` au lieu d'appeler `setPrice()`. Un `PUT` avec un prix négatif est donc accepté.

### B7. Inscription : téléphone et adresse ignorés
`Application/UseCase/User/RegisterUserUseCase.php:32` : le `User` est construit sans `phoneNumber` ni `address`, alors que `RegisterUserRequestDTO` les reçoit.

### B8. Codes HTTP incorrects

| Situation | Renvoyé | Attendu | Cause |
|---|---|---|---|
| `PUT /edit-product/{id}` sur un produit inconnu | 500 | 404 | `throw new \Exception("Product not found")` (`UpdateProductUseCase.php:24`) |
| Idem avec une catégorie inconnue | 500 | 404/422 | `UpdateProductUseCase.php:29` |
| Upload sur un produit inconnu | 500 | 404 | `UploadProductImageUseCase.php:26` + `catch (\Exception)` → 500 dans le contrôleur |
| `POST /create-product` avec une catégorie inconnue | 400 | 404/422 | `ExceptionListener` mappe toute `DomainExceptionInterface` en 400 |
| `POST /users/create` avec un champ manquant | 500 | 400 | `RegisterUserRequestMapper.php:17` lit `$data['email']` sans `??` → `TypeError` |
| Body JSON invalide sur `/users/*` | 500 | 400 | `Request::toArray()` lève `JsonException`, non interceptée |
| `GET /users/abc` (id non-UUID) | 500 | 400/404 | `Uuid::fromString` lève `InvalidArgumentException`, non interceptée dans `getById`/`delete` |
| `GET /product/abc` | 500 | 400/404 | PostgreSQL rejette `'abc'` sur une colonne UUID |
| Création produit sans `category_id` | 500 | 400 | `Uuid::fromString('')` |

### B9. `UpdateUserRequestMapper` accepte des noms vides
Les valeurs par défaut `''` pour `first_name` et `last_name` font qu'un `PUT` partiel **efface** le prénom et le nom.

## 🟡 Fragilités

| # | Où | Constat |
|---|---|---|
| B10 | `Domain/Model/Catalog/Category.php:12` | `private ?array $pictures;` n'est pas initialisé : `getPictures()` lève une `Error` si aucune image n'a été ajoutée. |
| B11 | `UploadProductImageUseCase.php:44-45` | Le fichier est écrit sur disque **avant** la sauvegarde en base, puis deux `flush()` séparés sont exécutés. En cas d'échec, il reste un fichier orphelin ou un état incohérent. Le `productRepository->save($product)` est par ailleurs inutile (le mapper ne gère pas les images). |
| B12 | `LoginUserUseCase.php:33` + `SymfonyPasswordVerifier` | L'utilisateur est chargé deux fois (deux requêtes SQL), et le vérificateur dépend directement de Doctrine. Mieux : vérifier le hash à partir de `User::getPasswordHash()`. |
| B13 | `JwtTokenGenerator.php:24` | Crée un faux `DoctrineUser` (id `00000000-…`) uniquement pour signer le token. Ça fonctionne, mais c'est fragile si le payload JWT inclut un jour l'id. |
| B14 | `PasswordHasher.php:19` | Hash via un `InMemoryUser('dummy')`. Ça fonctionne tant que les deux classes partagent le même hasher `auto`, mais tout changement de config par classe casse la vérification. |
| B15 | `DoctrineProduct` | `price` est un `DECIMAL` mappé sur un `float` PHP : risque d'arrondi sur les montants. Préférer des centimes en `int`, ou une `string`/`Money`. |
| B16 | `ProductController::create` / `update` | `json_decode` sans contrôle : un body vide ou invalide donne `$data = null`, et le produit est créé avec `name = ''` et `price = 0`. |
| B17 | Exceptions `User` | `DuplicateEmailException` et `UserNotFoundException` n'implémentent pas `DomainExceptionInterface`, contrairement aux exceptions Catalog : le comportement de l'`ExceptionListener` diffère selon le module. |
| B18 | Nommage | Mélange FR/EN (`CategorieNotFoundException`, messages « Produit non trouvé » / « Product not found »), `Dto` vs `DTO`. |
