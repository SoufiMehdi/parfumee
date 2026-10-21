# 06 — Recommandations et plan d'action

Les actions sont classées par priorité. Les références (B*, S*, T*) renvoient aux comptes rendus 03, 04 et 05.

## Priorité 1 — Avant toute mise en ligne (≈ 1 à 2 jours)

| # | Action | Réf. |
|---|---|---|
| 1 | Remplir `access_control` (routes admin, routes connectées, routes publiques) et ajouter un Voter « propriétaire du compte » sur `PUT`/`DELETE /users/{id}` | S1 |
| 2 | Valider les uploads (type image, taille max, pas de SVG) | S2 |
| 3 | Ne plus renvoyer `$exception->getMessage()` hors mode debug ; logger les erreurs | S3 |
| 4 | Définir `APP_SECRET`, générer les clés JWT, sortir les vrais secrets de `.env` / `.env.test` (et changer le mot de passe BDD s'il a fuité dans l'historique git) | S4 |
| 5 | Corriger `DoctrinePictureRepository` (entité `DoctrinePicture` + `PictureMapper`) | B1 |
| 6 | Corriger le mapping `inversedBy: 'images'`, puis lancer `doctrine:schema:validate` | B2 |

## Priorité 2 — Fiabilité (≈ 2 à 3 jours)

| # | Action | Réf. |
|---|---|---|
| 7 | Ajouter une colonne `slug` (unique) à `products`, la générer avec `SluggerInterface` et la mapper dans les deux sens | B4 |
| 8 | Corriger `AttributesMapper` (`isset` avant le cast) | B5 |
| 9 | Passer par `setPrice()` dans `Product::updateDetails()` | B6 |
| 10 | Prendre en compte le téléphone et l'adresse à l'inscription | B7 |
| 11 | **Uniformiser la gestion d'erreurs** : exceptions métier dédiées (`ProductNotFoundException`…) implémentant `DomainExceptionInterface`, mappage centralisé dans l'`ExceptionListener` (NotFound → 404, Validation → 422, Conflict → 409), puis suppression des `try/catch` des contrôleurs | B8, B17 |
| 12 | Valider les entrées avec `#[MapRequestPayload]` + contraintes `Assert` sur les DTO (Symfony 6.3+) : cela remplace les mappers manuels, renvoie automatiquement des 422 et fait disparaître les 500 sur champs manquants | B8, B9, B16, S11 |
| 13 | Valider les `{id}` via `requirements: ['id' => Requirement::UUID]` dans les routes | B8 |
| 14 | Rendre l'upload transactionnel (BDD puis fichier, ou suppression du fichier en cas d'échec) | B11 |
| 15 | Ajouter un `DEFAULT ''` ou une migration en deux temps pour `first_name` et `last_name` | B3 |

## Priorité 3 — Qualité et architecture (en continu)

| # | Action | Réf. |
|---|---|---|
| 16 | Tests d'intégration Doctrine (`KernelTestCase` + base de test + fixtures) et tests fonctionnels du `UserController` et du login | T1, T2 |
| 17 | Ranger `ProductControllerTest` dans `tests/Functional/` et corriger les namespaces | T3, T4 |
| 18 | Réparer PHPStan (script), supprimer ou réécrire la config Psalm, supprimer `.php_cs`, lancer `php-cs-fixer fix` | 05 |
| 19 | `declare(strict_types=1)` partout | 05 |
| 20 | CI GitHub Actions : `composer install` → `php-cs-fixer --dry-run` → `phpstan` → `phpunit` avec un service PostgreSQL | 05 |
| 21 | Purifier le domaine : déplacer `GetProductsFilterDto` vers un objet `ProductFilter` du domaine, abstraire `UploadedFile` (passer un flux ou un chemin), homogénéiser les ids (`string` partout ou un VO `ProductId`/`UserId`) | 01 |
| 22 | Déplacer les `*RequestMapper` (qui dépendent de `Request`) dans l'Infrastructure | 01 |
| 23 | Rédiger un `README` : installation, `docker compose up`, migrations, `lexik:jwt:generate-keypair`, fixtures, lancement des tests | 05 |

## Priorité 4 — Fonctionnel e-commerce (feuille de route)

1. **Catalogue** : suppression produit, CRUD catégories, pagination et tri, stock, statut publié/brouillon, lookup par slug, URL de miniature LiipImagine dans le presenter.
2. **Comptes** : endpoint `/users/me`, gestion des adresses, changement et réinitialisation du mot de passe, refresh token JWT.
3. **Panier** (stocké côté serveur ou côté client).
4. **Commandes** : création depuis le panier, statuts, historique client.
5. **Paiement** (Stripe ou équivalent) et webhooks.
6. **Transverse** : documentation OpenAPI (`nelmio/api-doc-bundle`), CORS, rate-limiting, préfixe `/api/v1`, routes REST cohérentes (`POST /products`, `PUT /products/{id}` plutôt que `/create-product`, `/edit-product/{id}`).

## Bilan

Le projet a une **base architecturale de bonne qualité**, rare pour un projet personnel à ce stade : hexagonal respecté, tests présents, mapping propre. Les problèmes relevés sont surtout de la **finition** (validation, erreurs HTTP, sécurité non activée) et quelques **bugs ponctuels** faciles à corriger. En traitant les priorités 1 et 2, l'API devient une base saine pour construire le panier et les commandes.
