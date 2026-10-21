# 01 — Architecture et stack technique

## Stack

| Couche | Technologie |
|---|---|
| Langage | PHP ≥ 8.2 (couverture générée sous PHP 8.5) |
| Framework | Symfony 7.4 (framework-bundle, security, uid, mime, runtime, dotenv) |
| ORM / BDD | Doctrine ORM 3.6 + Migrations, PostgreSQL 16 (via `compose.yaml`, image `postgres:16-alpine`) |
| Authentification | `lexik/jwt-authentication-bundle` 3.2 (JWT stateless) |
| Images | `liip/imagine-bundle` (filtre `product_thumb` : 400×400, WebP, qualité 75) |
| Dev / qualité | PHPUnit 12.5, PHPStan 2 (niveau 6), Psalm 6, PHP-CS-Fixer (règles `@Symfony`), MakerBundle, Fixtures |
| Tests HTTP manuels | fichiers `.http` et collection Bruno/OpenCollection dans `tests/http/` |

## Organisation du code (architecture hexagonale)

Un seul *bounded context* : `src/Ecommerce`, découpé en deux sous-domaines, **Catalog** et **User**.

```
src/Ecommerce/
├── Domain/                     ← cœur métier (doit rester indépendant du framework)
│   ├── Model/Catalog/          Product, Category, Picture, Attribute
│   ├── Model/User/             User, Address
│   ├── ValueObject/User/       Email, PhoneNumber
│   ├── Exception/              CategorieNotFound, InvalidPrice, DuplicateEmail, UserNotFound
│   ├── Repository/             interfaces (ports) Product/Category/Picture/User
│   ├── Service/User/           ports PasswordHasher, PasswordVerifier, TokenGenerator
│   └── Storage/                port FileStorageInterface
├── Application/                ← cas d'usage
│   ├── UseCase/Catalog/        Create, Update, Get, GetAll, GetFiltered, UploadImage
│   ├── UseCase/User/           Register, Login, Get, GetAll, Update, Delete
│   ├── DTO/                    objets d'entrée
│   └── Mapper/                 Request/array → DTO, DTO → Attribute
└── Infrastructure/             ← adaptateurs
    ├── Controller/             ProductController, UserController
    ├── Persistence/Entity/     entités Doctrine (DoctrineProduct, DoctrineUser…)
    ├── Persistence/Doctrine/   implémentations des repositories
    ├── Persistence/Mapper/     Doctrine ⇄ Domaine
    ├── Persistence/Fixtures/   CategoryFixtures
    ├── Presenter/              Domaine → tableau JSON
    ├── Service/User/           JwtTokenGenerator, PasswordHasher, SymfonyPasswordVerifier
    ├── Storage/                LocalFileStorage (public/uploads)
    └── EventListener/          ExceptionListener (kernel.exception)
```

**Flux d'une requête :** Controller → Mapper (Request → DTO) → UseCase → Repository (interface) → DoctrineRepository → Mapper (Entity → Domain) → Presenter → `JsonResponse`.

### Ce qui est bien fait

- Les entités Doctrine sont **séparées** des modèles métier, et des mappers dédiés font la conversion. C'est le point le plus difficile à tenir en hexagonal, et il est respecté.
- Les dépendances techniques (hash de mot de passe, JWT, stockage fichier) passent par des **ports** déclarés dans le domaine et câblés dans `services.yaml`.
- Le mapping Doctrine est en **attributs PHP**, limité au dossier `Persistence/Entity`.

### Fuites d'abstraction (le domaine dépend de l'extérieur)

| Où | Problème |
|---|---|
| `Domain/Repository/Catalog/ProductRepositoryInterface.php:5` | Le domaine importe `Application\DTO\Catalog\GetProductsFilterDto`. La dépendance va dans le mauvais sens (Domain → Application). |
| `Domain/Storage/FileStorageInterface.php:5` | Le port dépend de `Symfony\...\UploadedFile`. |
| `Domain/Repository/User/UserRepositoryInterface.php` | Utilise `Symfony\Component\Uid\Uuid`, alors que les repositories Catalog utilisent `string`. Les deux conventions coexistent. |
| `Application/UseCase/User/LoginUserUseCase.php` | Lève `Symfony\...\AuthenticationException` (exception du framework dans la couche Application). |
| `Application/Mapper/User/*RequestMapper.php` | Prennent une `Symfony\...\Request` en entrée : ce sont des adaptateurs HTTP, ils devraient être en Infrastructure. |

## Modèle de données (PostgreSQL)

```
categories (id UUID PK, name, slug UNIQUE)
    │ 1
    │ N
products (id UUID PK, name, price NUMERIC(10,2), attributes JSON, category_id FK NOT NULL)
    │ 1
    │ N
pictures (id VARCHAR PK, url, alt, sort_order, product_id FK NULL, category_id FK NULL ON DELETE CASCADE)

users (id UUID PK, email UNIQUE, roles JSON, password, first_name, last_name, phone_number)
    │ 1
    │ N
addresses (id UUID PK, user_id FK ON DELETE CASCADE, type, street, city, postal_code, country, is_default)
```

Remarques :

- **`products` n'a pas de colonne `slug`**, alors que le modèle métier `Product` en a un (voir bugs).
- `pictures.id` est un `VARCHAR`, alors que tous les autres identifiants sont des `UUID`.
- `attributes` est en type `json` (et non `jsonb`). Le commentaire de `DoctrineProduct` affirme que c'est du JSONB, c'est inexact. Le filtrage `->>'fragrance'` fonctionne quand même, mais sans possibilité d'index GIN.
- Pas de colonne de stock, de statut (actif/brouillon), ni de dates `created_at` / `updated_at`.

## Migrations

| Version | Contenu |
|---|---|
| `20260507201724` | Tables `categories` et `products` |
| `20260515155631` | Table `pictures` (FK produit et catégorie, `ON DELETE CASCADE`) |
| `20260531203111` | Table `users` ; retire le `ON DELETE CASCADE` de `pictures.product_id` |
| `20260612051158` | Table `addresses` ; ajoute `first_name`, `last_name` (NOT NULL **sans valeur par défaut**) et `phone_number` à `users` |

⚠️ La dernière migration échouera sur une base qui contient déjà des utilisateurs, à cause des colonnes `NOT NULL` ajoutées sans `DEFAULT`.

## Configuration

- `services.yaml` : autowiring global de `src/`, câblage explicite des ports `FileStorage`, `TokenGenerator` et `PasswordVerifier`. `PasswordHasherInterface` n'est pas câblé explicitement, mais son implémentation est unique, donc l'autowiring s'en charge.
- Les uploads vont dans `public/uploads/products/` (servis directement par le serveur web).
- `framework.session: true` est activé alors que l'API est stateless : inutile.
- Les clés JWT (`config/jwt/*.pem`) **ne sont pas présentes** dans le dossier. Il faut lancer `bin/console lexik:jwt:generate-keypair` avant que le login fonctionne.
- `.env` : `APP_SECRET` est **vide**.
