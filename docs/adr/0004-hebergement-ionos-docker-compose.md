# 0004 — Hébergement sur un VPS IONOS avec Docker Compose et Caddy

- **Statut :** Acceptée
- **Date :** 2026-10-21

## Contexte
- Budget de 10 € par mois maximum au lancement, avec un trafic faible attendu.
- Le domaine et un WordPress sont déjà chez IONOS, et le souhait est de rester chez eux.
- La configuration doit être **portable** : la même stack doit tourner sur un nouveau VPS ou sur n'importe quel PC de dev.

## Décision
- **Serveur :** VPS IONOS, datacenter en Allemagne (pas de datacenter IONOS en France). VPS S+ (2 Go) pendant le dev, puis VPS M+ (4 Go) au lancement, en remplacement de l'hébergement WordPress.
- **Stack :** Docker Compose, avec
  - un `compose.yaml` commun ;
  - un `compose.override.yaml` pour le dev ;
  - un `compose.prod.yaml` pour la prod.
- **Services :**
  - Caddy : reverse proxy et HTTPS automatique ;
  - API en FrankenPHP ;
  - boutique en SSR ;
  - back-office en fichiers statiques ;
  - PostgreSQL 16, non exposé sur Internet ;
  - Garage : stockage S3 des images.
- **Images Docker :** construites par GitHub Actions et publiées sur GHCR. Le serveur ne fait que `docker compose pull && up -d`.
- **Sauvegardes :** `pg_dump` et images chaque nuit, envoyés avec restic (chiffré) vers un stockage externe au VPS. Une restauration est testée chaque mois.
- **DNS :** le domaine reste chez IONOS. `api.`, `admin.` et `preprod.` pointent vers le VPS pendant le dev ; `@` et `www` restent sur WordPress jusqu'à la bascule, qui se fait avec des redirections 301. On ne touche pas aux enregistrements MX.

## Conséquences
- Coût estimé : environ 6 € TTC par mois pendant le dev. Au lancement, environ 14 € TTC (VPS M+ au prix affiché en octobre 2026), en partie compensés par la résiliation de l'hébergement WordPress. **Vérifier le prix après promo dans le panier IONOS.**
- Il faut ouvrir les ports 80 et 443 dans les « Stratégies de pare-feu » du panneau IONOS, en plus du pare-feu du serveur.
- Les images passent par Flysystem : on peut basculer vers un stockage objet externe sans toucher au code.

## Alternatives écartées
- **OVHcloud, Hetzner, Netcup :** moins chers ou mieux dotés selon les cas, mais l'objectif est de rester chez IONOS.
- **Coolify :** trop gourmand en RAM (1,5 à 2 Go) pour un petit VPS. Dokploy reste une option si une interface web de déploiement devient utile.
- **MinIO :** sa version communautaire a été fortement réduite ; Garage est plus léger.
