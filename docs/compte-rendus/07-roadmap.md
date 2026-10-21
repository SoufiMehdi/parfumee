# 07 — Roadmap : API, boutique et back-office

> Établie le 21/10/2026, à partir de l'état du code décrit dans les comptes rendus 00 à 06.
> Deux estimations par tâche, en **jours pour une personne**, hors imprévus (prévois +30 % de marge) :
> - **Humain** : un développeur qui code tout à la main ;
> - **Avec IA** : toi + Claude, temps de relecture et de test compris. La méthode est détaillée en section 4.
> Un CSV importable dans ClickUp accompagne ce fichier : `roadmap-clickup.csv`.

## 1. Architecture cible

```
                    ┌────────────────────────┐
  Clients  ───────▶ │  Front BOUTIQUE        │ ──┐   /api/shop/*  (public + ROLE_USER)
                    │  (SSR pour le SEO)     │   │
                    └────────────────────────┘   │    ┌──────────────────────────┐    ┌────────────┐
                                                 ├──▶ │  parfumee-api (Symfony)  │──▶ │ PostgreSQL │
                    ┌────────────────────────┐   │    │  Domain / App / Infra    │    └────────────┘
  Équipe   ───────▶ │  Front BACK-OFFICE     │ ──┘    └─────────┬────────────────┘
                    │  (React-admin)         │   /api/admin/*   │  Stripe · Mailer · Stockage images
                    └────────────────────────┘  (ROLE_ADMIN)    ▼
```

Principes :

- **Une seule API, deux espaces de routes.** `/api/shop` est consommé par la boutique (lecture publique du catalogue, panier, commandes du client connecté). `/api/admin` est consommé par le back-office (CRUD complet, réservé aux rôles admin). Les **mêmes use cases** servent les deux : seuls les contrôleurs, la sécurité et les presenters changent.
- Le contrat d'API est décrit en **OpenAPI** : les deux fronts génèrent leur client TypeScript à partir de cette description, au lieu de l'écrire à la main.
- Chaque phase livre une fonctionnalité **de bout en bout** (API + back-office + boutique) plutôt que « toute l'API puis tous les fronts ». On peut ainsi tester et montrer quelque chose à chaque étape.

## 2. Back-office : outils open source pour gagner du temps

| Option | Principe | Temps pour un BO complet | ✅ Avantages | ⚠️ Limites |
|---|---|---|---|---|
| **React-admin** (MIT) — *recommandé* | Front React séparé qui consomme ton API via un `dataProvider` | ~10 j | Respecte ton choix « API + 2 fronts » ; réutilise tes use cases et tes règles métier ; listes, filtres, formulaires, upload, auth JWT fournis ; très documenté | L'API doit suivre un format de liste standard (pagination, tri, total) ; un projet front de plus à maintenir |
| **EasyAdmin** (MIT, bundle Symfony) | Back-office généré **dans** l'app Symfony, en Twig, à partir des entités Doctrine | ~4–5 j | Le plus rapide ; zéro front à créer ; même dépôt, même déploiement | Travaille directement sur les entités Doctrine et **contourne ton domaine** (ex. la règle « prix ≥ 0 ») sauf à brancher tes use cases dans les CRUD controllers ; ne passe pas par l'API |
| **API Platform + API Platform Admin** | Expose l'API au format Hydra ; l'admin (basé sur React-admin) se génère tout seul | ~3 j pour l'admin, mais grosse refonte de l'API | OpenAPI, pagination, filtres et admin quasi gratuits | Demande de réécrire tes contrôleurs en *State Providers/Processors* ; courbe d'apprentissage |
| **Sylius 2** (MIT, e-commerce Symfony complet) | Repartir d'une plateforme e-commerce déjà faite (catalogue, panier, commandes, paiement, admin, API) | Plusieurs semaines gagnées sur le métier | Panier, checkout, promotions, taxes, livraison et admin déjà codés et éprouvés | Tu jettes une bonne partie de ton code actuel ; framework lourd à personnaliser ; ton architecture hexagonale ne s'applique plus |

