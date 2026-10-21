# API — règles spécifiques

Symfony 7.4, PHP ≥ 8.2, Doctrine ORM 3, PostgreSQL 16, JWT (lexik), LiipImagine. Tout le code métier vit dans `src/Ecommerce/`.

## Architecture hexagonale : règles non négociables

```
Domain/          Modèles, objets-valeurs, exceptions, interfaces (ports). AUCUNE dépendance à Symfony ni à Doctrine.
Application/     Use cases + DTO d'entrée. Dépend du Domain uniquement.
Infrastructure/  Contrôleurs, entités Doctrine, repositories, mappers, presenters, services techniques.
```

1. **Le Domain n'importe rien de `Symfony\`, `Doctrine\` ni d'`Application\`.**
   - Dette connue, à corriger quand on touche ces fichiers : `ProductRepositoryInterface` importe `GetProductsFilterDto`, `FileStorageInterface` importe `UploadedFile`, et `UserRepositoryInterface` utilise `Symfony\Component\Uid\Uuid`.
2. **Les entités Doctrine (`Infrastructure/Persistence/Entity/Doctrine*`) sont séparées des modèles du domaine.**
   - La conversion passe toujours par un mapper (`toDomain`, `toInfrastructure`, `mapDomainToEntity`).
   - Un repository renvoie **toujours des objets du domaine**, jamais des entités Doctrine.
3. **Les règles métier sont dans le modèle.** Exemple : le prix est validé dans `Product::setPrice()`, et toute modification du prix passe par cette méthode.
4. **Un use case = une classe `execute()`**, nommée `<Verbe><Objet>UseCase`.
5. **Les contrôleurs sont fins :** ils transforment la requête en DTO, appellent le use case et renvoient le presenter. Pas de logique, pas de `try/catch` : les erreurs sont gérées par `ExceptionListener`.
6. **Exceptions métier :**
   - une classe par cas (`ProductNotFoundException`…) ;
   - elles implémentent `DomainExceptionInterface` ;
   - le mapping vers un code HTTP est centralisé dans `ExceptionListener` (NotFound → 404, Validation → 422, Conflict → 409) ;
   - **jamais de `\Exception` générique.**
7. **Validation des entrées :** `#[MapRequestPayload]` / `#[MapQueryString]` + contraintes `Assert` sur les DTO. Les ids de route sont contraints avec `Requirement::UUID`.

## Conventions

- `declare(strict_types=1);` dans **tous** les fichiers.
- **Identifiants :** UUID v4 en `string` (RFC 4122), générés dans le use case.
- **Montants :** en **centimes (`int`)** pour tout nouveau code (panier, commandes). `Product::price` est encore un `float` : dette à migrer avant la phase 4.
- **Slugs :** générés avec `SluggerInterface`, jamais avec `str_replace`.
- **Nommage :** suffixe `Dto` (et non `DTO`), messages d'exception en anglais.
- **Routes :** REST (`GET /api/shop/products`, `POST /api/admin/products`, `PUT /api/admin/products/{id}`…). Les routes actuelles (`/create-product`, `/edit-product/{id}`…) sont à migrer en P1-01.

## Tests

- Les tests **unitaires** (`tests/Unit/`) couvrent le domaine, les use cases (repositories mockés), les mappers et les presenters.
- Les tests **d'intégration** (`tests/Integration/`, `KernelTestCase`) couvrent les repositories Doctrine **contre une vraie base de test**. Les mocks d'EntityManager ont masqué le bug B1.
- Les tests **fonctionnels** (`tests/Functional/`, `WebTestCase`) couvrent les contrôleurs et la sécurité (401/403 attendus).
- Le namespace d'un test suit son dossier : `App\Tests\Unit\...`, `App\Tests\Integration\...`.
- PHPUnit est configuré avec `failOnWarning`, `failOnNotice` et `failOnDeprecation` : un warning PHP fait échouer la suite.

## Pièges connus

- Les clés JWT (`config/jwt/*.pem`) ne sont pas versionnées : `php bin/console lexik:jwt:generate-keypair` sur chaque machine.
- `.env` : `APP_SECRET` est vide. Le définir dans `.env.local`.
- La config Psalm (`pslam.xml`) est cassée et l'ancien `.php_cs` est obsolète : utiliser PHPStan (`phpstan.dist.neon`) et `.php-cs-fixer.dist.php`.
- La migration `Version20260612051158` ajoute des colonnes `NOT NULL` sans défaut : elle échoue si des utilisateurs existent déjà.
