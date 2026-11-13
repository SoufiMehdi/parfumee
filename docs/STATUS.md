# État d'avancement

> À mettre à jour **à la fin de chaque session** (skill `fin-de-session`). Ajouter les entrées les plus récentes en haut du journal.

## Où on en est

- **Phase en cours :** Phase 0 — Stabiliser l'existant (pas encore démarrée).
- **Prochaine tâche conseillée :** `P0-01`, activer le contrôle d'accès, car toutes les routes sont actuellement publiques.
- **Branche de travail :** `chore/upgrade-symfony-8.1` (montée en Symfony 8.1, PR à ouvrir), sinon `main`.

## Décisions en attente

| Sujet | Options | Impact |
|---|---|---|
| Framework du front boutique | Next.js (React) / Nuxt (Vue) | P1-11 et tout `shop/` |
| Back-office | React-admin (proposé, ADR 0003) / EasyAdmin | P1-09, P1-10 et tout `admin/` |
| Paiement | Stripe / Mollie | P5-04 |
| Régime de TVA | Franchise en base (micro-entreprise) / TVA collectée | P4-03, P5-07 |
| Taille du VPS IONOS | S+ pendant le dev, M+ au lancement (proposé) | Phase 7 |

## Pièges connus en cours

- Toutes les routes de l'API sont publiques (S1) : ne rien exposer sur Internet avant `P0-01`.
- `DoctrinePictureRepository` est cassé (B1) : les lectures d'images plantent.
- Le slug produit n'est pas persisté (B4) : tous les produits renvoient `slug-placeholder`.
- Symfony 8.1 n'est pas LTS et sa fin de maintenance tombe en **janvier 2027** : monter en 8.2 dès sa sortie, prévue fin novembre 2026 (ADR 0005).
- Trois recipes Flex ont une mise à jour disponible : `doctrine/doctrine-bundle`, `symfony/framework-bundle` et `symfony/security-bundle`. Les appliquer avec `composer recipes:update` sur un arbre git propre, en relisant les diffs (`security.yaml` touche P0-01).
- `doctrine:schema:validate` échoue sur le mapping, mais ce n'est pas lié à Symfony 8 : ce sont les bugs déjà connus B2 (`DoctrinePicture.php:29`, `inversedBy: 'images'`, corrigé dans P0-03) et B15 (`price` en `float`).

## Journal

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
