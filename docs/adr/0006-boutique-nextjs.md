# 0006 — Boutique en Next.js

- **Statut :** Acceptée
- **Date :** 2026-11-22

## Contexte
- La boutique doit être bien référencée : les pages du catalogue doivent arriver déjà rendues aux moteurs de recherche (SSR), avec leurs balises et leurs données structurées (P8-05).
- Elle consomme l'API comme le back-office, avec un client TypeScript généré depuis le contrat OpenAPI, jamais écrit à la main.
- Le back-office est en React (ADR 0003).
- Elle tourne dans la stack Docker Compose du VPS, derrière Caddy (ADR 0004).

## Décision
Front séparé dans `shop/`, construit avec **Next.js** (React, TypeScript) :
- rendu côté serveur des pages du catalogue ;
- client de l'API typé, généré depuis `api/openapi.json` (`openapi-typescript`) ;
- auto-hébergé : un conteneur Node (sortie `standalone`) derrière Caddy, sans dépendre de Vercel.

Décision prise le 2026-11-22.

## Mise en œuvre
- `P1-11` : Next.js 16 (App Router), client `openapi-fetch` typé par `openapi-typescript`. Les données fraîches sont lues à chaque requête, dans un `<Suspense>` après `connection()` : la compilation n'appelle jamais l'API. Les règles sont dans `shop/CLAUDE.md`.
- `P7-01` : l'image Docker, avec la sortie `standalone`.

## Conséquences
- Un seul écosystème pour les deux fronts : React, TypeScript, les mêmes outils de test et de lint.
- Contrairement au back-office (fichiers statiques), la boutique demande un serveur Node en production, donc un peu plus de mémoire sur le VPS.
- Les appels faits par le serveur Next.js n'envoient pas d'en-tête `Origin` : ils ne passent pas par le CORS. Seuls les appels faits depuis le navigateur (panier, compte) le font, depuis `CORS_SHOP_ORIGIN` (`http://localhost:3000` en dev).

## Alternatives écartées
- **Nuxt (Vue) :** aussi adapté au SSR, mais il ferait cohabiter Vue pour la boutique et React pour le back-office : deux écosystèmes à maintenir.