**Ma recommandation : React-admin.** C'est cohérent avec ton objectif (une API qui alimente deux fronts), ça ne casse pas ton architecture, et le temps gagné reste important par rapport à un back-office codé de zéro (~20–25 j). Garde **EasyAdmin** comme plan B si tu veux un back-office en quelques jours pour saisir tes premiers produits.

**À considérer sérieusement :** si ton objectif principal est de **vendre vite** plutôt que d'apprendre ou de maîtriser chaque brique, Sylius couvre en standard la quasi-totalité des phases 2 à 6 ci-dessous. C'est une décision à prendre **avant** d'attaquer la phase 2.

## 3. Briques open source à intégrer (côté API)

| Besoin | Outil | Phase |
|---|---|---|
| CORS pour les deux fronts | `nelmio/cors-bundle` | 1 |
| Doc OpenAPI + Swagger UI | `nelmio/api-doc-bundle` | 1 |
| Refresh token JWT | `gesdinet/jwt-refresh-token-bundle` | 1 |
| Dates auto, slugs | `stof/doctrine-extensions-bundle` (Gedmo) | 1 |
| Fixtures et factories de test | `zenstruck/foundry` | 0 |
| Anti brute-force | `symfony/rate-limiter` (+ `login_throttling`) | 1 |
| Statuts de commande | `symfony/workflow` | 5 |
| Emails asynchrones | `symfony/mailer` + `symfony/messenger` | 3 / 6 |
| Factures PDF | `dompdf/dompdf` | 5 |
| Recherche produit | PostgreSQL full-text, puis **Meilisearch** si besoin | 2 |
| Stockage images S3 / CDN | `league/flysystem-bundle` + LiipImagine (déjà présent) | 7 |
| Suivi des erreurs | Sentry ou **GlitchTip** (auto-hébergeable) | 7 |
| Paiement | SDK `stripe/stripe-php` (service payant, SDK open source), ou Mollie | 5 |
| Client TypeScript des fronts | `openapi-typescript` / `orval` | 1 |
| Tests E2E | Playwright | 7 |

## 4. Vue d'ensemble

| Phase | API | Boutique | Back-office | DevOps | **Total humain (j)** | **Total avec IA (j)** |
|---|---|---|---|---|---|---|
| Phase 0 — Stabiliser l'existant | 5.5 | – | – | 3.5 | **9** | **5.25** |
| Phase 1 — Socle API pour deux fronts | 6.25 | 1 | 2 | – | **9.25** | **5.25** |
| Phase 2 — Catalogue complet | 11.5 | 5 | 3.5 | – | **20** | **11** |
| Phase 3 — Comptes clients | 4.5 | 2.5 | 1 | – | **8** | **4.5** |
| Phase 4 — Panier | 3.5 | 2 | – | – | **5.5** | **3.25** |
| Phase 5 — Commande et paiement | 10 | 5 | 2 | – | **17** | **9.75** |
| Phase 6 — Livraison et emails | 6 | – | 1 | – | **7** | **3.75** |
| Phase 7 — Mise en production (MVP) | – | 2 | – | 5 | **7** | **5.5** |
| Phase 8 — Contenu, marketing et SEO | 6.5 | 2 | 2 | – | **10.5** | **4.75** |
| Phase 9 — Pilotage et améliorations | 3.5 | – | 2.5 | – | **6** | **2.5** |
| **Total** | | | | | **99.25** | **55.5** |

*Les colonnes par périmètre (API, Boutique…) sont en jours humains.*

| | Humain | Avec IA |
|---|---|---|
| **MVP vendable (phases 0 à 7)** | ≈ 82.75 j (≈ 4 mois à temps plein) | **≈ 48.25 j** (≈ 2,5 mois à temps plein) |
| **Total (80 tâches)** | ≈ 99.25 j | **≈ 55.5 j** |

#### Comment est calculée l'estimation « avec IA »

