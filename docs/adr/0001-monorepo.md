# 0001 — Monorepo unique pour l'API et les deux fronts

- **Statut :** Acceptée
- **Date :** 2026-10-21

## Contexte
Le projet comporte trois applications (API Symfony, boutique, back-office), développées par une seule personne, sur plusieurs PC, avec l'aide de Claude. Il faut que le contexte (docs, décisions, conventions), la configuration Docker et le déploiement restent cohérents et faciles à retrouver.

## Décision
Un seul dépôt GitHub, `parfumee` (ex-`parfumee-api`, renommé pour garder l'historique et les PR), organisé ainsi :

```
api/  shop/  admin/  infra/  docs/  .claude/  CLAUDE.md
```

## Conséquences
- Un seul `git clone` pour retrouver tout le projet et tout le contexte sur un nouveau PC.
- Une modification d'API et son adaptation front tiennent dans une seule PR.
- La CI doit filtrer par chemin (`paths:` dans GitHub Actions) pour ne pas tout relancer à chaque commit.
- Les outils de chaque app (Composer, npm) sont lancés depuis leur sous-dossier.

## Alternatives écartées
- **Un dépôt par app :** contexte éclaté, synchronisation des versions d'API pénible pour un dev seul.
- **Des sous-modules git :** complexité inutile à cette échelle.
