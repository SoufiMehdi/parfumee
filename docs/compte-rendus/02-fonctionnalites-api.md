# 02 — Fonctionnalités et endpoints

## Catalogue (`ProductController`)

| Méthode | Route | Rôle | État |
|---|---|---|---|
| GET | `/products` | Liste tous les produits | ✅ OK, mais slug faux (`slug-placeholder`) |
| GET | `/product/{id}` | Détail d'un produit | ✅ 404 si absent ; un id non-UUID provoque une erreur PostgreSQL (500) |
| GET | `/products/filter?category_id=&min_price=&max_price=&scent=&size=` | Recherche filtrée | ✅ Requête SQL native paramétrée |
| POST | `/create-product` | Création produit (JSON) | ⚠️ Pas de validation des champs ; renvoie l'id brut sous forme de chaîne JSON |
| PUT | `/edit-product/{id}` | Mise à jour produit | ⚠️ Prix négatif accepté ; erreurs renvoyées en 500 au lieu de 404 |
| POST | `/upload-picture/{productId}` | Upload image (multipart, champs `file` et `alt`) | ⚠️ Aucun contrôle de type ni de taille ; toute erreur renvoie un 500 |

Exemple de payload de création :

```json
{
  "name": "Bougie Vanille Gourmande",
  "price": 15.90,
  "category_id": "018f5370-1234-7000-8000-000000000001",
  "attributes": { "fragrance": "Vanille", "weight": 180, "size": "M", "description": "…", "presentation": "…" }
}
```

Seuls les attributs `weight`, `fragrance`, `size`, `description` et `presentation` sont conservés. Toute autre clé (par exemple `wax` dans les fichiers `.http`) est **ignorée sans erreur**.

## Utilisateurs (`UserController`, préfixe `/users`)

| Méthode | Route | Rôle | État |
|---|---|---|---|
| POST | `/users/login` | Connexion, renvoie `{ "token": "<JWT>" }` | ✅ Message identique pour email inconnu ou mot de passe faux (bien) ; nécessite les clés JWT |
| POST | `/users/create` | Inscription | ⚠️ Téléphone et adresse ignorés ; champ manquant → 500 au lieu de 400 |
| GET | `/users/list` | Liste des utilisateurs | 🔴 Public, expose les emails et rôles de tout le monde |
| GET | `/users/{id}` | Détail | ⚠️ id non-UUID → 500 |
| PUT | `/users/{id}` | Mise à jour (prénom, nom, téléphone) | ⚠️ Accepte prénom/nom vides ; aucun contrôle « c'est bien mon compte » |
| DELETE | `/users/{id}` | Suppression | 🔴 Public, n'importe qui peut supprimer n'importe quel compte |

## Données de démo (fixtures)

`CategoryFixtures` crée 3 catégories : **Bougies Parfumées** (id fixe `…0001`), **Fondants** (id fixe `…0002`) et **Vêtements** (id aléatoire). Il n'existe pas de fixture pour les produits ni pour les utilisateurs.

## Ce qui existe dans le code mais n'est pas exposé

- `Category` gère des images (`addPicture`), mais aucun endpoint ne permet d'en ajouter, et le mapping Doctrine correspondant est incomplet.
- `User::changePassword()` et `User::addAddress()` existent, sans use case ni route.
- Le port `FileStorageInterface::delete()` est implémenté, mais rien ne l'appelle (pas de suppression d'image).
- Le filtre LiipImagine `product_thumb` est configuré, mais aucune URL de miniature n'est générée par le `ProductPresenter`.

## Fonctionnalités manquantes pour un site e-commerce

| Domaine | Manque |
|---|---|
| Catalogue | Suppression de produit, CRUD catégories, pagination, tri, stock, statut publié/brouillon, recherche texte, lookup par slug |
| Comptes | Changement / réinitialisation de mot de passe, gestion des adresses, endpoint `/me`, rôles admin |
| Vente | **Panier, commandes, paiement, livraison, factures** (aucun de ces modules n'existe) |
| Transverse | Documentation OpenAPI/Swagger, CORS (indispensable pour un front séparé), versionnement d'API (`/api/v1`), rate-limiting |
