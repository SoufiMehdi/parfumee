# Parfumée — monorepo

Site e-commerce de **bougies parfumées**. Un seul dépôt contient une API et les deux fronts qui la consomment.

| Dossier | Contenu | État |
|---|---|---|
| `api/` | API Symfony 8.1 / PHP 8.4+ / PostgreSQL 16, architecture hexagonale. Voir `api/CLAUDE.md` | Existant, en stabilisation |
| `shop/` | Front boutique publique en Next.js (SSR pour le SEO), voir ADR 0006 et `shop/CLAUDE.md` | Initialisé : accueil avec les dernières bougies |
| `admin/` | Back-office en React-admin (Vite, TypeScript), voir ADR 0003 et `admin/CLAUDE.md` | Connexion, session, listes des produits et des comptes |
| `infra/` | Docker Compose, Caddy, scripts de déploiement et de sauvegarde | À créer |
| `docs/` | Comptes rendus d'audit, roadmap, ADR, état d'avancement | À jour |

## À lire en début de session

1. **`docs/STATUS.md`** : où on en est, ce qui est en cours, les pièges connus. **Toujours le lire avant de coder.**
2. `docs/compte-rendus/07-roadmap.md` : les phases et les tâches (IDs `P0-01`…). Le suivi des tâches se fait dans le kanban ClickUp, avec les mêmes IDs.
3. `docs/adr/` : les décisions déjà prises. Ne pas les remettre en cause sans le signaler.
4. `docs/compte-rendus/03-bugs-et-anomalies.md` et `04-securite.md` : les bugs connus (références B1…B18, S1…S11).

## Architecture cible

- **Une API, deux espaces de routes :**
  - `/api/v1/shop/*` est consommé par la boutique (public + `ROLE_USER`) ;
  - `/api/v1/admin/*` est consommé par le back-office (`ROLE_ADMIN`).
- **Les deux espaces réutilisent les mêmes use cases.** Seuls les contrôleurs, la sécurité et les presenters diffèrent.
- **Le contrat d'API est décrit en OpenAPI.** Les fronts génèrent leur client TypeScript à partir de lui ; on ne l'écrit pas à la main.
- **Déploiement :** un VPS IONOS (datacenter Allemagne) avec Docker Compose et Caddy (HTTPS automatique). La même stack Compose tourne en dev et en prod (ADR 0004).

## Règles de travail

- **Langue :** code, noms de classes et commits en anglais ; documentation et échanges en français.
- **Commits :** Conventional Commits, avec l'ID de tâche quand il y en a un. Exemple : `fix(catalog): persist product slug [P0-06]`.
- **Branches :** `feat/<id>-<sujet>` ou `fix/<id>-<sujet>` depuis `main`, avec une PR par tâche ou groupe de tâches.
- **Une tâche n'est pas finie tant que** les tests passent et que `docs/STATUS.md` n'est pas mis à jour (skill `fin-de-session`).
- **Secrets :** jamais dans git. `.env` ne contient aucun secret ; `.env.dev` et `.env.test` ne contiennent que des valeurs de dev jetables ; les vraies valeurs vont dans `.env.local`, dans de vraies variables d'environnement ou dans les secrets Symfony.
- **Suppressions :** ne jamais supprimer de données, de migrations déjà jouées ou de fichiers utilisateur sans demander.

## Agents et skills du projet

| Nom | Type | Quand l'utiliser |
|---|---|---|
| `hexa-dev` | agent | Implémenter une fonctionnalité dans `api/` en respectant l'architecture |
| `reviewer` | agent | Relire un diff avant commit ou PR (grille de l'audit) |
| `test-writer` | agent | Écrire ou compléter les tests unitaires et d'intégration |
| `devops` | agent | Docker, Compose, Caddy, CI, déploiement IONOS, sauvegardes |
| `nouvelle-fonctionnalite` | skill | La procédure pas à pas pour ajouter une fonctionnalité à l'API |
| `fin-de-session` | skill | Mettre à jour `docs/STATUS.md` et préparer le commit en fin de session |

## Commandes utiles

```bash
# API (depuis api/)
docker compose up -d                       # PostgreSQL de dev + PostgreSQL de test (127.0.0.1:54320)
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console lexik:jwt:generate-keypair # une seule fois par machine
composer check                             # tout : style + PHPStan + conteneur + tests (ce que lance la CI)
composer lint                              # style (dry-run), PHPStan niveau 6, lint du conteneur
composer test                              # PHPUnit ; les tests d'intégration exigent la base de test
composer fix-cs                            # corrige le style (php-cs-fixer)

# Back-office (depuis admin/)
npm install
npm run dev                                # http://localhost:5173, l'API sur VITE_API_URL (admin/.env)
npm run check                              # lint (oxlint) + types (tsc) + tests (Vitest), comme la CI
npm run api:types                          # régénère src/api/schema.ts après un changement du contrat

# Boutique (depuis shop/)
npm install
npm run dev                                # http://localhost:3000, l'API sur API_URL (shop/.env.development)
npm run check                              # lint (ESLint) + types (tsc) + tests (Vitest), comme la CI
npm run api:types                          # régénère src/api/schema.ts après un changement du contrat
```