Chaque tâche reçoit un niveau de gain, appliqué à l'estimation humaine. Le coefficient inclut le temps que **tu** passes à cadrer la demande, relire le code produit et tester.

| Gain | Coefficient | Types de tâches | Nb de tâches |
|---|---|---|---|
| **Fort** | × 0,4 | Code structuré et répétitif : CRUD, DTO et validation, mappers, migrations, config de bundles, tests, écrans React-admin, endpoints qui suivent un schéma existant | 48 |
| **Moyen** | × 0,65 | Modélisation métier (variantes, panier, TVA), intégrations externes (Stripe, webhooks), front boutique (rendu visuel et ergonomie à arbitrer), CI, tests d'intégration | 27 |
| **Faible** | × 0,9 | Hébergement, sauvegardes, secrets, API transporteurs (comptes et contrats), contenus légaux : surtout des décisions et de la configuration hors code | 5 |

**Hypothèses :**

- **Fourchette haute.** Ces chiffres supposent le fonctionnement de cette session : je lis et j'écris tes fichiers, mais **c'est toi qui lances** `composer`, `phpunit`, les migrations et `git`, puis qui me renvoies les erreurs.
- **Fourchette basse (MVP vers 35–40 j) avec Claude Code installé dans ton dépôt.** Je peux alors lancer les tests et corriger jusqu'à ce qu'ils passent, ce qui supprime une bonne partie des allers-retours.
- **Ce que l'IA ne réduit pas, et qui n'est pas compté :** les décisions de la section 6, les photos et textes produits, l'apprentissage de nouveaux outils si tu ne les connais pas encore (React-admin, Stripe…).

Jalons :

1. **Fin phase 1** : l'API est sécurisée et documentée, les deux fronts sont initialisés et se connectent.
2. **Fin phase 2** : tu saisis tes bougies dans le back-office, elles s'affichent sur la boutique.
3. **Fin phase 5** : un client peut commander et payer (en mode test Stripe).
4. **Fin phase 7** : **mise en ligne**.

## 5. Détail des phases

### Phase 0 — Stabiliser l'existant
**Objectif :** corriger les points bloquants des comptes rendus 03 à 05 avant d'empiler de nouvelles fonctionnalités.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P0-01 | API | Activer le contrôle d'accès (access_control) + Voter « propriétaire du compte »<br><sub>Routes publiques (catalogue GET, login, inscription), routes client (ROLE_USER), routes admin (ROLE_ADMIN). Voter pour PUT/DELETE /users/{id}.</sub> | 🔴 Urgent | 1 | 0.75 | moyen | – |
| P0-02 | API | Valider les uploads d'images (type, taille, refus SVG)<br><sub>Contrainte Assert\Image, max 5 Mo, jpeg/png/webp uniquement.</sub> | 🔴 Urgent | 0.5 | 0.25 | fort | – |
| P0-03 | API | Corriger DoctrinePictureRepository et le mapping inversedBy 'images'<br><sub>Utiliser DoctrinePicture + PictureMapper ; doctrine:schema:validate doit passer.</sub> | 🔴 Urgent | 0.5 | 0.25 | fort | – |
| P0-04 | API | Centraliser la gestion d'erreurs (codes HTTP + messages masqués en prod)<br><sub>Exceptions métier dédiées (NotFound, Conflict, Validation) mappées dans ExceptionListener ; supprimer les try/catch des contrôleurs.</sub> | 🟠 Haute | 1 | 0.5 | fort | – |
| P0-05 | API | Valider les entrées : #[MapRequestPayload] + contraintes Assert sur les DTO<br><sub>Remplace les mappers manuels ; 422 automatiques ; ids validés avec Requirement::UUID.</sub> | 🟠 Haute | 1.5 | 0.5 | fort | P0-04 |
| P0-06 | API | Persister le slug produit (colonne unique + SluggerInterface)<br><sub>Migration + mapping dans les deux sens.</sub> | 🟠 Haute | 0.5 | 0.25 | fort | – |
| P0-07 | API | Corriger les bugs ponctuels (AttributesMapper, updateDetails, inscription téléphone/adresse)<br><sub>Voir compte rendu 03 : B5, B6, B7, B9.</sub> | 🟠 Haute | 0.5 | 0.25 | fort | – |
| P0-08 | DevOps | Secrets : APP_SECRET, clés JWT, .env.local, nettoyage .env.test<br><sub>Changer le mot de passe BDD s'il est dans l'historique git.</sub> | 🔴 Urgent | 0.25 | 0.25 | faible | – |
| P0-09 | DevOps | Réparer l'outillage qualité (PHPStan, CS-Fixer, strict_types, supprimer Psalm/.php_cs) | 🟡 Normale | 0.5 | 0.25 | fort | – |
| P0-10 | DevOps | Tests d'intégration Doctrine (KernelTestCase + Foundry + BDD de test)<br><sub>zenstruck/foundry pour les factories et fixtures.</sub> | 🟠 Haute | 1.5 | 1 | moyen | P0-03 |
| P0-11 | DevOps | CI GitHub Actions (lint + PHPStan + PHPUnit + PostgreSQL) | 🟠 Haute | 1 | 0.75 | moyen | P0-09 |
| P0-12 | DevOps | README d'installation (docker compose, migrations, clés JWT, fixtures) | 🟡 Normale | 0.25 | 0.25 | fort | – |

