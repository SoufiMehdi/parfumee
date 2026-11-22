# API — règles spécifiques

Symfony 8.1 (pas LTS, voir ADR 0005), PHP ≥ 8.4, Doctrine ORM 3 / DoctrineBundle 3, PostgreSQL 16, JWT (lexik), LiipImagine. Tout le code métier vit dans `src/Ecommerce/`.

## Architecture hexagonale : règles non négociables

```
Domain/          Modèles, objets-valeurs, exceptions, interfaces (ports). AUCUNE dépendance à Symfony ni à Doctrine.
Application/     Use cases + DTO d'entrée. Dépend du Domain uniquement.
Infrastructure/  Contrôleurs, entités Doctrine, repositories, mappers, presenters, services techniques.
```

1. **Le Domain n'importe rien de `Symfony\`, `Doctrine\` ni d'`Application\`.**
   - Dette connue, à corriger quand on touche ce fichier : `FileStorageInterface` importe `UploadedFile`.
2. **Les entités Doctrine (`Infrastructure/Persistence/Entity/Doctrine*`) sont séparées des modèles du domaine.**
   - La conversion passe toujours par un mapper (`toDomain`, `toInfrastructure`, `mapDomainToEntity`).
   - Un repository renvoie **toujours des objets du domaine**, jamais des entités Doctrine.
3. **Les règles métier sont dans le modèle.** Exemple : le prix est validé dans `Product::setPrice()`, et toute modification du prix passe par cette méthode.
4. **Un use case = une classe `execute()`**, nommée `<Verbe><Objet>UseCase`.
5. **Les contrôleurs sont fins :** ils transforment la requête en DTO, appellent le use case et renvoient `$this->json(...)` de la vue produite par le presenter. Pas de logique, pas de `try/catch` : les erreurs sont gérées par `ExceptionListener`.
6. **Exceptions métier :**
   - une classe par cas (`ProductNotFoundException`…) ;
   - chacune implémente **une** des interfaces de `Domain/Exception/`, qui dit de quel type d'échec il s'agit : `NotFoundExceptionInterface` → 404, `ConflictExceptionInterface` → 409, `ValidationExceptionInterface` → 422, `AuthenticationExceptionInterface` → 401 ;
   - le mapping vers le code HTTP est centralisé dans `ExceptionListener` : ajouter une exception ne demande aucune modification du listener ;
   - leur message est renvoyé tel quel au client : l'écrire pour lui, en anglais, sans détail technique ;
   - **jamais de `\Exception` ni d'`\InvalidArgumentException` générique**, qui finiraient en 500.
7. **Format d'erreur :** toute erreur sort en `{"error": "..."}`. Le message d'une erreur 5xx n'est jamais renvoyé hors mode debug : il est journalisé côté serveur.
8. **Validation des entrées :** `#[MapRequestPayload]` / `#[MapQueryString]` + contraintes `Assert` sur les DTO. Les ids de route sont contraints avec `Requirement::UUID`.
   - Pas de mapper manuel requête → DTO, et pas de `json_decode` ni de `$request->toArray()` dans un contrôleur.
   - L'API parle `snake_case` (`first_name`), les DTO sont en `camelCase` : la conversion est globale (`config/packages/serializer.yaml`).
   - `#[MapQueryString]` répond 404 par défaut en cas d'erreur : toujours préciser `validationFailedStatusCode: 422`.
   - `Assert\NotBlank` accepte une chaîne d'espaces : écrire `NotBlank(normalizer: 'trim')`.
   - Une erreur de validation sort en `{"error": "Validation failed.", "violations": [{"field": "first_name", "message": "..."}]}`.

## Contrat OpenAPI

Le contrat est généré à partir du code ; les fronts en tirent leur client. Il doit donc rester exact.

