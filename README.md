# Parfumée

Site e-commerce de bougies parfumées. Ce dépôt contient l'API et, à terme, les deux fronts qui la consomment.

| Dossier | Contenu | État |
|---|---|---|
| `api/` | API Symfony 8.1, PostgreSQL 16, architecture hexagonale | En stabilisation |
| `shop/` | Boutique publique | À créer |
| `admin/` | Back-office | À créer |
| `infra/` | Docker Compose de production, Caddy, sauvegardes | À créer |
| `docs/` | Audit, roadmap, décisions d'architecture, état d'avancement | À jour |

Pour savoir où en est le projet : [`docs/STATUS.md`](docs/STATUS.md).

## Installer l'API

### Prérequis

- **PHP 8.4 ou plus**, avec les extensions `ctype`, `iconv`, `intl`, `mbstring`, `pdo_pgsql`, `gd`, `fileinfo` et `sodium`
- **Composer 2**
- **Docker** avec Docker Compose v2

Vérifier d'un coup :

```bash
php -v && composer -V && docker compose version
```

### Installation

Toutes les commandes se lancent depuis `api/`.

```bash
git clone git@github.com:SoufiMehdi/parfumee.git
cd parfumee/api
```

**1. Démarrer les bases de données**

```bash
docker compose up -d
```

Deux PostgreSQL 16 démarrent : la base de développement, et une base de test en mémoire sur `127.0.0.1:54320`.

**2. Installer les dépendances**

```bash
composer install
```

**3. Indiquer à l'API où est la base de développement**

Docker attribue à la base de développement un port libre au hasard. Le lire :

```bash
docker compose port database 5432
# exemple de réponse : 0.0.0.0:32768
```

Créer le fichier `api/.env.local`, non versionné, avec ce port :

```dotenv
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:32768/app?serverVersion=16&charset=utf8"
```

> Ce port change quand le conteneur est recréé (`docker compose down` puis `up`). Si l'API ne joint plus la base, relire le port et corriger `.env.local`.

**4. Générer les clés JWT**

```bash
php bin/console lexik:jwt:generate-keypair
```

Les clés (`config/jwt/*.pem`) ne sont pas versionnées : cette commande est à lancer une fois par machine.

**5. Créer les tables**

```bash
php bin/console doctrine:migrations:migrate
```

**6. Charger les données de départ**

```bash
php bin/console doctrine:fixtures:load
```

Les fixtures créent trois catégories (Bougies Parfumées, Fondants, Vêtements) et aucun produit.

> **Attention :** cette commande **vide toute la base** avant de la remplir. Sur une base dont on veut garder les données (des comptes, par exemple), ajouter les catégories sans rien effacer : `php bin/console doctrine:fixtures:load --append`.

**7. Lancer l'API**

```bash
php -S 127.0.0.1:8000 -t public
```

Vérifier dans un autre terminal :

```bash
curl http://127.0.0.1:8000/api/v1/shop/products
# []
```

### Créer un compte administrateur

Le back-office est réservé aux comptes qui ont un rôle, et aucune route ne permet d'en donner un : cela passe par une commande.

```bash
php bin/console app:user:create-admin
```

Elle demande l'email, le prénom, le nom et le mot de passe (12 caractères minimum, saisie masquée). Variantes :

```bash
# Un éditeur : il gère le catalogue, sans accès aux comptes clients
php bin/console app:user:create-admin editeur@example.test --editor

# Donner le rôle à un compte déjà inscrit, sans toucher à son mot de passe
php bin/console app:user:create-admin client@example.test --promote
```

| Rôle | Droits |
|---|---|
| Client (`ROLE_USER`) | Son propre compte |
| Éditeur (`ROLE_EDITOR`) | Ceux du client, plus la gestion des produits (`/api/v1/admin/products`) |
| Administrateur (`ROLE_ADMIN`) | Ceux de l'éditeur, plus tout le reste du back-office, dont les comptes clients |

Se connecter ensuite ; la réponse contient le jeton :

```bash
curl -X POST http://127.0.0.1:8000/api/v1/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"<mot de passe>"}'
```

Le jeton s'envoie ensuite dans l'en-tête `Authorization: Bearer <jeton>`. Exemple, avec l'identifiant fixe de la catégorie « Bougies Parfumées » des fixtures :

```bash
curl -X POST http://127.0.0.1:8000/api/v1/admin/products \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <jeton>' \
  -d '{"name":"Bougie Ambre","price":24.9,"category_id":"018f5370-1234-7000-8000-000000000001","attributes":{"weight":180,"fragrance":"ambre"}}'
```

Un corps JSON doit toujours être envoyé avec `Content-Type: application/json`, sinon l'API répond 400.

## Tester avec Bruno