### Phase 1 — Socle API pour deux fronts
**Objectif :** une API propre à consommer : routes séparées shop/admin, CORS, doc OpenAPI, listes paginées, auth complète. Les deux fronts sont initialisés.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P1-01 | API | Séparer les routes /api/shop (public/client) et /api/admin (back-office), routes REST<br><sub>POST /products au lieu de /create-product, etc. Préfixe versionné possible (/api/v1).</sub> | 🟠 Haute | 1 | 0.5 | fort | P0-01 |
| P1-02 | API | Configurer CORS (nelmio/cors-bundle) pour les deux fronts | 🟠 Haute | 0.25 | 0.25 | fort | – |
| P1-03 | API | Documentation OpenAPI (nelmio/api-doc-bundle) + Swagger UI<br><sub>Sert aussi à générer le client TypeScript des fronts.</sub> | 🟠 Haute | 1 | 0.5 | fort | P1-01 |
| P1-04 | API | Format standard de liste : pagination, tri, filtres<br><sub>Compatible React-admin (total dans X-Total-Count ou Content-Range).</sub> | 🟠 Haute | 1.5 | 0.5 | fort | P1-01 |
| P1-05 | API | Refresh token JWT (gesdinet) + endpoint GET /api/shop/me | 🟠 Haute | 1 | 0.75 | moyen | P0-01 |
| P1-06 | API | Rôles ROLE_ADMIN / ROLE_EDITOR + commande console de création d'admin | 🟠 Haute | 0.5 | 0.25 | fort | P0-01 |
| P1-07 | API | Rate-limiting login/inscription (login_throttling + rate-limiter) | 🟡 Normale | 0.5 | 0.25 | fort | – |
| P1-08 | API | Dates created_at / updated_at sur les entités (stof/doctrine-extensions) | 🟡 Normale | 0.5 | 0.25 | fort | – |
| P1-09 | Back-office | Initialiser le back-office React-admin + authProvider JWT<br><sub>Projet séparé (Vite + React-admin).</sub> | 🟠 Haute | 1 | 0.75 | moyen | P1-05 |
| P1-10 | Back-office | dataProvider React-admin adapté au format de l'API | 🟠 Haute | 1 | 0.5 | fort | P1-04 |
| P1-11 | Boutique | Initialiser le front boutique + client API typé généré depuis l'OpenAPI<br><sub>Ex. Next.js ou Nuxt (SSR pour le SEO) + openapi-typescript.</sub> | 🟠 Haute | 1 | 0.75 | moyen | P1-03 |

