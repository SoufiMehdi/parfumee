# État d'avancement

> À mettre à jour **à la fin de chaque session** (skill `fin-de-session`). Ajouter les entrées les plus récentes en haut du journal.

## Où on en est

- **Phase en cours :** Phase 0 — Stabiliser l'existant (pas encore démarrée).
- **Prochaine tâche conseillée :** `P0-01`, activer le contrôle d'accès, car toutes les routes sont actuellement publiques.
- **Branche de travail :** `main`, monorepo en place.

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

## Journal

### 2026-10-21 — Audit, roadmap et mise en place du monorepo
- Audit complet de l'API : comptes rendus 00 à 06 dans `docs/compte-rendus/`.
- Branche `feat/data-product` vérifiée : déjà mergée dans `main` (PR #2), rien à merger.
- Roadmap en 10 phases et 80 tâches, avec estimations humain / avec IA (`07-roadmap.md`), importée dans ClickUp.
- Hébergement choisi : rester chez IONOS (domaine actuel), sur un VPS avec Docker Compose et Caddy. WordPress reste en ligne jusqu'à la bascule. Aucune donnée WordPress à récupérer.
- Passage en monorepo : `api/`, `shop/`, `admin/`, `infra/`, `docs/`. Le dépôt GitHub `parfumee-api` est renommé `parfumee`.
- Création de `CLAUDE.md`, `api/CLAUDE.md`, des ADR, des agents et des skills Claude.
