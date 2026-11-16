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

> **Attention :** cette commande **vide toute la base** avant de la remplir. Ne pas la relancer sur une base dont on veut garder les données.

**7. Lancer l'API**

```bash
php -S 127.0.0.1:8000 -t public
```

Vérifier dans un autre terminal :

```bash
curl http://127.0.0.1:8000/api/shop/products
# []
```

### Créer un compte administrateur

Presque toutes les routes d'écriture sont réservées aux administrateurs, et aucune commande ne crée encore de compte admin. En attendant, créer un compte puis le promouvoir en base.

```bash
# 1. Créer un compte (mot de passe : 8 caractères minimum)
curl -X POST http://127.0.0.1:8000/api/shop/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"change-me-123","first_name":"Ada","last_name":"Admin"}'

# 2. Le promouvoir administrateur
php bin/console dbal:run-sql "UPDATE users SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'admin@example.test'"

# 3. Se connecter : la réponse contient le jeton
curl -X POST http://127.0.0.1:8000/api/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"change-me-123"}'
```

Le jeton s'envoie ensuite dans l'en-tête `Authorization: Bearer <jeton>`. Exemple, avec l'identifiant fixe de la catégorie « Bougies Parfumées » des fixtures :

```bash
curl -X POST http://127.0.0.1:8000/api/admin/products \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <jeton>' \
  -d '{"name":"Bougie Ambre","price":24.9,"category_id":"018f5370-1234-7000-8000-000000000001","attributes":{"weight":180,"fragrance":"ambre"}}'
```

Un corps JSON doit toujours être envoyé avec `Content-Type: application/json`, sinon l'API répond 400.

## Tester avec Bruno

Le dossier `api/bruno/` est une collection [Bruno](https://www.usebruno.com/) : 18 requêtes qui couvrent toutes les routes, avec le jeton géré automatiquement.

1. Dans Bruno : **Open Collection**, choisir le dossier `api/bruno`.
2. En haut à droite, sélectionner l'environnement **local** (API sur `http://127.0.0.1:8000`).
3. Lancer `1 - Comptes / 01 Inscription`, puis promouvoir ce compte administrateur, une seule fois :

   ```bash
   php bin/console dbal:run-sql "UPDATE users SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'admin@example.test'"
   ```

4. Lancer `02 Connexion` : le jeton est enregistré, les autres requêtes l'envoient toutes seules.

Les requêtes du catalogue utilisent la catégorie des fixtures (étape 6 de l'installation). Elles se lancent dans l'ordre : `02 Créer un produit` enregistre l'identifiant dont les suivantes ont besoin.

Pour tout lancer d'un coup, clic droit sur la collection puis **Run**, ou en ligne de commande :

```bash
npm install -g @usebruno/cli      # une seule fois
cd api/bruno && bru run --env local -r
```

Chaque exécution crée un produit et une image dans la base de développement.

## Routes actuelles

Deux espaces, un par front. Ils partagent les mêmes cas d'usage ; seuls les contrôleurs et les droits diffèrent.

**Boutique — `/api/shop`**

| Méthode | Route | Accès |
|---|---|---|
| `POST` | `/api/shop/register` | Public |
| `POST` | `/api/shop/login` | Public |
| `GET` | `/api/shop/products` | Public. Filtres optionnels : `category_id`, `min_price`, `max_price`, `scent`, `size` |
| `GET` | `/api/shop/products/{id}` | Public |
| `GET` `PUT` `DELETE` | `/api/shop/users/{id}` | Le titulaire du compte |

**Back-office — `/api/admin`**

| Méthode | Route | Accès |
|---|---|---|
| `POST` | `/api/admin/login` | Public |
| `GET` | `/api/admin/products` | Administrateur. Mêmes filtres que la boutique |
| `GET` | `/api/admin/products/{id}` | Administrateur |
| `POST` | `/api/admin/products` | Administrateur |
| `PUT` | `/api/admin/products/{id}` | Administrateur |
| `POST` | `/api/admin/products/{id}/pictures` | Administrateur |
| `GET` | `/api/admin/users` | Administrateur |
| `GET` `PUT` `DELETE` | `/api/admin/users/{id}` | Administrateur |

Tout le reste de `/api/admin` est réservé aux administrateurs, et tout le reste de `/api/shop` aux clients connectés.

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
| Upload d'image refusé au-delà de 2 Mo | Limite `upload_max_filesize` de PHP, à relever dans son `php.ini` (l'API accepte jusqu'à 5 Mo). |

## Contribuer

- Une branche par tâche, depuis `main` : `feat/<id>-<sujet>` ou `fix/<id>-<sujet>`, puis une pull request.
- Commits en anglais, au format Conventional Commits, avec l'identifiant de tâche : `fix(catalog): persist product slug [P0-06]`.
- Une tâche est finie quand `composer check` passe et que `docs/STATUS.md` est à jour.

## Documentation

- [`docs/STATUS.md`](docs/STATUS.md) : état d'avancement et pièges connus
- [`docs/compte-rendus/07-roadmap.md`](docs/compte-rendus/07-roadmap.md) : phases et tâches
- [`docs/adr/`](docs/adr/README.md) : décisions d'architecture
- [`api/CLAUDE.md`](api/CLAUDE.md) : règles d'architecture et conventions de l'API