### Phase 2 — Catalogue complet
**Objectif :** gérer les bougies de bout en bout (catégories, variantes de taille ou de parfum, stock, images) et les afficher sur la boutique.
> 💡 Les **variantes** (P2-03) sont le chantier le plus structurant : une même bougie en 180 g et en 300 g n'a ni le même prix ni le même stock. Le panier et les commandes pointeront sur une variante, pas sur un produit : il faut la faire avant la phase 4.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P2-01 | API | CRUD catégories (admin) + catégories parentes/enfants | 🟠 Haute | 1.5 | 0.5 | fort | P1-01 |
| P2-02 | API | Statut produit (brouillon/publié/archivé) + suppression/archivage<br><sub>La boutique ne voit que les produits publiés.</sub> | 🟠 Haute | 1 | 0.5 | fort | – |
| P2-03 | API | Déclinaisons produit (variantes taille/parfum : SKU, prix, poids)<br><sub>Ex. bougie 180 g / 300 g ; le panier pointe sur une variante.</sub> | 🟠 Haute | 3 | 2 | moyen | P2-02 |
| P2-04 | API | Stock par variante + seuil d'alerte | 🟠 Haute | 1 | 0.5 | fort | P2-03 |
| P2-05 | API | Images : ordre, image principale, suppression, URL miniatures LiipImagine | 🟡 Normale | 1.5 | 0.5 | fort | P0-03 |
| P2-06 | API | Caractéristiques produit (cire, durée de combustion, notes olfactives…)<br><sub>Faire évoluer Attribute (JSON) vers des caractéristiques configurables.</sub> | 🟡 Normale | 1 | 0.75 | moyen | – |
| P2-07 | API | Endpoints boutique : produit par slug, mis en avant, produits liés | 🟡 Normale | 1 | 0.5 | fort | P0-06 |
| P2-08 | API | Recherche texte (PostgreSQL full-text, ou Meilisearch) | 🟡 Normale | 1.5 | 1 | moyen | – |
| P2-09 | Back-office | Écrans produits, variantes, stock et upload d'images | 🟠 Haute | 3 | 1.25 | fort | P1-10 |
| P2-10 | Back-office | Écrans catégories | 🟡 Normale | 0.5 | 0.25 | fort | P2-01 |
| P2-11 | Boutique | Pages accueil, liste catégorie avec filtres, fiche produit | 🟠 Haute | 4 | 2.5 | moyen | P1-11 |
| P2-12 | Boutique | Recherche produit | 🟡 Normale | 1 | 0.75 | moyen | P2-08 |

### Phase 3 — Comptes clients
**Objectif :** un espace client complet et conforme au RGPD.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P3-01 | API | CRUD adresses du client connecté (livraison/facturation, par défaut) | 🟠 Haute | 1 | 0.5 | fort | P1-05 |
| P3-02 | API | Changement de mot de passe + politique de mot de passe | 🟠 Haute | 0.5 | 0.25 | fort | – |
| P3-03 | API | Mot de passe oublié (token + email)<br><sub>Envoi via Symfony Mailer (synchrone au début, passé en asynchrone en P6-02).</sub> | 🟠 Haute | 1 | 0.5 | fort | – |
| P3-04 | API | Vérification de l'email à l'inscription | 🟡 Normale | 1 | 0.5 | fort | – |
| P3-05 | API | RGPD : export des données et suppression du compte | 🟡 Normale | 1 | 0.75 | moyen | – |
| P3-06 | Back-office | Écrans clients (liste, détail, commandes du client) | 🟡 Normale | 1 | 0.5 | fort | P1-10 |
| P3-07 | Boutique | Inscription, connexion, espace client (profil, adresses, mot de passe) | 🟠 Haute | 2.5 | 1.5 | moyen | P3-01 |

