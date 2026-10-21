---
name: fin-de-session
description: Clôture une session de travail sur Parfumée en mettant à jour docs/STATUS.md (et les ADR si une décision a été prise) puis en proposant le commit. À utiliser quand l'utilisateur dit qu'il arrête, change de PC, ou demande de « sauvegarder le contexte ».
---

# Fin de session : sauvegarder le contexte

L'objectif est qu'une nouvelle session, sur n'importe quel PC, puisse reprendre exactement où on s'est arrêté en lisant `docs/STATUS.md`.

## 1. Faire le point
- `git status` et `git diff --stat` : ce qui a changé depuis le dernier commit.
- Les tâches de la roadmap touchées (IDs), et leur état : terminée, en cours, bloquée.

## 2. Mettre à jour `docs/STATUS.md`
- **Où on en est :** phase en cours, prochaine tâche conseillée, branche de travail.
- **Décisions en attente :** retire celles qui ont été tranchées et ajoute les nouvelles.
- **Pièges connus en cours :** retire les bugs corrigés et ajoute les nouveaux problèmes découverts (avec fichier:ligne).
- **Journal :** ajoute **en haut** une entrée datée `### AAAA-MM-JJ — <titre court>`, avec 3 à 8 puces :
  - ce qui a été fait (avec les IDs de tâche) ;
  - ce qui reste en cours et à quelle étape ;
  - les commandes à relancer sur un autre PC, par exemple une nouvelle migration (`doctrine:migrations:migrate`), un nouveau paquet (`composer install` / `npm install`), ou une nouvelle variable d'environnement à ajouter dans `.env.local` (nom seulement, jamais la valeur).

## 3. Décisions
Si une décision structurante a été prise pendant la session, crée l'ADR correspondante dans `docs/adr/` (modèle dans `docs/adr/README.md`) et ajoute-la à l'index.

## 4. Commit
- Vérifie qu'aucun secret n'est indexé (`git diff --cached` : pas de `.env.local`, de clé `.pem` ni de mot de passe).
- Propose un message Conventional Commits, avec les IDs de tâche.
- **Ne commite et ne pousse qu'avec l'accord explicite de l'utilisateur.** Rappelle-lui de déplacer les cartes correspondantes dans le kanban ClickUp.
