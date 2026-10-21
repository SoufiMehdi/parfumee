# 0003 — Back-office en React-admin consommant l'API

- **Statut :** Proposée (à confirmer avant P1-09)
- **Date :** 2026-10-21

## Contexte
Le back-office doit permettre de gérer le catalogue, les commandes, les clients et les contenus. L'objectif est une API unique qui alimente deux fronts, en respectant les règles métier du domaine (ADR 0002).

## Décision
Front séparé dans `admin/`, construit avec **React-admin** (MIT, Vite), qui consomme `/api/admin/*` :
- un `authProvider` basé sur le JWT et le refresh token ;
- un `dataProvider` adapté au format de liste standard de l'API (pagination, tri, filtres, total).

## Conséquences
- Toutes les écritures passent par les use cases, donc les règles métier s'appliquent aussi aux admins.
- L'API doit exposer un format de liste standard (P1-04).
- Un projet front de plus à maintenir, mais il ne nécessite pas de serveur : c'est un build statique servi par Caddy.
- Estimation : environ 10 jours humains pour un back-office complet, contre 20 à 25 en partant de zéro.

## Alternatives écartées
- **EasyAdmin :** le plus rapide (4 à 5 jours), mais il travaille sur les entités Doctrine et contourne le domaine. C'est un plan B pour une saisie rapide des premiers produits.
- **API Platform Admin :** demande de refondre l'API en ressources API Platform.
- **Sylius :** voir ADR 0002.
