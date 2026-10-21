# Architecture Decision Records (ADR)

Une fiche par décision structurante. On ne modifie pas une décision acceptée : on crée une nouvelle ADR qui la remplace, et on passe l'ancienne au statut « Remplacée par ADR XXXX ».

| N° | Décision | Statut |
|---|---|---|
| [0001](0001-monorepo.md) | Monorepo unique pour l'API et les deux fronts | Acceptée |
| [0002](0002-architecture-hexagonale.md) | Architecture hexagonale pour l'API | Acceptée |
| [0003](0003-back-office-react-admin.md) | Back-office en React-admin consommant l'API | Proposée |
| [0004](0004-hebergement-ionos-docker-compose.md) | Hébergement sur un VPS IONOS avec Docker Compose et Caddy | Acceptée |

## Modèle

```markdown
# NNNN — Titre

- **Statut :** Proposée | Acceptée | Remplacée par ADR XXXX
- **Date :** AAAA-MM-JJ

## Contexte
Le problème et les contraintes.

## Décision
Ce qu'on fait.

## Conséquences
Ce que ça implique, en positif comme en négatif.

## Alternatives écartées
Et pourquoi.
```
