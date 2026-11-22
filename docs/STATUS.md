# État d'avancement

> À mettre à jour **à la fin de chaque session** (skill `fin-de-session`). Ajouter les entrées les plus récentes en haut du journal.

## Où on en est

- **Phase en cours :** Phase 1 — Socle API pour deux fronts. La phase 0 est terminée.
- **Fait :** `P0-01` (contrôle d'accès), mergée dans `main` (PR #6).
- **Fait :** `P0-02` (validation des uploads), mergée dans `main` (PR #7).
- **Fait :** `P0-03` (repository des images), mergée dans `main` (PR #8).
- **Fait :** la base de test (première partie de `P0-10`), mergée dans `main` (PR #9).
- **Fait :** `P0-04` (gestion d'erreurs centralisée), mergée dans `main` (PR #10).
- **Fait :** `P0-05` (validation des entrées), mergée dans `main` (PR #11).
- **Fait :** `P0-07` (bugs ponctuels B5, B6, B7), mergée dans `main` (PR #12).
- **Fait :** `P0-06` (slug produit, B4), mergée dans `main` (PR #13).
- **Fait :** `P0-08` (secrets, S4), mergée dans `main` (PR #14).
- **Fait :** `P0-09` (outillage qualité), mergée dans `main` (PR #15).
- **Fait :** `P0-11` (CI GitHub Actions), mergée dans `main` (PR #16).
- **Fait :** `P0-12` (README d'installation), mergée dans `main` (PR #17).
- **Fait :** le nettoyage de la sortie des tests (PR #18) et la collection Bruno (PR #19), mergés dans `main`.
- **Fait :** la fin de `P0-10` (Foundry, factories, tests d'intégration des repositories), mergée dans `main` (PR #20). **La phase 0 est terminée.**
- **Fait :** `P1-01` (routes `/api/v1/shop` et `/api/v1/admin`), mergée dans `main` (PR #23, qui remplace la #22).
- **Fait :** `P1-06` (rôles et commande de création d'admin), mergée dans `main` (PR #24).
- **Fait :** `P1-05` (`/me` et refresh token), mergée dans `main` (PR #25).
- **Fait :** `P1-03` (contrat OpenAPI et Swagger UI), mergée dans `main` (PR #26).
- **Fait :** `P1-04` (format de liste : pagination, tri, filtres), mergée dans `main` (PR #27).
- **Fait :** `P1-02` (CORS pour les deux fronts), mergée dans `main` (PR #28).
- **En relecture :** `P1-08` (dates de création et de modification), sur la branche `feat/P1-08-timestamps`.
- **Prochaine tâche conseillée :** `P1-07` (limitation des tentatives de connexion). Les autres tâches de la phase 1 (`P1-09` à `P1-11`, les fronts) attendent les décisions ci-dessous, qui deviennent bloquantes à partir de `P1-09`.
- **Branche de travail :** `main`. La montée en Symfony 8.1 y est mergée.

## Décisions en attente

| Sujet | Options | Impact |
|---|---|---|
| Framework du front boutique | Next.js (React) / Nuxt (Vue) | P1-11 et tout `shop/` |
| Back-office | React-admin (proposé, ADR 0003) / EasyAdmin | P1-09, P1-10 et tout `admin/` |
| Paiement | Stripe / Mollie | P5-04 |
| Régime de TVA | Franchise en base (micro-entreprise) / TVA collectée | P4-03, P5-07 |
| Taille du VPS IONOS | S+ pendant le dev, M+ au lancement (proposé) | Phase 7 |

## Pièges connus en cours

- Droits par espace (`config/packages/security.yaml`) : tout `/api/v1/admin` exige `ROLE_ADMIN`, tout `/api/v1/shop` exige `ROLE_USER`, sauf les routes publiques listées. Une nouvelle route **publique** doit donc y être ajoutée explicitement, sinon elle répond 401.
- Les anciennes routes (`/create-product`, `/users/login`…) n'existent plus depuis `P1-01` : elles répondent 404. Il n'y a pas de redirection, aucun client n'existant encore.
- Les deux connexions (`/api/v1/shop/login` et `/api/v1/admin/login`) délivrent le même jeton : un client peut se connecter par celle du back-office, son jeton y sera simplement refusé partout (403).
- Sessions : le JWT vit 15 minutes, le refresh token 30 jours et ne sert qu'une fois (`POST /api/v1/token/refresh`). Un front doit donc remplacer son refresh token à chaque renouvellement, et ne pas lancer deux renouvellements en parallèle : le second serait refusé.
- Pas de déconnexion côté serveur : aucune route ne révoque un refresh token, et un JWT émis reste valable jusqu'à son expiration, même après suppression du rôle. Seule la suppression du compte coupe la session. Une route de déconnexion reste à écrire, au plus tard avec les fronts (`P1-09`, `P3-07`).
- Les refresh tokens expirés ne sont pas purgés tout seuls : prévoir `php bin/console gesdinet:jwt:clear` en tâche planifiée en production (`P7-01`).
- Contrat OpenAPI : `api/openapi.json` est versionné et un test le compare au contrat généré. Après tout changement de route, de DTO ou de vue, lancer `composer openapi:dump` et commiter le fichier, sinon la suite échoue.
- Contrat OpenAPI : la route `/api/v1/token/refresh` n'a pas de contrôleur (elle est traitée par le pare-feu) et les schémas d'erreur ne viennent pas d'une classe. Les deux sont décrits à la main dans `config/packages/nelmio_api_doc.yaml` ; `OpenApiContractTest` vérifie que les vraies réponses d'erreur y correspondent.
- Dans le contrat, un champ de réponse qui peut être `null` (par exemple `phone_number`) n'est pas marqué « requis », alors qu'il est toujours présent dans la réponse. Le client TypeScript généré le verra donc comme optionnel.
- La documentation (`/api/v1/doc` et `/api/v1/doc.json`) est publique, y compris en production : elle décrit l'API sans rien ouvrir. À restreindre dans `security.yaml` si ce n'est pas souhaité.
- Le bundle de refresh token n'a pas de recipe Flex officielle : il est enregistré à la main dans `config/bundles.php` et configuré dans `config/packages/gesdinet_jwt_refresh_token.yaml`.
- Un compte du back-office ne se crée que par la commande `php bin/console app:user:create-admin` (options `--editor` et `--promote`). Aucune route ne donne de rôle. Les rôles sont relus en base à chaque requête : une promotion prend effet sans se reconnecter.
- Rôles : `ROLE_ADMIN` inclut `ROLE_EDITOR`, qui inclut `ROLE_USER` (`role_hierarchy`). L'éditeur n'a accès qu'à `/api/v1/admin/products` ; toute nouvelle route du back-office est réservée aux administrateurs tant qu'on ne l'ouvre pas aux éditeurs dans `security.yaml`.
- Aucune commande ne retire un rôle : pour rétrograder un compte, il faut encore passer par SQL.
- Les tests fonctionnels signent de vrais JWT : sans `config/jwt/*.pem` (`php bin/console lexik:jwt:generate-keypair`), ils échouent. La CI génère sa propre paire à chaque exécution.
- CI (`.github/workflows/api.yml`) : elle ne se lance que si `api/` ou le workflow change. La branche `main` n'est pas protégée : rien n'empêche encore de merger une PR rouge. Si on rend les checks obligatoires dans GitHub, une PR qui ne touche pas `api/` resterait bloquée en attente, à cause de ce filtre par chemin : il faudra alors le retirer.
- CI : les actions sont épinglées par SHA de commit. Pour les mettre à jour, changer le SHA et le commentaire de version ensemble.
- Upload d'images : la limite applicative est de 5 Mo, mais PHP refuse avant elle tout fichier au-delà de `upload_max_filesize` (2 Mo par défaut) et ignore le corps entier au-delà de `post_max_size` (8 Mo par défaut). À régler dans l'image Docker (`P7-01`) : `upload_max_filesize = 5M` au minimum.
- Listes : toutes paginées (`items` + `pagination`, total aussi dans `X-Total-Count`). Le format et la marche à suivre pour une nouvelle liste sont dans `api/CLAUDE.md`, section « Listes ».
- CORS : seules deux origines sont autorisées, comparées exactement : `CORS_SHOP_ORIGIN` et `CORS_ADMIN_ORIGIN`. En dev, `http://localhost:3000` (boutique) et `http://localhost:5173` (back-office) ; un autre port se règle dans `api/.env.dev.local`. En production, les définir au déploiement (`P7-01`) : vides, elles n'autorisent aucun front.
- CORS : tout nouvel en-tête de réponse que les fronts doivent lire s'ajoute à `expose_headers` (aujourd'hui `X-Total-Count`).
- Listes : React-admin trie par `id` tant qu'on ne lui dit rien ; l'API refuse `sort=id` (422). Le data provider (`P1-10`) doit envoyer un vrai champ de tri, ou aucun : `created_at` en `desc` donne les plus récents d'abord.
- Listes : le filtre `scent` s'appelle maintenant `fragrance`, comme l'attribut qu'il filtre. Un paramètre inconnu étant ignoré, `scent=ambre` renvoie désormais la liste sans filtre, sans erreur.
- Listes : le tri des noms utilise la collation ICU `fr-x-icu` de PostgreSQL. L'image `postgres:16-alpine` l'a ; une image sans ICU ferait échouer ce tri (les tests d'intégration le verraient).
- Recherche de comptes (`q`) : insensible à la casse, mais pas aux accents (« elodie » ne trouve pas « Élodie »). Il faudrait l'extension `unaccent`.
- `page=1.5` répond bien 422, mais Symfony passe d'abord la valeur brute au constructeur du DTO et PHP émet un avertissement de conversion. Sans effet pour le client ; le test de ce cas tolère l'avertissement (`#[IgnoreDeprecations]`).
- `#[MapUploadedFile]`, `#[MapRequestPayload]` et `#[MapQueryString]` **ignorent silencieusement leurs contraintes** si `symfony/validator` est absent. Il est installé depuis `P0-02` : ne pas le retirer.
- Un body JSON doit être envoyé avec `Content-Type: application/json`. Sans cet en-tête, il est lu comme un formulaire et la réponse est un 400.
- Quand un champ obligatoire manque ou a le mauvais type, la réponse ne liste que ces erreurs-là ; les autres règles (format d'email, longueur…) ne sont vérifiées qu'une fois tous les champs présents et bien typés. Un formulaire peut donc demander deux allers-retours.
- Un id de route en majuscules répond 404 : `Requirement::UUID` n'accepte que les minuscules, la forme que l'API produit.
- Attributs produit : les clés inconnues (par exemple `wax`) sont toujours ignorées sans erreur. Seules `weight`, `fragrance`, `size`, `description` et `presentation` sont conservées et validées.
- Les fichiers `tests/http/` ne sont pas versionnés : s'ils envoient encore `"weight": "180g"`, la réponse est maintenant un 422. Le poids doit être un nombre.
- Adresse à l'inscription : elle est enregistrée comme adresse par défaut du compte. Aucune route ne permet encore de la modifier ou d'en ajouter (`P3-01`).
- Toutes les erreurs sortent en `{"error": "..."}`, y compris les 401 du pare-feu JWT (`JwtFailureListener`) et du renouvellement de jeton (`RefreshTokenFailureHandler`).
- Aucun bundle de logs n'est installé : les erreurs partent sur la sortie d'erreur du serveur PHP. Monolog et le suivi d'erreurs sont prévus en `P7-03`.
- En production, vider le cache (`cache:clear`) à chaque déploiement : un cache `prod` périmé garde l'ancien câblage des services et répond 500 en HTML.
- Collection Bruno (`api/bruno/`) : à mettre à jour quand une route change La lancer avec `bru run --env local -r` depuis `api/bruno`, l'API démarrée et le compte `admin@example.test` promu administrateur.
- Modifier un utilisateur n'enregistre pas ses adresses : `UserMapper::mapDomainToEntity()` les ignore. Sans effet aujourd'hui, aucune route ne modifiant les adresses ; à corriger avec `P3-01`.
- Foundry est une dépendance de dev : aucune classe de `src/` ne doit l'utiliser, sinon le conteneur de production ne se construit plus. Les factories vivent dans `tests/Factory/`.
- Dans un `WebTestCase`, une route protégée s'appelle avec le trait `App\Tests\Support\AuthenticatesRequests` (`authenticateAs()`), qui ne demande pas de base de données.
- Slug produit : il suit le nom. Renommer un produit change donc son slug, et l'ancienne URL ne redirige nulle part. À revoir avant l'ouverture de la boutique (`P2-07`) : slug figé, ou table de redirections.
- Slug produit : deux créations simultanées du même nom peuvent viser le même slug. L'index unique refuse la seconde, qui répond 500 au lieu d'obtenir un suffixe. Sans gravité tant que seul le back-office crée des produits.
- La migration `Version20261114170000` (colonne `slug`) est à jouer sur chaque base de dev : `php bin/console doctrine:migrations:migrate`. Elle calcule le slug des produits existants.
- Symfony 8.1 n'est pas LTS et sa fin de maintenance tombe en **janvier 2027** : monter en 8.2 dès sa sortie, prévue fin novembre 2026 (ADR 0005).
- Deux recipes Flex ont une mise à jour disponible : `doctrine/doctrine-bundle` et `symfony/framework-bundle` (celle de `symfony/security-bundle` est passée avec `P0-01`). Les appliquer avec `composer recipes:update` sur un arbre git propre, en relisant les diffs.
- `doctrine:schema:validate` passe entièrement depuis `P1-08` : côté entité, le prix est stocké en chaîne, comme PostgreSQL rend un `DECIMAL`. B15 reste ouvert sur le fond : le domaine calcule encore les prix en `float`. Il se règle avec le passage des montants en centimes, à faire avant la phase 4.
- Dates : la migration `Version20261121195209` est à jouer sur chaque base de dev (`php bin/console doctrine:migrations:migrate`). Sans elle, toute route qui lit un produit ou un compte répond 500 (« column created_at does not exist »). Les lignes existantes y reçoivent l'heure de la migration.
- Dates : `updated_at` suit la ligne elle-même, à la seconde près. Ajouter une image ne change pas celui du produit, ni une adresse celui du compte. Le sitemap (`P8-05`) devra prendre la plus récente des deux dates s'il veut tenir compte des images.
- Serveur sur la base de test (pour Bruno) : `APP_ENV=test php -d variables_order=EGPCS -S 127.0.0.1:8001 -t public`. Sans le `-d`, le serveur intégré de PHP ne transmet pas `APP_ENV` : il tourne en dev, sur la base de dev.
- Une PR se crée toujours vers `main`, même si sa branche part d'une autre branche de travail : la PR #22, créée vers la branche de la #21, a été mergée dans cette branche et n'est jamais arrivée dans `main`.
- Un test fonctionnel (`tests/Functional/`, `WebTestCase`) ne doit jamais atteindre la base : en CI elle est vide quand ils tournent, et la requête répond 500. En local le défaut ne se voit pas, la base ayant déjà son schéma. Pour le reproduire : `docker compose restart database_test`, puis `vendor/bin/phpunit tests/Functional`. Un parcours HTTP qui a besoin de la base étend `DatabaseTestCase` (voir `TokenRefreshTest`).
- Tests d'intégration (`tests/Integration/`) : ils exigent la base de test, c'est-à-dire le service `database_test` lancé par `docker compose up -d` depuis `api/` (`127.0.0.1:54320`). Sans elle ils **échouent**, avec un message qui dit quoi lancer. `vendor/bin/phpunit tests/Unit` reste utilisable sans base.
- La base de test vit en mémoire : elle est vide après chaque redémarrage du conteneur, et le premier test d'intégration rejoue les migrations. Rien à faire à la main.
- **Le dépôt GitHub est public.** L'ancien `.env.test` contenait le mot de passe du compte `postgres` d'une base sur `192.168.1.28:5433`. Il est retiré du fichier mais **reste lisible par tous dans l'historique git** : le changer sur ce serveur, et partout où il serait réutilisé. Réécrire l'historique ne suffirait pas, le mot de passe ayant déjà été publié.
- Secrets : `.env` n'en contient aucun (`APP_SECRET` et `JWT_PASSPHRASE` y sont vides exprès). Les valeurs de dev et de test sont dans `.env.dev` et `.env.test`, versionnées et publiques. En production, les fournir par de vraies variables d'environnement ou par les secrets Symfony, avec une nouvelle paire de clés JWT : sans elles l'application échoue au lieu de tourner avec une valeur connue (`P7-01`).
- `.env.local` n'est **pas** lu en environnement de test : pour une surcharge locale des tests, utiliser `.env.test.local`.
- Le port de la base de **dev** côté hôte est toujours attribué au hasard par `compose.override.yaml` et change à chaque recréation du conteneur : le relire avec `docker compose port database 5432`. Seule la base de test a un port fixe.

## Journal

### 2026-11-21 — P1-08 : dates de création et de modification (branche `feat/P1-08-timestamps`, en relecture)
- `stof/doctrine-extensions-bundle` installé, avec Gedmo Timestampable pour seule extension. Les cinq tables reçoivent `created_at` et `updated_at` (`timestamptz`), posées à l'enregistrement avec l'horloge de Symfony.
- Migration `Version20261121195209` : les lignes existantes reçoivent l'heure de la migration.
- Produits et comptes exposent `created_at` et `updated_at`, toujours en UTC (RFC 3339). Les listes de produits et de comptes se trient aussi par `created_at`.
- Modifier un produit ou un compte renvoie la date de cette modification : les deux use cases relisent l'objet après l'enregistrement.
- Trouvé en écrivant les tests : chaque `flush()` réécrivait toutes les lignes chargées, parce que les identifiants étaient mappés en `uuid` sur des chaînes et le prix `DECIMAL` sur un `float`. C'était sans effet visible jusqu'ici, mais `updated_at` aurait avancé à chaque écriture pour le compte connecté ou la catégorie d'un produit. Les identifiants passent en `guid` et le prix est stocké en chaîne, sans changement de schéma. `doctrine:schema:validate` passe maintenant entièrement.
- Vérifications : `composer check` vert, PHPUnit « OK (439 tests) » ; dix régressions introduites exprès, toutes détectées ; migration essayée sur des lignes existantes, avec aller-retour `down`/`up` ; collection Bruno, 20 requêtes sur 20, contre un serveur sur la base de test.

### 2026-11-21 — P1-02 : CORS pour les deux fronts (branche `feat/P1-02-cors`, en relecture)
- `nelmio/cors-bundle` installé. La boutique et le back-office peuvent lire les réponses de l'API depuis le navigateur ; aucune autre origine (constat S7 de l'audit).
- Deux variables, une par front : `CORS_SHOP_ORIGIN` et `CORS_ADMIN_ORIGIN`, comparées exactement. Vides par défaut dans `.env` : sans réglage de production, aucun front n'est autorisé.
- Le mode « expression régulière » proposé par la recette est écarté : une valeur vide y autorise toutes les origines, et une expression mal ancrée un domaine voisin.
- Méthodes `GET`, `POST`, `PUT`, `DELETE` ; en-têtes `Authorization` et `Content-Type` ; `X-Total-Count` exposé ; pas de cookies (`allow_credentials: false`) ; réponse préliminaire mise en cache une heure.
- Les requêtes préliminaires sont traitées avant le pare-feu : elles n'ont pas besoin de jeton. Les réponses d'erreur (401, 404, 422) portent les en-têtes CORS, pour que les fronts puissent les lire.
- Vérifications : `CorsTest` (18 tests, qui passent aussi sur une base de test vide, comme en CI) et `EnvFilesTest` étendu ; cinq erreurs de configuration introduites exprès (mode expression régulière, total non exposé, cookies autorisés, chemin erroné, CORS sur tous les chemins) sont toutes détectées ; essai réel avec `curl`.

### 2026-11-21 — P1-04 : format standard des listes (branche `feat/P1-04-list-format`, en relecture)
- Les trois listes (produits de la boutique, produits et comptes du back-office) renvoient une page : `{"items": [...], "pagination": {"page", "per_page", "total", "total_pages"}}`, et le total dans l'en-tête `X-Total-Count`.
- Paramètres communs : `page` (1 à 1 000 000), `per_page` (1 à 100, 20 par défaut), `sort`, `order` (`asc`/`desc`). Tri des produits par `name` ou `price`, des comptes par `email`, `first_name` ou `last_name`. Nouveau filtre des comptes : `q`.
- Le filtre `scent` devient `fragrance`. `GetFilteredProductsUseCase` et `GetUsersUseCase` deviennent `ListProductsUseCase` et `ListUsersUseCase`.
- Domaine : `Pagination`, `Page`, `SortDirection`, critères et champs de tri par ressource. Dette corrigée au passage : `ProductRepositoryInterface` n'importe plus de DTO, `UserRepositoryInterface` n'utilise plus `Symfony\Uid` (identifiants en `string`).
- Tri des noms dans l'ordre alphabétique français (la base triait octet par octet : « Zèbre » avant « ambre »), départagé par l'id pour qu'aucun élément n'apparaisse sur deux pages.
- Une page se charge en 3 requêtes quel que soit son nombre d'éléments ; auparavant, une requête de plus par produit pour ses images.
- Vérifications : `composer check` vert, PHPUnit « OK (392 tests) » ; cinq régressions introduites exprès (collation, chargement groupé, échappement de `%` et `_`, départage par l'id, en-tête du total) sont toutes détectées ; collection Bruno, 20 requêtes sur 20.

### 2026-11-16 — P1-03 : contrat OpenAPI et Swagger UI (branche `feat/P1-03-openapi`, en relecture)
- `nelmio/api-doc-bundle` génère le contrat des 19 opérations : Swagger UI sur `/api/v1/doc`, JSON sur `/api/v1/doc.json`, copie versionnée dans `api/openapi.json` (`composer openapi:dump`).
- Les réponses sont maintenant des objets typés, les « vues » (`Presenter/*/View/`), que les presenters renvoient. Le contrat est généré à partir d'elles : la documentation ne peut pas s'écarter de la réponse.
- Changements du contrat, faits avant que les fronts ne le consomment :
  - les réponses sont en `snake_case` comme les requêtes (`first_name`, `phone_number`, `postal_code`, `is_default`, `sort_order`) ;
  - créer un produit ou envoyer une image répond `{"id": "..."}` (auparavant un texte nu, ou `{"result", "data"}`) ;
  - modifier un produit renvoie le produit, comme modifier un compte renvoie le compte ;
  - les 401 du pare-feu JWT et du renouvellement de jeton sortent en `{"error": "..."}`.
- `OpenApiContractTest` (12 tests) : toute route est documentée, une opération est dite publique exactement quand elle l'est, toutes les références se résolvent, tout est en `snake_case`, les vraies erreurs correspondent aux schémas, le fichier versionné est à jour.
- Vérifications : `composer check` vert ; Swagger UI et ses scripts servis en local, sans CDN ; collection Bruno, 20 requêtes sur 20.

### 2026-11-16 — P1-05 : `/me` et refresh token (branche `feat/P1-05-me-refresh-token`, en relecture)
- `GET`, `PUT` et `DELETE /api/v1/shop/me` remplacent `/api/v1/shop/users/{id}` : le compte est toujours celui du jeton, le client n'a plus à connaître son identifiant. `UserAccountVoter`, devenu inutile, est supprimé.
- `GET /api/v1/admin/me` donne l'identité et les rôles du compte connecté au back-office (ouvert aux éditeurs).
- La connexion renvoie un `refresh_token` en plus du `token`. `POST /api/v1/token/refresh` l'échange contre un nouveau couple.
- Réglages : JWT de 15 minutes (une heure auparavant, par défaut), refresh token de 30 jours, à usage unique, stocké haché. Migration `Version20261116102013` (table `refresh_tokens`).
- Le refresh token est émis par un port du domaine (`RefreshTokenIssuerInterface`), implémenté avec `gesdinet/jwt-refresh-token-bundle` 3.1.
- Vérifications : `composer check` vert ; 12 tests du parcours complet sur PostgreSQL (connexion, `/me`, renouvellement, jeton rejoué, jeton haché en base, compte supprimé) ; collection Bruno, 20 requêtes sur 20.

### 2026-11-16 — P1-06 : rôles et commande de création d'admin (branche `feat/P1-06-roles-admin-command`, en relecture)
- Trois rôles emboîtés, décrits dans `Domain/Model/User/Role` : client, éditeur, administrateur. L'éditeur gère les produits, sans accès aux comptes clients.
- Commande `app:user:create-admin` : crée un administrateur, ou un éditeur avec `--editor`, ou donne le rôle à un compte existant avec `--promote`. Le mot de passe est demandé en saisie masquée, 12 caractères minimum.
- Deux use cases derrière : `CreateBackOfficeUserUseCase` et `GrantRoleUseCase`. `User::grantRole()` refuse un rôle inconnu.
- Le README et la collection Bruno n'utilisent plus la promotion en SQL.
- Vérifications : `composer check` vert, PHPUnit « OK (315 tests) » ; commande lancée en vrai sur la base de test ; en faussant la règle d'accès de l'éditeur puis la hiérarchie des rôles, les tests échouent bien.

### 2026-11-16 — P1-01 : routes `/api/v1/shop` et `/api/v1/admin` (branche `feat/P1-01-shop-admin-routes`, en relecture)
- Les contrôleurs sont répartis en deux espaces : `Controller/Shop/` (catalogue en lecture, inscription, connexion, compte du client) et `Controller/Admin/` (connexion, produits, utilisateurs). Les mêmes use cases servent aux deux.
- Routes REST : `POST /api/v1/admin/products` remplace `/create-product`, `PUT /api/v1/admin/products/{id}` remplace `/edit-product/{id}`, `POST /api/v1/admin/products/{id}/pictures` remplace `/upload-picture/{id}`, etc. Le tableau complet est dans le `README.md`.
- `GET /products` et `GET /products/filter` fusionnent en `GET /api/v1/shop/products`, dont les filtres sont optionnels. `GetProductsUseCase`, devenu inutile, est supprimé.
- Le back-office gagne quatre routes : liste et détail des produits, détail, modification et suppression d'un utilisateur.
- `access_control` protège chaque espace en bloc. L'envoi d'image répond maintenant 201 au lieu de 200.
- Toutes les routes sont sous le préfixe de version `/api/v1`, décidé en review et défini une seule fois dans `config/routes.yaml`.
- Vérifications : `composer check` vert, PHPUnit « OK (293 tests) » ; collection Bruno mise à jour et lancée contre un serveur de test, 18 requêtes sur 18.

### 2026-11-16 — Suppression des tests unitaires de repositories (branche `chore/P0-10-remove-mocked-repository-tests`, en relecture)
- Les quatre tests unitaires de `tests/Unit/.../Persistence/Doctrine/` simulaient l'EntityManager : ils vérifiaient les appels faits à Doctrine, pas le résultat en base, et avaient laissé passer le bug B1. Supprimés à la demande, les tests d'intégration les remplacent.
- Vérification : `composer check` vert, PHPUnit « OK (272 tests) ».

### 2026-11-16 — Fin de P0-10 : Foundry et tests d'intégration des repositories (branche `test/P0-10-foundry-integration-tests`, en relecture)
- `zenstruck/foundry` installé (dev). Cinq factories dans `tests/Factory/` : catégorie, produit, image, utilisateur, adresse.
- `DatabaseTestCase` charge les factories. Le `ResetDatabase` de Foundry n'est pas utilisé : la base reste préparée par les migrations puis une transaction annulée par test.
- Nouveaux tests d'intégration sur PostgreSQL : repository catégorie (5), produit (16, dont les 10 combinaisons de filtres en SQL natif) et utilisateur (9).
- Le fichier `src/Story/AppStory.php` posé par la recipe de Foundry est retiré : il aurait cassé le conteneur de production, Foundry n'y étant pas installé.
- Vérifications : `composer check` vert, PHPUnit « OK (297 tests) » ; conteneur de production valide ; trois régressions introduites exprès (filtre de prix, filtre de taille, mise à jour du nom) sont bien détectées par les nouveaux tests.

### 2026-11-16 — Sortie des tests nettoyée, partie de P0-10 (branche `chore/P0-10-clean-test-output`, en relecture)
- `composer check` se terminait par « OK, but there were issues! » avec 64 avertissements PHPUnit, et affichait une ligne `[error] Uncaught exception` au milieu des tests. Rien n'échouait, mais la sortie ressemblait à des erreurs.
- Les mocks sans attente sont devenus des stubs (`createStub`). Quatre vérifications d'arguments qui ne s'appuyaient sur aucune attente sont devenues de vraies attentes (`expects($this->once())`).
- 27 tests gardent des mocks créés dans `setUp()` dont ils n'attendent rien : ils portent `#[AllowMockObjectsWithoutExpectations]`.
- En environnement de test, le logger est un `NullLogger` : les erreurs volontaires des tests ne s'affichent plus.
- Vérification : `composer check` vert, PHPUnit affiche « OK (267 tests, 1254 assertions) ».

### 2026-11-16 — P0-12 : README d'installation (branche `docs/P0-12-readme`, en relecture)
- Nouveau `README.md` à la racine : prérequis, installation de l'API en 7 étapes, création d'un compte administrateur, routes actuelles, commandes de vérification, fichiers d'environnement, problèmes fréquents.
- La procédure a été répétée dans un clone neuf du dépôt, sur une base jetable : dépendances, clés JWT, migrations, fixtures, inscription, promotion et connexion d'un administrateur.
- Non répété : `docker compose up -d` lui-même, pour ne pas perturber les conteneurs déjà lancés sur ce PC.
- Piste d'amélioration : donner un port fixe à la base de dev supprimerait l'étape 3 du README (`.env.local`) et le piège du port qui change.

### 2026-11-16 — P0-11 : CI GitHub Actions (branche `chore/P0-11-ci`, en relecture)
- Nouveau workflow `.github/workflows/api.yml`, lancé sur les PR et sur `main` quand `api/` change.
- Job `lint` (PHP 8.4) : `composer validate`, style, PHPStan, lint du conteneur.
- Job `tests` (PHP 8.4 et 8.5) : PHPUnit avec un service PostgreSQL 16 sur le port 54320 attendu par `.env.test`, et une paire de clés JWT générée à la volée.
- Sécurité : actions épinglées par SHA, jeton en lecture seule, aucun secret GitHub nécessaire.
- Vérification : première exécution verte sur la PR #16 en une minute environ (267 tests sur chaque version de PHP).

### 2026-11-16 — P0-09 : outillage qualité (branche `chore/P0-09-quality-tooling`, en relecture)
- Psalm est retiré (config `pslam.xml` cassée et dépendance `vimeo/psalm`), ainsi que l'ancien `.php_cs`. PHPStan fait l'analyse statique.
- Scripts Composer réparés et complétés : `composer lint` (style, PHPStan, conteneur), `composer test`, `composer check` (les deux), `composer fix-cs`.
- PHP-CS-Fixer est passé sur tout le code (76 fichiers), avec la règle `declare_strict_types` : tous les fichiers PHP déclarent `strict_types`.
- `strict_types` a révélé un bug caché : `CreateProductUseCase` passait un objet `Uuid` à un repository qui attend une chaîne. Corrigé.
- PHPStan niveau 6 : 0 erreur, contre 54. Au passage, `Category::getPictures()` plantait tant qu'aucune image n'était ajoutée (propriété non initialisée) : corrigé.
- Vérifications : `composer check` vert (style, PHPStan, conteneur, PHPUnit 267 tests) ; essai HTTP réel de toutes les routes sous `strict_types`.

### 2026-11-14 — P0-08 : secrets, S4 (branche `fix/P0-08-secrets`, en relecture)
- `.env` ne contient plus aucun secret : `JWT_PASSPHRASE` y est vidé, comme `APP_SECRET`.
- Nouveau `.env.dev` versionné (retiré de `.gitignore`) : `APP_SECRET` de dev et passphrase de la paire de clés locale. `.env.test` reçoit la même passphrase et est nettoyé (`APP_ENV` et `XDEBUG_MODE` y étaient sans effet).
- La passphrase JWT n'a pas changé : les clés déjà générées sur chaque PC restent valables.
- `tests/Unit/Config/EnvFilesTest` échoue si un secret revient dans `.env`.
- Audit de l'historique git : aucune clé privée ni clé d'API ; seuls y figurent la passphrase JWT de dev et l'ancien mot de passe de la base `192.168.1.28` (voir les pièges).
- Vérifications : PHPUnit 267 tests verts (6 nouveaux) ; en `prod`, `APP_SECRET` et `JWT_PASSPHRASE` sont bien vides sans configuration.

### 2026-11-14 — P0-06 : slug produit persisté, B4 (mergée, PR #13)
- La table `products` a une colonne `slug` unique et non nulle. Le slug est enregistré et relu : plus de `slug-placeholder`.
- Le slug est généré par le `SluggerInterface` de Symfony, derrière le port `Domain/Service/Catalog/SlugGeneratorInterface`. « Bougie Été à l'Ambre » donne `bougie-ete-a-l-ambre`.
- `ProductSlugResolver` rend le slug unique : un nom déjà pris reçoit `-2`, `-3`… Un nom sans aucun caractère utilisable (`!!!`) donne `product`.
- À la modification, le slug n'est recalculé que si le nom change.
- La migration `Version20261114170000` calcule le slug des produits déjà en base, avec la même règle.
- Vérifications : PHPUnit 261 tests verts (22 nouveaux) ; PHPStan 54 erreurs, inchangé, aucune dans le nouveau code ; migration essayée sur la base de test avec des produits existants, dont deux homonymes ; essai HTTP réel (création de deux homonymes, renommage, relecture).

### 2026-11-14 — P0-07 : bugs ponctuels B5, B6 et B7 (mergée, PR #12)
- B5 : un produit sans `weight` a maintenant un poids `null`, sans warning PHP. Créer ou lire un tel produit répondait 500. Les attributs sont validés à l'entrée : le poids doit être un nombre positif, donc `"180g"` est refusé au lieu d'être lu comme 180.
- B6 : `Product::updateDetails()` passe par `setPrice()`. Le modèle refuse un prix négatif à la modification comme à la création.
- B7 : le téléphone et l'adresse fournis à l'inscription sont enregistrés. L'adresse devient l'adresse par défaut du compte, de type `shipping` sauf mention contraire.
- Le format du téléphone est défini une seule fois, dans `PhoneNumber::PATTERN`, et réutilisé par les DTO.
- B9 était déjà fermé par `P0-05`.
- Vérifications : PHPUnit 239 tests verts (20 nouveaux) ; PHPStan 54 erreurs contre 58, aucune dans le nouveau code ; essai HTTP réel sur la base de test (inscription avec téléphone et adresse, produit sans poids créé puis relu, prix négatif et poids `"180g"` refusés).

### 2026-11-13 — P0-05 : validation des entrées (branche `fix/P0-05-input-validation`, en relecture)
- Les bodies et les filtres sont convertis en DTO et validés par `#[MapRequestPayload]` et `#[MapQueryString]`, avec des contraintes `Assert` sur les DTO. Les quatre mappers manuels et les `json_decode` des contrôleurs sont supprimés.
- Les ids de route sont contraints par `Requirement::UUID` : un id invalide répond 404 au lieu de 500.
- Une erreur de validation répond 422 avec la liste des champs fautifs, nommés comme le client les a envoyés (`first_name`).
- Bugs fermés : le reste du tableau B8, B9 (un `PUT` partiel effaçait le nom), B16 (un body vide créait un produit sans nom à prix 0), S11 (longueurs).
- Ajout hors audit : mot de passe de 8 caractères minimum à l'inscription. La vraie politique de mots de passe reste `P3-02`.
- `GetFilteredProductsUseCase` reçoit maintenant un `GetProductsFilterDto` au lieu d'un tableau. Les DTO `…RequestDTO` sont renommés `…RequestDto`.
- Vérifications : PHPUnit 219 tests verts (36 de plus) ; PHPStan 58 erreurs contre 63, aucune dans le nouveau code ; essai HTTP réel sur la base de test (inscription, doublon, login, filtres, ids).

### 2026-11-13 — P0-04 : gestion d'erreurs centralisée (branche `fix/P0-04-centralized-error-handling`, en relecture)
- Les contrôleurs n'ont plus aucun `try/catch` : `ExceptionListener` est le seul endroit où une exception devient une réponse HTTP.
- Les exceptions métier disent leur type par une interface (`NotFound` → 404, `Conflict` → 409, `Validation` → 422, `Authentication` → 401). Nouvelles classes : `ProductNotFoundException`, `InvalidCredentialsException`, `InvalidEmailException`, `InvalidPhoneNumberException`. `CategorieNotFoundException` est renommée `CategoryNotFoundException`. B17 est corrigé au passage.
- S3 corrigé : le message d'une erreur 5xx n'est plus renvoyé au client hors mode debug, il est journalisé. Avant, rien n'était journalisé du tout.
- `LoginUserUseCase` ne dépend plus de `Symfony\Component\Security`.
- Changements visibles pour un client de l'API :
  - toutes les erreurs sortent en `{"error": "..."}` (certaines sortaient en `{"message": "..."}`) ;
  - produit ou catégorie inconnus : 404 au lieu de 500 ou 400 ;
  - email ou téléphone invalide : 422 au lieu de 400.
- Vérifications : PHPUnit 183 tests verts (21 nouveaux) ; PHPStan 63 erreurs comme avant, aucune dans le nouveau code ; essai HTTP en mode production (500 générique côté client, détail SQL dans le journal serveur).

### 2026-11-13 — Base de test dédiée (branche `chore/P0-10-test-database`, en relecture)
- Nouveau service `database_test` dans `api/compose.override.yaml` : PostgreSQL 16 jetable, données en mémoire, exposé uniquement sur `127.0.0.1:54320` (les ports 5432 et 5433 étaient déjà pris sur le PC de dev).
- `api/.env.test` pointe vers ce service et n'est plus listé dans `.gitignore` (il était à la fois versionné et ignoré).
- `tests/Support/DatabaseTestCase.php` : classe de base des tests d'intégration. Elle joue les migrations une fois par lancement de PHPUnit, refuse toute base dont le nom ne finit pas par `_test`, et annule les données de chaque test.
- Le « Skipped » provisoire de `P0-03` est supprimé : sans base, les tests d'intégration échouent.
- Vérifications : PHPUnit 162 tests verts avec la base lancée, 5 échecs explicites base arrêtée, `tests/Unit` seul (121 tests) vert sans base ; PHPStan 63 erreurs comme avant.

### 2026-11-13 — P0-03 : repository des images (mergée, PR #8)
- B1 corrigé : `DoctrinePictureRepository::findById`, `findByProductId` et `findAll` interrogent maintenant l'entité `DoctrinePicture` et renvoient des `Picture` du domaine via `PictureMapper`. `findByProductId` filtre sur l'association `product` (le champ `productId` n'existait pas).
- B2 corrigé : `inversedBy: 'images'` retiré de `DoctrinePicture::$category`. La relation devient unidirectionnelle, sans changement de schéma ni migration.
- Premier test d'intégration contre une vraie base PostgreSQL (`tests/Integration/…/DoctrinePictureRepositoryTest.php`), puisque les mocks avaient masqué B1.
- **Écart avec la roadmap :** elle demandait que `doctrine:schema:validate` passe. Il échoue encore à cause de B15, qui est hors périmètre.
- Vérifications :
  - PHPUnit : 162 tests passent avec la base de test (6 nouveaux), 65 notices contre 68 ; sans base, 157 passent et 5 sont ignorés ;
  - PHPStan : 63 erreurs, contre 67 avant, aucune dans le nouveau code.

### 2026-11-13 — P0-02 : validation des uploads d'images (mergée, PR #7)
- `POST /upload-picture/{productId}` n'accepte plus que de vraies images JPEG, PNG ou WebP de 5 Mo au plus (constat S2). Le type est lu dans le contenu du fichier, pas dans son nom ni dans le `Content-Type` envoyé par le client. SVG, GIF, HTML et PHP déguisés sont refusés avec un 422, avant d'atteindre le use case.
- La validation est déclarée sur l'argument du contrôleur avec `#[MapUploadedFile]` et `Assert\Image`.
- Dépendances ajoutées : `symfony/validator` et `symfony/serializer`, toutes deux aussi nécessaires à `P0-05`.
- Non traité, comme prévu : le réencodage des images (suggéré « idéalement » par l'audit) et la limite de dimensions en pixels.
- Vérifications :
  - PHPUnit : 156 tests passent (10 nouveaux), avec les mêmes 68 notices ;
  - PHPStan : 67 erreurs, comme avant, aucune dans le nouveau code ;
  - `composer audit` ne remonte aucune vulnérabilité.

### 2026-11-13 — P0-01 : contrôle d'accès (mergée, PR #6)
- `access_control` activé, fermé par défaut :
  - public : `POST /users/login`, `POST /users/create`, `GET /products`, `GET /products/filter`, `GET /product/{id}` ;
  - `ROLE_USER` : `GET`, `PUT` et `DELETE /users/{id}` ;
  - `ROLE_ADMIN` : tout le reste, dont `GET /users/list`, les écritures du catalogue et les uploads.
- `UserAccountVoter` : `/users/{id}` n'est accessible qu'au propriétaire du compte ou à un admin, en lecture comme en écriture (la roadmap ne citait que `PUT` et `DELETE`, mais `GET` expose les mêmes données personnelles).
- Recipe Flex `symfony/security-bundle` mise à jour.
- Vérifications :
  - PHPUnit : 146 tests passent (34 nouveaux), avec les mêmes 68 notices ;
  - PHPStan : 67 erreurs, comme avant, aucune dans le nouveau code ;
  - essai HTTP réel avec PostgreSQL et le vrai provider Doctrine : 401 sans token, 403 sur le compte d'un autre et sur les routes admin, 200 sur son propre compte, puis 200 partout une fois le compte promu admin.

### 2026-11-13 — Montée de version Symfony 7.4 → 8.1
- L'API passe en **Symfony 8.1.11** sur la branche `chore/upgrade-symfony-8.1`, avec PHP ≥ 8.4 et DoctrineBundle 3.4. ORM 3.7, doctrine/collections 3 et liip/imagine 2.18 suivent. Décision consignée dans l'ADR 0005.
- Config Doctrine nettoyée, il n'y a plus aucune dépréciation (`debug:container --deprecations`) en dev, test et prod. `config/reference.php` est régénéré automatiquement par Symfony.
- Vérifications :
  - PHPUnit : les 112 tests passent, avec les mêmes 68 notices qu'avant ;
  - PHPStan : 67 erreurs, contre 76 avant, et aucune nouvelle ;
  - `lint:container` passe ;
  - `composer audit` ne remonte aucune vulnérabilité, contre 45 avant.
- **Sur un autre PC :** il faut PHP ≥ 8.4, puis lancer `composer install` et `php bin/console cache:clear` depuis `api/`.

### 2026-10-21 — Audit, roadmap et mise en place du monorepo
- Audit complet de l'API : comptes rendus 00 à 06 dans `docs/compte-rendus/`.
- Branche `feat/data-product` vérifiée : déjà mergée dans `main` (PR #2), rien à merger.
- Roadmap en 10 phases et 80 tâches, avec estimations humain / avec IA (`07-roadmap.md`), importée dans ClickUp.
- Hébergement choisi : rester chez IONOS (domaine actuel), sur un VPS avec Docker Compose et Caddy. WordPress reste en ligne jusqu'à la bascule. Aucune donnée WordPress à récupérer.
- Passage en monorepo : `api/`, `shop/`, `admin/`, `infra/`, `docs/`. Le dépôt GitHub `parfumee-api` est renommé `parfumee`.
- Création de `CLAUDE.md`, `api/CLAUDE.md`, des ADR, des agents et des skills Claude.
