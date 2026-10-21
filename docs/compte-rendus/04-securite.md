# 04 — Sécurité

## 🔴 Critique

### S1. Aucun contrôle d'accès sur l'API
`config/packages/security.yaml`

```yaml
access_control:
    # - { path: ^/admin, roles: ROLE_ADMIN }
    # - { path: ^/profile, roles: ROLE_USER }
```
Le firewall `main` est configuré en `jwt` + `lazy`, mais **aucune règle n'exige d'authentification**. Toutes les routes sont donc publiques :

- `GET /users/list` expose l'email, le nom, le téléphone, les rôles et les adresses de **tous** les clients (enjeu RGPD) ;
- `DELETE /users/{id}` et `PUT /users/{id}` permettent de supprimer ou modifier n'importe quel compte ;
- `POST /create-product`, `PUT /edit-product/{id}` et `POST /upload-picture/{id}` permettent à n'importe qui de modifier le catalogue.

**À faire :**
```yaml
access_control:
    - { path: ^/users/(login|create)$, roles: PUBLIC_ACCESS }
    - { path: ^/products, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/product/, methods: [GET], roles: PUBLIC_ACCESS }
    - { path: ^/users/list, roles: ROLE_ADMIN }
    - { path: ^/(create-product|edit-product|upload-picture), roles: ROLE_ADMIN }
    - { path: ^/, roles: ROLE_USER }
```
Ajouter aussi un **Voter** pour que `PUT` et `DELETE /users/{id}` ne soient permis qu'au propriétaire du compte ou à un admin.

### S2. Upload de fichiers sans validation
`Infrastructure/Storage/LocalFileStorage.php` + `ProductController::uploadPicture`

- Aucun contrôle du type MIME ni de la taille. L'extension vient de `guessExtension()` (basée sur le contenu, ce qui est déjà mieux que le nom fourni par le client), mais un fichier HTML ou SVG contenant du JavaScript sera accepté et servi depuis `public/uploads/` : risque de **XSS stocké**.
- **À faire :** contrainte `Assert\Image(maxSize: '5M', mimeTypes: ['image/jpeg','image/png','image/webp'])`, refus du SVG, et idéalement réencodage via LiipImagine.

## 🟠 Élevé

### S3. Fuite d'informations dans les erreurs
`Infrastructure/EventListener/ExceptionListener.php:28`
```php
'error' => 'An unexpected error occurred.' . $exception->getMessage()
```
Les messages d'exception internes (erreurs SQL, chemins, noms de classes) sont renvoyés au client, y compris en production. `ProductController::uploadPicture` fait de même. → N'afficher le message que si `kernel.debug` est vrai, et le logger côté serveur.

### S4. Secrets et configuration
- `.env` : `APP_SECRET` est **vide**. Il faut le définir (via `.env.local` ou Symfony Secrets).
- `.env` contient un `JWT_PASSPHRASE` et un mot de passe de BDD. Ce fichier est versionné par défaut sous Symfony : vérifier que ce ne sont que des valeurs de dev, et que les vraies valeurs vivent dans `.env.local` ou dans le vault.
- `.env.test` contient un **mot de passe PostgreSQL en clair et une IP du réseau local**. Il est bien dans `.gitignore`, mais s'il a été commité avant l'ajout de cette règle, il reste dans l'historique git : à vérifier avec `git log --all -- .env.test`, et changer le mot de passe le cas échéant.
- `.env.test` : `DATABASE_URL` en `postgres://` avec `serverVersion=8.0.32&charset=utf8mb4`, des paramètres **MySQL**. C'est incohérent avec PostgreSQL 16.
- `compose.yaml` : mot de passe par défaut `!ChangeMe!`.

### S5. Politique de mots de passe absente
Ni longueur minimale ni contrôle de complexité à l'inscription. Symfony fournit `Assert\PasswordStrength` et `Assert\NotCompromisedPassword`.

### S6. Pas de protection contre le brute-force
Aucun rate-limiting sur `/users/login` ni `/users/create`. Le composant `symfony/rate-limiter` (avec `login_throttling` sur le firewall) règle ce point.

## 🟡 Moyen / à prévoir

| # | Point |
|---|---|
| S7 | Pas de **CORS** configuré (`nelmio/cors-bundle`) : nécessaire dès qu'un front sur un autre domaine appelle l'API. |
| S8 | `framework.session: true` sur une API stateless : surface inutile, à désactiver. |
| S9 | Pas de durée de vie de JWT explicite ni de refresh token (`gesdinet/jwt-refresh-token-bundle`). |
| S10 | Énumération d'emails possible via `POST /users/create` (le 409 « email already taken » révèle qu'un compte existe). C'est acceptable pour un e-commerce, mais à garder en tête. |
| S11 | Aucune validation de longueur sur les champs texte (nom produit, adresse…) : la BDD lèvera une erreur 500 au-delà de 255 caractères. |

## Points positifs

- Requêtes SQL natives **paramétrées** dans `findByFilter` : pas d'injection SQL.
- Mots de passe hachés via l'algorithme `auto` de Symfony (bcrypt/argon2).
- Login avec message d'erreur identique pour email inconnu et mot de passe faux.
- Noms de fichiers uploadés « slugifiés » et suffixés d'un `uniqid()` : pas de path traversal.
- Les clés `config/jwt/*.pem` sont ignorées par git.
