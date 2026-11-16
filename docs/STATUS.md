# État d'avancement

> À mettre à jour **à la fin de chaque session** (skill `fin-de-session`). Ajouter les entrées les plus récentes en haut du journal.

## Où on en est

- **Phase en cours :** Phase 0 — Stabiliser l'existant.
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
- **En relecture :** `P0-12` (README d'installation), sur la branche `docs/P0-12-readme`.
- **Prochaine tâche conseillée :** le reste de `P0-10`, dernière tâche de la phase 0.
- **Reste de `P0-10` :** Foundry, les factories et les tests d'intégration des repositories produit, catégorie et utilisateur.
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

- Toute route absente de `access_control` (`config/packages/security.yaml`) est réservée à `ROLE_ADMIN`. Une nouvelle route publique ou client doit donc y être ajoutée explicitement, sinon elle répond 401/403.
- Il n'existe aucun moyen de créer un administrateur avant `P1-06`. En attendant, promouvoir un compte en SQL : `UPDATE users SET roles = '["ROLE_ADMIN"]' WHERE email = '…';`. Les rôles sont relus en base à chaque requête, donc pas besoin de se reconnecter.
- Les tests fonctionnels signent de vrais JWT : sans `config/jwt/*.pem` (`php bin/console lexik:jwt:generate-keypair`), ils échouent. La CI génère sa propre paire à chaque exécution.
- CI (`.github/workflows/api.yml`) : elle ne se lance que si `api/` ou le workflow change. La branche `main` n'est pas protégée : rien n'empêche encore de merger une PR rouge. Si on rend les checks obligatoires dans GitHub, une PR qui ne touche pas `api/` resterait bloquée en attente, à cause de ce filtre par chemin : il faudra alors le retirer.
- CI : les actions sont épinglées par SHA de commit. Pour les mettre à jour, changer le SHA et le commentaire de version ensemble.
- Upload d'images : la limite applicative est de 5 Mo, mais PHP refuse avant elle tout fichier au-delà de `upload_max_filesize` (2 Mo par défaut) et ignore le corps entier au-delà de `post_max_size` (8 Mo par défaut). À régler dans l'image Docker (`P7-01`) : `upload_max_filesize = 5M` au minimum.
- `#[MapUploadedFile]`, `#[MapRequestPayload]` et `#[MapQueryString]` **ignorent silencieusement leurs contraintes** si `symfony/validator` est absent. Il est installé depuis `P0-02` : ne pas le retirer.
- Un body JSON doit être envoyé avec `Content-Type: application/json`. Sans cet en-tête, il est lu comme un formulaire et la réponse est un 400.
- Quand un champ obligatoire manque ou a le mauvais type, la réponse ne liste que ces erreurs-là ; les autres règles (format d'email, longueur…) ne sont vérifiées qu'une fois tous les champs présents et bien typés. Un formulaire peut donc demander deux allers-retours.
- Un id de route en majuscules répond 404 : `Requirement::UUID` n'accepte que les minuscules, la forme que l'API produit.
- Attributs produit : les clés inconnues (par exemple `wax`) sont toujours ignorées sans erreur. Seules `weight`, `fragrance`, `size`, `description` et `presentation` sont conservées et validées.
- Les fichiers `tests/http/` ne sont pas versionnés : s'ils envoient encore `"weight": "180g"`, la réponse est maintenant un 422. Le poids doit être un nombre.
- Adresse à l'inscription : elle est enregistrée comme adresse par défaut du compte. Aucune route ne permet encore de la modifier ou d'en ajouter (`P3-01`).
- Les 401 du pare-feu JWT (token absent, invalide ou expiré) sont produits par le bundle lexik au format `{"code": 401, "message": "..."}`, pas en `{"error": "..."}`. À harmoniser au plus tard avec le contrat OpenAPI (`P1-03`).
- Aucun bundle de logs n'est installé : les erreurs partent sur la sortie d'erreur du serveur PHP. Monolog et le suivi d'erreurs sont prévus en `P7-03`.
- En production, vider le cache (`cache:clear`) à chaque déploiement : un cache `prod` périmé garde l'ancien câblage des services et répond 500 en HTML.
- Dans un `WebTestCase`, une route protégée s'appelle avec le trait `App\Tests\Support\AuthenticatesRequests` (`authenticateAs()`), qui ne demande pas de base de données.
- Slug produit : il suit le nom. Renommer un produit change donc son slug, et l'ancienne URL ne redirige nulle part. À revoir avant l'ouverture de la boutique (`P2-07`) : slug figé, ou table de redirections.
- Slug produit : deux créations simultanées du même nom peuvent viser le même slug. L'index unique refuse la seconde, qui répond 500 au lieu d'obtenir un suffixe. Sans gravité tant que seul le back-office crée des produits.
- La migration `Version20261114170000` (colonne `slug`) est à jouer sur chaque base de dev : `php bin/console doctrine:migrations:migrate`. Elle calcule le slug des produits existants.
- Symfony 8.1 n'est pas LTS et sa fin de maintenance tombe en **janvier 2027** : monter en 8.2 dès sa sortie, prévue fin novembre 2026 (ADR 0005).
- Deux recipes Flex ont une mise à jour disponible : `doctrine/doctrine-bundle` et `symfony/framework-bundle` (celle de `symfony/security-bundle` est passée avec `P0-01`). Les appliquer avec `composer recipes:update` sur un arbre git propre, en relisant les diffs.
- `doctrine:schema:validate` échoue encore sur le mapping, à cause de B15 uniquement (`DoctrineProduct::price` : `DECIMAL` mappé sur un `float`). B2 est corrigé par `P0-03`, et la base est bien synchronisée avec le mapping. B15 se règle avec le passage des montants en centimes, à faire avant la phase 4.
- Tests d'intégration (`tests/Integration/`) : ils exigent la base de test, c'est-à-dire le service `database_test` lancé par `docker compose up -d` depuis `api/` (`127.0.0.1:54320`). Sans elle ils **échouent**, avec un message qui dit quoi lancer. `vendor/bin/phpunit tests/Unit` reste utilisable sans base.
- La base de test vit en mémoire : elle est vide après chaque redémarrage du conteneur, et le premier test d'intégration rejoue les migrations. Rien à faire à la main.
- **Le dépôt GitHub est public.** L'ancien `.env.test` contenait le mot de passe du compte `postgres` d'une base sur `192.168.1.28:5433`. Il est retiré du fichier mais **reste lisible par tous dans l'historique git** : le changer sur ce serveur, et partout où il serait réutilisé. Réécrire l'historique ne suffirait pas, le mot de passe ayant déjà été publié.
- Secrets : `.env` n'en contient aucun (`APP_SECRET` et `JWT_PASSPHRASE` y sont vides exprès). Les valeurs de dev et de test sont dans `.env.dev` et `.env.test`, versionnées et publiques. En production, les fournir par de vraies variables d'environnement ou par les secrets Symfony, avec une nouvelle paire de clés JWT : sans elles l'application échoue au lieu de tourner avec une valeur connue (`P7-01`).
- `.env.local` n'est **pas** lu en environnement de test : pour une surcharge locale des tests, utiliser `.env.test.local`.
- Le port de la base de **dev** côté hôte est toujours attribué au hasard par `compose.override.yaml` et change à chaque recréation du conteneur : le relire avec `docker compose port database 5432`. Seule la base de test a un port fixe.

## Journal

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
