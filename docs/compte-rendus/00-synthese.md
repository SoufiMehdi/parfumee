# Compte rendu — projet `parfumee-api`

> Analyse réalisée le 21/10/2026 sur la branche `main` (dernier `git pull` du jour).
> Dépôt : `github.com:SoufiMehdi/parfumee-api` — branches `main` et `feat/data-product`.

## En une phrase

`parfumee-api` est l'API back-end (Symfony 7.4 / PHP 8.2+ / PostgreSQL 16) d'un site e-commerce de bougies parfumées. Elle gère un **catalogue** (produits, catégories, images) et des **utilisateurs** (inscription, connexion JWT, CRUD). Elle suit une architecture hexagonale (Domain / Application / Infrastructure).

## Sommaire des comptes rendus

| Fichier | Contenu |
|---|---|
| [01-architecture.md](01-architecture.md) | Stack technique, organisation du code, modèle de données, migrations |
| [02-fonctionnalites-api.md](02-fonctionnalites-api.md) | Liste des endpoints, ce qui marche, ce qui manque |
| [03-bugs-et-anomalies.md](03-bugs-et-anomalies.md) | Bugs relevés dans le code, avec fichier et ligne |
| [04-securite.md](04-securite.md) | Failles et points de vigilance sécurité |
| [05-qualite-tests-outillage.md](05-qualite-tests-outillage.md) | Tests, couverture, PHPStan / Psalm / CS-Fixer |
| [06-recommandations.md](06-recommandations.md) | Plan d'action priorisé |
| [07-roadmap.md](07-roadmap.md) | Roadmap complète API + boutique + back-office (CSV ClickUp : `roadmap-clickup.csv`) |

## Chiffres clés

| Indicateur | Valeur |
|---|---|
| Fichiers PHP dans `src/` | 61 (dont 2 contrôleurs, 12 use cases, 6 modèles domaine, 5 entités Doctrine) |
| Endpoints HTTP | 13 (6 catalogue, 7 utilisateurs) |
| Migrations Doctrine | 4 (mai → juin 2026) |
| Fichiers de test | 34, environ 110 méthodes de test |
| Dernière couverture mesurée | 94,8 % des lignes, mais rapport daté du 22/05/2026, **avant** l'ajout du module Utilisateur (donc obsolète) |

## Points forts

- **Architecture propre et lisible** : séparation nette Domain / Application / Infrastructure, ports (interfaces) côté domaine, adaptateurs Doctrine côté infrastructure, mappers dédiés entre entités Doctrine et modèles métier.
- **Modèle métier qui porte des règles** : prix négatif refusé (`InvalidPriceException`), objets-valeurs `Email` et `PhoneNumber` validés à la construction.
- **Catalogue générique** : attributs produit stockés en JSON (parfum, taille, poids, présentation), donc extensible à d'autres catégories (une catégorie « Vêtements » est déjà prévue dans les fixtures).
- **Filtrage produit** par catégorie, fourchette de prix, parfum et taille, avec des requêtes paramétrées (pas d'injection SQL).
- **Bonne habitude de test** : chaque use case, mapper, presenter et repository a son fichier de test.

## Points d'attention majeurs

1. 🔴 **Aucune route n'est protégée** : `access_control` est vide dans `security.yaml`. N'importe qui peut lister tous les utilisateurs (avec emails), les supprimer, ou créer/modifier des produits. → [04-securite.md](04-securite.md)
2. 🔴 **`DoctrinePictureRepository` est cassé** : il interroge `Picture::class` (modèle domaine) au lieu de l'entité Doctrine, donc `findById`, `findByProductId` et `findAll` échouent à l'exécution. → [03-bugs-et-anomalies.md](03-bugs-et-anomalies.md)
3. 🟠 **Le slug produit n'est pas persisté** : à la relecture, tous les produits renvoient `"slug-placeholder"`.
4. 🟠 **L'inscription ignore le téléphone et l'adresse** envoyés par le client.
5. 🟠 **Le poids d'un produit sans poids devient `0` au lieu de `null`**, et lève un warning PHP (ce qui fait échouer PHPUnit, configuré en `failOnWarning`).
6. 🟠 **Gestion d'erreurs incohérente** : plusieurs `\Exception` génériques renvoient des 500 au lieu de 404/400, et le message d'exception brut est renvoyé au client.
7. 🟡 **Outillage qualité non fonctionnel** : config Psalm mal nommée (`pslam.xml`) et pointant vers des dossiers inexistants ; script PHPStan pointant vers un fichier ignoré par git.

## Niveau de maturité

**Prototype avancé / MVP en construction.** Les fondations sont solides, mais le projet n'est pas déployable en production en l'état (sécurité absente, quelques bugs bloquants). Il manque aussi les briques e-commerce clés : panier, commandes, paiement, stock.