Le dossier `api/bruno/` est une collection [Bruno](https://www.usebruno.com/) : 22 requêtes qui couvrent toutes les routes, avec le jeton géré automatiquement.

1. Dans Bruno : **Open Collection**, choisir le dossier `api/bruno`.
2. En haut à droite, sélectionner l'environnement **local** (API sur `http://127.0.0.1:8000`).
3. Lancer `1 - Comptes / 01 Inscription`, puis promouvoir ce compte administrateur, une seule fois :

   ```bash
   php bin/console app:user:create-admin admin@example.test --promote
   ```

4. Lancer `02 Connexion` : le jeton est enregistré, les autres requêtes l'envoient toutes seules.

Les requêtes du catalogue utilisent la catégorie des fixtures (étape 6 de l'installation). Elles se lancent dans l'ordre : `02 Créer un produit` enregistre l'identifiant dont les suivantes ont besoin.

Pour tout lancer d'un coup, clic droit sur la collection puis **Run**, ou en ligne de commande :

```bash
npm install -g @usebruno/cli      # une seule fois
cd api/bruno && bru run --env local -r
```

Chaque exécution crée un produit et une image dans la base de développement.

## Lancer le back-office

Le back-office (`admin/`) est une application React-admin qui consomme l'API. Il faut Node 22.12 ou plus, et l'API lancée comme à l'étape 7 (`http://127.0.0.1:8000`, ou l'adresse mise dans `admin/.env.local`).

```bash
cd admin
npm install
npm run dev
```

Ouvrir http://localhost:5173, puis se connecter avec un compte éditeur ou administrateur (voir « Créer un compte administrateur »). Un compte client est refusé : « Ce compte n'a pas accès au back-office ».

- **Écrans** : la liste des produits (éditeurs et administrateurs) et celle des comptes (administrateurs seulement), avec tri, filtres et pagination. Pas encore de formulaires (`P2-09`, `P3-06`) : pour avoir des produits en développement, les créer avec la collection Bruno (`1 - Comptes / 02 Connexion`, puis `2 - Catalogue / 02 Créer un produit`). Il faut les catégories des fixtures (étape 6).
- **Port imposé** : 5173 est l'origine que l'API autorise (CORS). S'il est pris, `npm run dev` s'arrête plutôt que de changer de port.
- **Session** : elle dure tant qu'elle est renouvelée (le refresh token vaut 30 jours) ; « Déconnexion » la termine aussi côté serveur.
- **Production** : `VITE_API_URL=https://api.example.fr npm run build`, puis servir `admin/dist/` en fichiers statiques (`P7-01`).
- **Vérifier** : `npm run check` (lint, types, tests), ce que lance la CI.

## Routes actuelles

Deux espaces, un par front. Ils partagent les mêmes cas d'usage ; seuls les contrôleurs et les droits diffèrent.

**Sessions**

Une connexion renvoie deux valeurs : un `token` (JWT, valable 15 minutes, à envoyer dans `Authorization: Bearer`) et un `refresh_token` (valable 30 jours).

| Méthode | Route | Accès |
|---|---|---|
| `POST` | `/api/v1/token/refresh` | Public. Corps : `{"refresh_token": "..."}`. Renvoie un nouveau `token` et un nouveau `refresh_token` |
| `POST` | `/api/v1/token/revoke` | Public. Corps : `{"refresh_token": "..."}`. Déconnexion : répond `204`, et le refresh token ne renouvelle plus la session |

Un refresh token ne sert qu'une fois : chaque renouvellement en donne un nouveau, et l'ancien est refusé. Après une déconnexion, le `token` déjà délivré reste valable jusqu'à son expiration : le front l'oublie.

**Boutique — `/api/v1/shop`**

| Méthode | Route | Accès |
|---|---|---|
| `POST` | `/api/v1/shop/register` | Public |
| `POST` | `/api/v1/shop/login` | Public |
| `GET` | `/api/v1/shop/products` | Public. Une page de produits (voir « Format des listes ») |
| `GET` | `/api/v1/shop/products/{id}` | Public |
| `GET` `PUT` `DELETE` | `/api/v1/shop/me` | Client connecté : son propre compte |

**Back-office — `/api/v1/admin`**

| Méthode | Route | Accès |
|---|---|---|
| `POST` | `/api/v1/admin/login` | Public |
| `GET` | `/api/v1/admin/me` | Éditeur : son identité et ses rôles |
| `GET` | `/api/v1/admin/products` | Éditeur. Une page de produits, mêmes paramètres que la boutique |
| `GET` | `/api/v1/admin/products/{id}` | Éditeur |
| `POST` | `/api/v1/admin/products` | Éditeur |
| `PUT` | `/api/v1/admin/products/{id}` | Éditeur |
| `POST` | `/api/v1/admin/products/{id}/pictures` | Éditeur |
| `GET` | `/api/v1/admin/users` | Administrateur. Une page de comptes |
| `GET` `PUT` `DELETE` | `/api/v1/admin/users/{id}` | Administrateur |

« Éditeur » inclut les administrateurs. Tout le reste de `/api/v1/admin` est réservé aux administrateurs, et tout le reste de `/api/v1/shop` aux clients connectés.

### Format des listes

Une liste ne renvoie jamais un tableau nu, mais une page :

```json
{
  "items": [ ... ],
  "pagination": { "page": 2, "per_page": 20, "total": 45, "total_pages": 3 }
}
```

Le total est aussi envoyé dans l'en-tête `X-Total-Count`, la convention de React-admin. Une page après la dernière n'est pas une erreur : elle est vide, avec le bon total.

Paramètres, tous facultatifs :

| Paramètre | Valeurs | Par défaut |
|---|---|---|
| `page` | de 1 à 1 000 000 | `1` |
| `per_page` | de 1 à 100 | `20` |
| `sort` | produits : `name`, `price`, `created_at` ; comptes : `email`, `first_name`, `last_name`, `created_at` | `name` pour les produits, `email` pour les comptes |
| `order` | `asc`, `desc` | `asc` |

Filtres des produits : `category_id`, `min_price` et `max_price` (inclus), `fragrance` et `size` (valeur exacte). Filtre des comptes : `q`, un morceau de l'email, du prénom ou du nom, sans tenir compte des majuscules.

Les noms se trient dans l'ordre alphabétique français (« ambre » avant « Bougie », « Été » avec les « e »). À valeur égale, les éléments sont départagés par leur identifiant : un élément ne peut pas apparaître sur deux pages.

Exemple : `GET /api/v1/shop/products?fragrance=ambre&sort=price&order=desc&per_page=10`

### Dates

Chaque produit et chaque compte porte deux dates, en UTC et au format RFC 3339 (`2026-11-21T19:52:47+00:00`) :

- `created_at` : sa création ;
- `updated_at` : sa dernière modification. Ajouter une image à un produit ne la change pas, ni modifier une adresse pour un compte : ce sont d'autres enregistrements.

L'API les pose elle-même à l'enregistrement, à la seconde près ; un client ne peut pas les envoyer. Les plus récents d'abord : `sort=created_at&order=desc`.

### Limites de connexion et d'inscription

Contre la recherche de mots de passe et la création de comptes en masse, l'API limite les essais par adresse IP :

| Route | Limite | Remise à zéro |
|---|---|---|
| Connexion (`/api/v1/shop/login` et `/api/v1/admin/login`, comptées ensemble) | 5 essais sur un même email depuis une même adresse, en 15 minutes | une connexion réussie remet cet email à zéro |
| | 25 essais depuis une même adresse, tous emails confondus, en 15 minutes | une connexion réussie ne les efface pas |
| Inscription (`/api/v1/shop/register`) | 10 essais depuis une même adresse en une heure, réussis ou non | aucune |

Au-delà, l'API répond `429` : `{"error": "..."}`, avec l'en-tête `Retry-After`, le nombre de secondes à attendre. Une adresse bloquée est refusée même avec le bon mot de passe. Les limites se règlent dans `api/config/packages/rate_limiter.yaml`.

## Appeler l'API depuis un navigateur (CORS)

La boutique et le back-office tournent sur leur propre origine et appellent l'API depuis le navigateur. L'API n'autorise que ces deux origines, une variable chacune :

| Variable | Développement (`api/.env.dev`) | Production |
|---|---|---|
| `CORS_SHOP_ORIGIN` | `http://localhost:3000` (Next.js ou Nuxt) | à définir, par exemple `https://www.example.fr` |
| `CORS_ADMIN_ORIGIN` | `http://localhost:5173` (Vite) | à définir, par exemple `https://admin.example.fr` |

- **Comparaison exacte** : schéma, hôte et port, sans barre finale. `http://localhost:5173/` ou `http://127.0.0.1:5173` ne correspondent pas à `http://localhost:5173`.
- **Autre port en développement** : surcharger la variable dans `api/.env.dev.local`, non versionné.
- **Valeur vide** : aucun front n'est autorisé. C'est le cas par défaut dans `api/.env`, donc en production tant que les variables ne sont pas définies.
- **En-têtes exposés** : le front peut lire `X-Total-Count`, le total d'une liste, et `Retry-After`, l'attente après trop d'essais.
- **Limite** : le CORS n'empêche que la lecture des réponses par une page web d'une autre origine. Un script hors navigateur appelle l'API librement : ce sont le jeton et les rôles qui la protègent.

## Contrat de l'API (OpenAPI)

L'API se décrit elle-même : routes, corps attendus, réponses et erreurs.

| Adresse | Contenu |
|---|---|
| `http://127.0.0.1:8000/api/v1/doc` | Swagger UI : la documentation navigable, où l'on peut essayer chaque route |
| `http://127.0.0.1:8000/api/v1/doc.json` | Le contrat OpenAPI au format JSON |
| `api/openapi.json` | Le même contrat, versionné dans le dépôt |

Dans Swagger UI, le bouton **Authorize** attend le `token` renvoyé par une connexion.

Les fronts généreront leur client TypeScript à partir de ce contrat. Il est produit à partir du code (contrôleurs, DTO d'entrée, vues de sortie) : après un changement de l'API, le régénérer puis le commiter.

```bash
composer openapi:dump
```

Un test échoue tant que `api/openapi.json` n'est pas à jour.

## Vérifier son travail

Depuis `api/`, bases de données démarrées :

```bash
composer check     # tout : style, PHPStan, conteneur de services et tests
```

C'est ce que lance la CI sur chaque pull request. Les commandes séparées :

| Commande | Rôle |
|---|---|
| `composer lint` | Style (sans modifier), PHPStan niveau 6, conteneur de services |
| `composer fix-cs` | Corrige le style |
| `composer test` | PHPUnit |
| `vendor/bin/phpunit tests/Unit` | Les tests unitaires seuls, sans base de données |

Les tests d'intégration utilisent la base de test du port 54320. Sans elle, ils échouent avec un message qui indique quoi lancer.

## Fichiers d'environnement

| Fichier | Versionné | Contenu |
|---|---|---|
| `api/.env` | Oui | Valeurs par défaut, **sans aucun secret** |
| `api/.env.dev` | Oui | Valeurs jetables pour le développement |
| `api/.env.test` | Oui | Valeurs jetables pour les tests |
| `api/.env.local` | Non | Réglages propres à la machine (étape 3) |

Ne jamais mettre de vrai secret dans un fichier versionné. `.env.local` n'est pas lu pendant les tests : pour eux, utiliser `.env.test.local`.

## Problèmes fréquents

| Symptôme | Cause et solution |
|---|---|
| `Connection refused` sur la base de développement | Le port a changé : refaire l'étape 3. |
| Les tests d'intégration échouent tous | La base de test est arrêtée : `docker compose up -d`. |
| Erreur à la connexion ou tests de sécurité en échec, mention de `private.pem` | Les clés JWT manquent : refaire l'étape 4. |
| Une migration échoue sur une colonne `NOT NULL` | La base contient d'anciennes données : repartir d'une base vide. |
| `Category with id "018f5370-…" not found` en créant un produit avec Bruno | Les catégories des fixtures manquent : `php bin/console doctrine:fixtures:load --append` les ajoute sans rien effacer. |
| Erreur 500 « column … created_at does not exist » | La base de développement n'a pas la dernière migration : `php bin/console doctrine:migrations:migrate`. |
| Le front reçoit une erreur CORS (« blocked by CORS policy ») | Son origine ne correspond pas exactement à `CORS_SHOP_ORIGIN` ou `CORS_ADMIN_ORIGIN` : vérifier le port, `localhost` contre `127.0.0.1`, et l'absence de barre finale. |
| Upload d'image refusé au-delà de 2 Mo | Limite `upload_max_filesize` de PHP, à relever dans son `php.ini` (l'API accepte jusqu'à 5 Mo). |
| `npm run dev` du back-office : « Port 5173 is already in use » | Un autre programme occupe le port : l'arrêter. Changer de port ferait refuser les appels par l'API (CORS). |
| Le back-office affiche « Ce compte n'a pas accès au back-office » | Le compte n'est ni éditeur ni administrateur : `php bin/console app:user:create-admin <email> --promote`. |
| `429 Too Many Requests` à la connexion ou à l'inscription, en développement | Les limites sont atteintes, par exemple après plusieurs exécutions de Bruno : `php bin/console cache:pool:clear cache.rate_limiter` remet les compteurs à zéro. |

## Contribuer

- Une branche par tâche, depuis `main` : `feat/<id>-<sujet>` ou `fix/<id>-<sujet>`, puis une pull request.
- Commits en anglais, au format Conventional Commits, avec l'identifiant de tâche : `fix(catalog): persist product slug [P0-06]`.
- Une tâche est finie quand `composer check` passe et que `docs/STATUS.md` est à jour.

## Documentation

- [`docs/STATUS.md`](docs/STATUS.md) : état d'avancement et pièges connus
- [`docs/compte-rendus/07-roadmap.md`](docs/compte-rendus/07-roadmap.md) : phases et tâches
- [`docs/adr/`](docs/adr/README.md) : décisions d'architecture
- [`api/CLAUDE.md`](api/CLAUDE.md) : règles d'architecture et conventions de l'API
