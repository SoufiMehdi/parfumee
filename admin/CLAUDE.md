# Back-office — règles spécifiques

React-admin 5 (Material UI 9), React 19, Vite 8, TypeScript 5.9. Un site statique qui consomme `/api/v1/admin/*` (ADR 0003).

## Structure

```
src/
  App.tsx           <Admin> : authProvider, interface en français, page de connexion, tableau de bord
  config.ts         API_URL (VITE_API_URL) et apiUrl('/admin/me')
  auth/             session (les jetons), httpClient, authProvider, libellés des rôles
  api/schema.ts     types de l'API, générés depuis api/openapi.json : ne jamais le modifier à la main
  test/fakeApi.ts   faux serveur d'API pour les tests
```

## Règles

1. **Tout appel à l'API passe par `httpClient`** (`src/auth/httpClient.ts`), jamais par `fetch` directement.
   - Il joint le JWT, renouvelle la session une fois si le JWT a expiré, et met le message de l'API (`{"error": ...}`) dans l'erreur.
   - Le `dataProvider` (P1-10) l'utilisera aussi.
   - Seul `session.ts` appelle `fetch`, pour les routes sans JWT : connexion, renouvellement, déconnexion.
2. **Types de l'API générés :** `npm run api:types` après tout changement du contrat (`api/openapi.json`). La CI échoue si `src/api/schema.ts` n'est plus à jour. Utiliser `components['schemas']['UserView']`… plutôt que de redéclarer un type.
3. **Session :**
   - le JWT (15 minutes) reste en mémoire ; le refresh token (30 jours, à usage unique) est dans le `localStorage` (`parfumee.admin.refresh_token`), partagé par les onglets ;
   - un seul renouvellement à la fois : les appels d'un onglet partagent le même, et un verrou Web Locks (`parfumee.admin.session_renewal`) fait attendre les autres onglets. Ne jamais appeler `/token/refresh` ailleurs que dans `renewSession()` ;
   - la déconnexion révoque le refresh token (`POST /api/v1/token/revoke`) et oublie les deux jetons, même si l'API ne répond pas ;
   - limite connue : un script injecté dans la page (XSS) pourrait lire le refresh token. La parade complète, un cookie `HttpOnly`, demanderait de changer l'API. D'ici là, ne jamais insérer de HTML venu de l'API (`dangerouslySetInnerHTML`).
4. **Droits :** l'API décide.
   - Le `authProvider` lit les rôles sur `/admin/me` (`getPermissions`), pour masquer ce qu'un rôle ne peut pas faire.
   - Un compte sans rôle d'éditeur ou d'administrateur est refusé dès la connexion.
   - Un 403 de l'API laisse connecté (« accès refusé ») ; un 401 déconnecte, la session n'ayant pas pu être renouvelée.
5. **Langue :** l'interface est en français (`ra-language-french`) ; le code, les noms et les commentaires sont en anglais, comme dans l'API.
6. **Pas de télémétrie :** `disableTelemetry` sur `<Admin>`, sinon react-admin signale à son éditeur où il tourne.

## Configuration

- `VITE_API_URL` (`admin/.env`) : l'adresse de l'API, lue **à la compilation**.
  - Le build de production doit la définir : `VITE_API_URL=https://api.example.fr npm run build`.
  - Une surcharge locale va dans `admin/.env.local`, non versionné.
  - Ces fichiers ne contiennent aucun secret : tout ce qui est compilé dans le back-office est public.
- Le serveur de dev écoute sur `localhost:5173` exactement (`strictPort`) : c'est l'origine que l'API autorise (`CORS_ADMIN_ORIGIN`). Si le port est pris, Vite s'arrête au lieu d'en changer.

## Tests

- Vitest, en environnement jsdom (`localStorage`, `Response`). `npm test`, ou `npm run check` pour tout ce que vérifie la CI (lint, types, tests).
- `fakeApi()` remplace `fetch` : chaque route « MÉTHODE /chemin » répond, chaque appel est enregistré, toute autre requête fait échouer le test.
- La session vit dans la mémoire des modules : chaque test les recharge (`vi.resetModules()`, puis `await import(...)`).
- `App.test.tsx` rend l'application entière (Testing Library) : page de connexion sans session, tableau de bord avec une session, connexion par le formulaire. C'est lui qui vérifie ce que l'utilisateur voit ; les autres tests ne couvrent que la logique.

## Pièges connus

- TypeScript 5.9, et non 7 (que propose le modèle Vite) : `openapi-typescript` utilise l'API JavaScript du compilateur, que TypeScript 7 n'a pas encore. Passer à 7 quand il le permettra.
- Le lint est **oxlint**, pas ESLint : c'est celui du modèle Vite actuel, et il ne dépend pas du compilateur TypeScript.
- `<Admin>` n'a pas encore de `dataProvider` : les écrans des ressources arrivent avec P1-10.
- Sans aucune `<Resource>` ni `<CustomRoutes>`, react-admin affiche sa page « Welcome to React-admin » à la place de la connexion et du tableau de bord : il teste l'application « vide » avant même l'authentification. Les ressources `products` et `users` sont donc déclarées dès maintenant, sans écran ; une ressource sans liste n'apparaît pas dans le menu.
- Le `package-lock.json` est au format 3 : il faut npm 9 ou plus.