- **Réponses :** un presenter renvoie une **vue**, un objet `final readonly` de `Infrastructure/Presenter/<Module>/View/` aux propriétés publiques typées. Jamais un tableau : c'est la vue qui produit le schéma de réponse. Une liste se déclare `/** @var list<XView> */`.
- **`snake_case` partout :** les propriétés sont en `camelCase` dans le code, le sérialiseur les convertit. Renvoyer avec `$this->json()`, jamais `new JsonResponse()` d'un objet.
- **Chaque action documente :** un résumé (`#[OA\Get(summary: ...)]`), sa réponse de succès avec `new Model(type: XView::class)`, et ses erreurs par référence (`ref: '#/components/responses/NotFound'`). Le tag et les 401/403 communs se posent sur la classe.
- **Route publique :** `#[Security(name: null)]`, en plus de la règle dans `security.yaml`. Un test vérifie que les deux disent la même chose.
- **Après tout changement** de route, de DTO ou de vue : `composer openapi:dump`, et commiter `openapi.json`.

## Listes

Toute liste renvoyée par l'API est paginée. La chaîne complète, à reproduire pour une nouvelle liste (exemple : les produits) :

- **DTO de query string** (`ListProductsQueryDto`) : les filtres, puis `page` (`Range(1, Pagination::MAX_PAGE)`), `perPage` (`Range(1, Pagination::MAX_PER_PAGE)`), `sort` (`Choice` sur les valeurs de l'enum de tri) et `order` (`Choice` sur `SortDirection`). Un filtre porte le nom du champ qu'il filtre.
- **Domaine** : des critères (`ProductCriteria` : filtres + `ProductSortField` + `SortDirection`) et une `Pagination` ; le repository expose `findPage(Criteria, Pagination): Page`. Ne jamais renvoyer `findAll()` à un client.
- **Use case** `List<Objet>UseCase` : convertit le DTO en critères et renvoie `Page<T>`.
- **Repository Doctrine** : les ids de la page en SQL (`COUNT`, puis `ORDER BY … LIMIT … OFFSET`), puis les objets en une requête DQL avec leurs relations (`JOIN … WHERE id IN (:ids)`). Une page coûte 3 requêtes, quel que soit son nombre d'éléments : le vérifier avec `queriesRunBy()`.
- **Tri** : toujours par `SqlOrderBy::clause()`, qui départage par l'id ; une colonne de texte passe par `SqlOrderBy::frenchText()` (collation ICU française), sinon PostgreSQL trie octet par octet.
- **Presenter** : `presentPage()` renvoie une vue `XListView` (`items` + `PaginationView`), et le contrôleur répond `$this->json($list, headers: $list->pagination->headers())` pour l'en-tête `X-Total-Count`. Documenter la réponse avec `headers: [new OA\Header(header: PaginationView::TOTAL_COUNT_HEADER, ref: '#/components/headers/TotalCount')]`.
- `OpenApiContractTest` refuse toute réponse en tableau nu et toute liste sans pagination, en-tête ni paramètres de page et de tri.

## Conventions

- `declare(strict_types=1);` dans **tous** les fichiers.
- **Identifiants :** UUID v4 en `string` (RFC 4122), générés dans le use case.
- **Montants :** en **centimes (`int`)** pour tout nouveau code (panier, commandes). `Product::price` est encore un `float` : dette à migrer avant la phase 4.
- **Dates d'enregistrement :** toute entité utilise le trait `Infrastructure/Persistence/Entity/Timestamps` : `created_at` et `updated_at` en `timestamptz`, posées par Gedmo Timestampable à l'enregistrement, jamais par le code (pas de setter).
  - Un modèle du domaine qui doit les exposer les reçoit du mapper, en lecture seule (`?\DateTimeImmutable`, `null` tant qu'il n'est pas enregistré). Le mapper ne les renvoie jamais vers l'entité.
  - Une vue les déclare en `\DateTimeImmutable` : le sérialiseur écrit toute date en UTC, au format RFC 3339 (`config/packages/serializer.yaml`).
  - Un use case qui renvoie l'objet qu'il vient de modifier le **relit après `save()`** (voir `UpdateProductUseCase`) : l'objet en mémoire garde l'ancien `updated_at`.
  - Une date **métier** (commande passée, payée, expédiée) n'est pas une date d'enregistrement : elle appartient au modèle, et le use case la lui passe en lisant une horloge injectée.
- **Slugs :** générés par le port `SlugGeneratorInterface` (`SluggerInterface` de Symfony derrière), jamais avec `str_replace`. Pour un produit, passer par `ProductSlugResolver`, qui garantit l'unicité.
- **Compte connecté :** une route qui agit sur le compte de l'appelant ne prend pas d'id : elle lit le compte du jeton avec `#[CurrentUser] DoctrineUser $user` (voir `Shop/AccountController`). Ne jamais comparer un id de route à celui du jeton.
- **Rôles :** utiliser les constantes de `Domain/Model/User/Role`, jamais la chaîne `'ROLE_ADMIN'`. Un rôle ne se donne que par `User::grantRole()`, et seule la commande `app:user:create-admin` l'appelle : aucune route ne doit permettre de changer un rôle.
- **Commandes console :** dans `Infrastructure/Console/`, fines comme un contrôleur (elles appellent un use case).
- **Nommage :** suffixe `Dto` (et non `DTO`), messages d'exception en anglais.
- **Routes :** REST, dans deux espaces (`GET /api/v1/shop/products`, `POST /api/v1/admin/products`, `PUT /api/v1/admin/products/{id}`…).
  - Les contrôleurs sont rangés par espace : `Infrastructure/Controller/Shop/` et `Infrastructure/Controller/Admin/`. L'attribut `#[Route]` de la classe porte l'espace et le nom (`/shop/...` + `shop_...`) ; le préfixe `/api/v1` est ajouté une seule fois, dans `config/routes.yaml`.
  - Une même ressource peut avoir un contrôleur dans chaque espace : ils appellent **les mêmes use cases**. Ne jamais dupliquer un use case pour un espace.
  - `access_control` protège chaque espace en bloc : une nouvelle route sous `/api/v1/admin` est d'office réservée à `ROLE_ADMIN`, sous `/api/v1/shop` à `ROLE_USER`. Seule une route **publique** doit être ajoutée à `security.yaml`.
  - Le préfixe de version `/api/v1` ne s'écrit jamais dans un contrôleur. Un changement incompatible du contrat se publierait sous `/api/v2`, à côté de l'ancien.

## Tests

- Les tests **unitaires** (`tests/Unit/`) couvrent le domaine, les use cases (repositories mockés), les mappers et les presenters. **Pas de test unitaire d'un repository Doctrine** : un EntityManager simulé ne prouve rien.
- Les tests **d'intégration** (`tests/Integration/`) couvrent les repositories Doctrine **contre une vraie base de test**. Les mocks d'EntityManager ont masqué le bug B1.
  - Ils étendent `App\Tests\Support\DatabaseTestCase`, qui joue les migrations une fois par lancement et annule les données de chaque test (transaction + rollback).
  - Les données se créent avec les factories Foundry de `tests/Factory/` (`DoctrineProductFactory::createOne([...])`). Ne pas utiliser le trait `ResetDatabase` de Foundry.
  - Après avoir créé des données, appeler `$this->entityManager->clear()` avant de lire : sinon Doctrine rend l'objet en mémoire sans interroger la base.
  - `queriesRunBy(fn () => ...)` renvoie les requêtes SQL exécutées : de quoi vérifier qu'une liste ne fait pas une requête par élément.
  - La base est le service `database_test` de `compose.override.yaml` (`docker compose up -d`). Sans elle, ces tests **échouent** : ne jamais les remplacer par un `markTestSkipped`.
- Les tests **fonctionnels** (`tests/Functional/`, `WebTestCase`) couvrent les contrôleurs et la sécurité (401/403 attendus).
  - Ils ne doivent **jamais** atteindre la base : les use cases qui pourraient s'exécuter sont remplacés par des doubles (`static::getContainer()->set()`).
  - Le client reconstruit le conteneur entre deux requêtes d'un même test : un double ne vaut que pour la **première** requête. Une requête par test, sauf si les suivantes ne touchent aucun use case (requête préliminaire CORS, refus du pare-feu).
  - Ce défaut passe inaperçu en local, où la base de test a ses tables, et casse la CI, où elle est vide. Pour le voir avant de pousser : `docker compose restart database_test`, puis `vendor/bin/phpunit tests/Functional`.
- Le namespace d'un test suit son dossier : `App\Tests\Unit\...`, `App\Tests\Integration\...`.
- PHPUnit est configuré avec `failOnWarning`, `failOnNotice` et `failOnDeprecation` : un warning PHP fait échouer la suite.
- **Heure figée :** `use ClockSensitiveTrait;` puis `self::mockTime(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'))`. Gedmo lit l'horloge `clock` de Symfony, donc les dates enregistrées suivent. Toujours écrire le décalage (`+00:00`), sinon la date dépend du fuseau de PHP.
- Un double sans attente se crée avec `createStub()`, pas `createMock()`. `createMock()` est réservé aux doubles sur lesquels on appelle `expects()` ; sinon PHPUnit affiche un avertissement. La sortie de la suite doit rester « OK » sans réserve.

## Pièges connus

- Les clés JWT (`config/jwt/*.pem`) ne sont pas versionnées : `php bin/console lexik:jwt:generate-keypair` sur chaque machine.
- `.env` ne contient aucun secret : `APP_SECRET` et `JWT_PASSPHRASE` y sont vides exprès. Les valeurs de dev sont dans `.env.dev`, celles de test dans `.env.test` ; `tests/Unit/Config/EnvFilesTest` le vérifie. `.env.local` n'est pas lu en test.
- Avant de pousser : `composer check` (style, PHPStan niveau 6, conteneur, tests). PHPStan est à 0 erreur : ne pas ajouter de baseline ni de `@phpstan-ignore`. `composer fix-cs` corrige le style.
- Un docblock `@var` de propriété se place **au-dessus** des attributs `#[ORM\…]`, sinon PHPStan ne le lit pas.
- CORS (`config/packages/nelmio_cors.yaml`) : deux origines exactes, `CORS_SHOP_ORIGIN` et `CORS_ADMIN_ORIGIN`. Ne pas passer en mode expression régulière (`origin_regex`) : une valeur vide y autorise toutes les origines. Tout se règle sous le chemin `'^/api/'`, jamais dans `defaults`, que le bundle applique à tous les chemins.
- Un nouvel en-tête de réponse que les fronts doivent lire (comme `X-Total-Count`) s'ajoute à `expose_headers`, sinon le navigateur le leur cache. `CorsTest` couvre ces règles.
- La migration `Version20261116102013` (table `refresh_tokens`) est à jouer sur chaque base de dev.
- La migration `Version20261121195209` (`created_at` et `updated_at`) aussi : sans elle, toute route qui lit un produit ou un compte répond 500.
- **Mapping Doctrine :** un identifiant UUID se mappe en `Types::GUID`, jamais en `uuid` ; une colonne `DECIMAL` sur une propriété `string`. Sinon la valeur relue ne vaut jamais celle de la propriété : Doctrine réécrit la ligne à chaque `flush()`, et son `updated_at` avance sans raison (le compte connecté, la catégorie d'un produit…). `TimestampsTest` et les tests « sans changement » des repositories le vérifient.
- **Serveur sur la base de test** (pour Bruno) : `APP_ENV=test php -d variables_order=EGPCS -S 127.0.0.1:8001 -t public`. Sans le `-d`, le serveur intégré de PHP ne transmet pas `APP_ENV` à Symfony : il tourne en dev, **sur la base de dev**. Le vérifier avant d'écrire quoi que ce soit.
- La migration `Version20260612051158` ajoute des colonnes `NOT NULL` sans défaut : elle échoue si des utilisateurs existent déjà.