### Phase 4 — Panier
**Objectif :** un panier qui fonctionne en invité comme en connecté, avec des montants fiables (en centimes).
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P4-01 | API | Modèle Panier / LignePanier (domaine + persistance) | 🟠 Haute | 1.5 | 0.5 | fort | P2-03 |
| P4-02 | API | Panier invité (token) + fusion à la connexion | 🟠 Haute | 1 | 0.75 | moyen | P4-01 |
| P4-03 | API | Calcul des totaux (HT, TVA, TTC) + contrôle du stock<br><sub>Montants en centimes (int).</sub> | 🟠 Haute | 1 | 0.75 | moyen | P4-01 |
| P4-04 | Boutique | Mini-panier + page panier | 🟠 Haute | 2 | 1.25 | moyen | P4-02 |

### Phase 5 — Commande et paiement
**Objectif :** transformer un panier en commande payée, avec un suivi des statuts côté admin.
> ⚠️ Ne jamais considérer une commande comme payée sur la seule redirection du navigateur : seul le **webhook Stripe signé** fait foi.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P5-01 | API | Modèle Commande / LigneCommande (prix figés) + numérotation | 🔴 Urgent | 1.5 | 0.5 | fort | P4-03 |
| P5-02 | API | Statuts de commande avec Symfony Workflow<br><sub>en attente → payée → préparée → expédiée → livrée ; annulée ; remboursée.</sub> | 🔴 Urgent | 1 | 0.5 | fort | P5-01 |
| P5-03 | API | Checkout : adresses, mode de livraison, récapitulatif | 🔴 Urgent | 1.5 | 1 | moyen | P5-01 |
| P5-04 | API | Paiement Stripe (PaymentIntent) + webhooks signés<br><sub>Alternative : Mollie, très utilisé en France.</sub> | 🔴 Urgent | 2.5 | 1.5 | moyen | P5-03 |
| P5-05 | API | Réservation et décrément du stock au paiement | 🟠 Haute | 1 | 0.75 | moyen | P5-04 |
| P5-06 | API | Historique des commandes du client | 🟠 Haute | 0.5 | 0.25 | fort | P5-01 |
| P5-07 | API | Factures PDF (dompdf) + numérotation légale | 🟠 Haute | 1 | 0.75 | moyen | P5-04 |
| P5-08 | API | Remboursement total/partiel depuis l'admin | 🟡 Normale | 1 | 0.75 | moyen | P5-04 |
| P5-09 | Back-office | Écrans commandes : liste, détail, changement de statut, remboursement | 🔴 Urgent | 2 | 0.75 | fort | P5-02 |
| P5-10 | Boutique | Tunnel de commande + paiement Stripe + page de confirmation | 🔴 Urgent | 4 | 2.5 | moyen | P5-04 |
| P5-11 | Boutique | Historique et détail des commandes dans l'espace client | 🟠 Haute | 1 | 0.5 | fort | P5-06 |

