# 0002 — Architecture hexagonale pour l'API

- **Statut :** Acceptée (formalise l'existant)
- **Date :** 2026-10-21

## Contexte
L'API existante est déjà organisée en `Domain / Application / Infrastructure`, avec des entités Doctrine séparées des modèles métier. L'audit (compte rendu 01) confirme que cette séparation est bien tenue, avec quelques fuites à corriger.

## Décision
On conserve et on renforce cette architecture. Les règles détaillées sont dans `api/CLAUDE.md` :
- le domaine ne dépend d'aucun framework ;
- un repository renvoie toujours des objets du domaine ;
- les contrôleurs sont fins ;
- les erreurs sont centralisées dans `ExceptionListener`.

## Conséquences
- Plus de code « de plomberie » (mappers, DTO), en grande partie générable avec l'aide de Claude (gain IA « fort » dans la roadmap).
- Le métier reste testable sans base de données.
- Les deux espaces de routes `/api/shop` et `/api/admin` réutilisent les mêmes use cases.
- Exclut les solutions qui travaillent directement sur les entités Doctrine (EasyAdmin, API Platform en mode entités) sans adaptation.

## Alternatives écartées
- **Repartir sur Sylius :** plateforme e-commerce complète, mais elle impose son architecture et oblige à jeter l'existant. Elle reste envisageable si l'objectif devient de vendre au plus vite.
- **Architecture Symfony « classique »** (entités Doctrine utilisées partout) : plus rapide au début, mais couple le métier à la persistance.
