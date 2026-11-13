# État d'avancement

> À mettre à jour **à la fin de chaque session** (skill `fin-de-session`). Ajouter les entrées les plus récentes en haut du journal.

## Où on en est

- **Phase en cours :** Phase 0 — Stabiliser l'existant.
- **Fait :** `P0-01` (contrôle d'accès), mergée dans `main` (PR #6).
- **Fait :** `P0-02` (validation des uploads), mergée dans `main` (PR #7).
- **En relecture :** `P0-03` (repository des images), sur la branche `fix/P0-03-picture-repository`. À merger après la review de la PR.
- **Prochaines tâches conseillées :** `P0-04` (gestion d'erreurs centralisée), puis `P0-05` qui en dépend.
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
- Les tests fonctionnels signent de vrais JWT : sans `config/jwt/*.pem` (`php bin/console lexik:jwt:generate-keypair`), ils échouent. À prévoir dans la CI (`P0-11`).
- Upload d'images : la limite applicative est de 5 Mo, mais PHP refuse avant elle tout fichier au-delà de `upload_max_filesize` (2 Mo par défaut) et ignore le corps entier au-delà de `post_max_size` (8 Mo par défaut). À régler dans l'image Docker (`P7-01`) : `upload_max_filesize = 5M` au minimum.
- `#[MapUploadedFile]`, `#[MapRequestPayload]` et `#[MapQueryString]` **ignorent silencieusement leurs contraintes** si `symfony/validator` est absent. Il est installé depuis `P0-02` : ne pas le retirer.
- Dans un `WebTestCase`, une route protégée s'appelle avec le trait `App\Tests\Support\AuthenticatesRequests` (`authenticateAs()`), qui ne demande pas de base de données.
- Le slug produit n'est pas persisté (B4) : tous les produits renvoient `slug-placeholder`.
- Symfony 8.1 n'est pas LTS et sa fin de maintenance tombe en **janvier 2027** : monter en 8.2 dès sa sortie, prévue fin novembre 2026 (ADR 0005).
- Deux recipes Flex ont une mise à jour disponible : `doctrine/doctrine-bundle` et `symfony/framework-bundle` (celle de `symfony/security-bundle` est passée avec `P0-01`). Les appliquer avec `composer recipes:update` sur un arbre git propre, en relisant les diffs.
- `doctrine:schema:validate` échoue encore sur le mapping, à cause de B15 uniquement (`DoctrineProduct::price` : `DECIMAL` mappé sur un `float`). B2 est corrigé par `P0-03`, et la base est bien synchronisée avec le mapping. B15 se règle avec le passage des montants en centimes, à faire avant la phase 4.
- Tests d'intégration (`tests/Integration/`) : ils ont besoin d'une base de test migrée (`doctrine:database:create --env=test --if-not-exists`, puis `doctrine:migrations:migrate --env=test -n`). Sans serveur de base joignable, ils sont **ignorés** (« Skipped »), pas en échec : vérifier ce compteur avant de conclure que tout passe. À rendre obligatoire dans `P0-10`.
- `.env.test` pointe vers une base sur `192.168.1.28`. Sur une autre machine, surcharger `DATABASE_URL` dans `.env.test.local` (non versionné), sinon chaque test d'intégration attend le délai de connexion (la suite passe de 2 s à 18 s).
- Avec le `compose.yaml` actuel, le port PostgreSQL côté hôte est attribué au hasard et change à chaque recréation du conteneur : le relire avec `docker compose port database 5432`.
- Lire un produit sans attribut `weight` déclenche un warning PHP (B5, `P0-07`), qui fait échouer PHPUnit. Les tests d'intégration donnent donc un poids à leurs produits.

## Journal

### 2026-11-13 — P0-03 : repository des images (branche `fix/P0-03-picture-repository`, en relecture)
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