### Phase 6 — Livraison et emails
**Objectif :** frais de port, suivi colis, emails transactionnels.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P6-01 | API | Modes et tarifs de livraison (forfait, poids, gratuité au-delà d'un seuil) | 🔴 Urgent | 1.5 | 0.5 | fort | P5-03 |
| P6-02 | API | Emails transactionnels (Mailer + Messenger asynchrone + templates)<br><sub>Confirmation de commande, expédition, mot de passe oublié, bienvenue.</sub> | 🔴 Urgent | 2 | 0.75 | fort | – |
| P6-03 | API | Numéro et lien de suivi colis | 🟠 Haute | 0.5 | 0.25 | fort | P5-02 |
| P6-04 | API | Points relais (Mondial Relay / Colissimo) — optionnel | ⚪ Basse | 2 | 1.75 | faible | P6-01 |
| P6-05 | Back-office | Écran modes de livraison + saisie du suivi | 🟠 Haute | 1 | 0.5 | fort | P6-01 |

### Phase 7 — Mise en production (MVP)
**Objectif :** mettre en ligne de façon fiable, conforme et sauvegardée.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P7-01 | DevOps | Hébergement + Docker de production (FrankenPHP ou Nginx/PHP-FPM) | 🔴 Urgent | 1.5 | 1.25 | faible | – |
| P7-02 | DevOps | Stockage des images sur S3 compatible (Flysystem) + CDN | 🟠 Haute | 1 | 0.75 | moyen | – |
| P7-03 | DevOps | Suivi des erreurs (Sentry/GlitchTip) + logs | 🟠 Haute | 0.5 | 0.25 | moyen | – |
| P7-04 | DevOps | Sauvegardes automatiques de la BDD | 🔴 Urgent | 0.5 | 0.5 | faible | – |
| P7-05 | Boutique | Pages légales, bandeau cookies, responsive, accessibilité<br><sub>CGV, mentions légales, politique de confidentialité.</sub> | 🔴 Urgent | 2 | 1.75 | faible | – |
| P7-06 | DevOps | Tests E2E du parcours d'achat (Playwright) | 🟠 Haute | 1.5 | 1 | moyen | P5-10 |

### Phase 8 — Contenu, marketing et SEO
**Objectif :** faire vivre le site (pages, bannières, promos, avis) et être trouvé sur Google.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P8-01 | API | Pages de contenu (à propos, FAQ…) + bannières de la page d'accueil | 🟡 Normale | 1.5 | 0.5 | fort | – |
| P8-02 | API | Codes promo (pourcentage, montant, livraison offerte, dates, quotas) | 🟡 Normale | 1.5 | 0.5 | fort | P4-03 |
| P8-03 | API | Avis clients avec modération | 🟡 Normale | 1.5 | 0.5 | fort | P5-01 |
| P8-04 | API | Newsletter (consentement + synchro Brevo) | ⚪ Basse | 1 | 0.75 | moyen | – |
| P8-05 | API | SEO : meta title/description, sitemap.xml, données structurées | 🟡 Normale | 1 | 0.5 | fort | P0-06 |
| P8-06 | Back-office | Écrans contenus, bannières, codes promo, modération des avis | 🟡 Normale | 2 | 0.75 | fort | P8-01 |
| P8-07 | Boutique | Affichage contenus, avis, saisie code promo, inscription newsletter | 🟡 Normale | 2 | 1.25 | moyen | P8-02 |

### Phase 9 — Pilotage et améliorations
**Objectif :** suivre l'activité et outiller la gestion au quotidien.
| ID | Périmètre | Tâche | Priorité | Humain (j) | Avec IA (j) | Gain IA | Dépend de |
|---|---|---|---|---|---|---|---|
| P9-01 | API | Endpoints statistiques (CA, commandes, panier moyen, stock bas) | 🟡 Normale | 1.5 | 0.5 | fort | P5-01 |
| P9-02 | Back-office | Tableau de bord (graphiques et alertes) | 🟡 Normale | 1.5 | 0.5 | fort | P9-01 |
| P9-03 | API | Journal d'audit des actions admin | ⚪ Basse | 1 | 0.5 | fort | – |
| P9-04 | Back-office | Export CSV commandes/clients (comptabilité) | ⚪ Basse | 1 | 0.5 | fort | – |
| P9-05 | API | Liste de souhaits / favoris | ⚪ Basse | 1 | 0.5 | fort | P3-01 |

## 6. Décisions à prendre avant de démarrer

1. **Back-office :** React-admin (recommandé), EasyAdmin, ou bascule vers Sylius ?
2. **Framework du front boutique :** Next.js (React, cohérent avec React-admin) ou Nuxt (Vue) ? Le SSR est important pour le référencement d'un site marchand.
3. **Prestataire de paiement :** Stripe ou Mollie ?
4. **Hébergement :** VPS (OVH, Scaleway, Hetzner) avec Docker, ou PaaS (Platform.sh, Clever Cloud) ?
5. **TVA et statut :** micro-entreprise en franchise de TVA, ou TVA collectée ? Cela change le calcul des totaux (P4-03) et les factures (P5-07).
