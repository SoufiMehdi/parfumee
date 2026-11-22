# Boutique — règles spécifiques

Next.js 16 (App Router, React 19), TypeScript 5.9. Les pages sont rendues par le serveur, pour le référencement (ADR 0006).

**Lire `AGENTS.md` avant d'écrire du code Next.js** : cette version a des changements incompatibles avec les précédentes (cache, récupération des données, conventions de fichiers). Sa documentation est fournie dans `node_modules/next/dist/docs/`.

## Structure

```
src/
  app/              les routes (App Router) : mise en page, accueil, page 404
  api/client.ts     le client de l'API (openapi-fetch), typé par api/schema.ts
  api/schema.ts     types générés depuis api/openapi.json : ne jamais le modifier à la main
  catalog/          lecture et affichage du catalogue
  test/fakeApi.ts   faux serveur d'API pour les tests
```

## Règles

1. **Appels à l'API** : toujours par `apiClient()` (`src/api/client.ts`), typé par le contrat, jamais par un `fetch` avec une URL écrite à la main. Une route, un paramètre ou un champ absent du contrat ne compile pas.
2. **Depuis le serveur** : les pages appellent l'API depuis le serveur Next.js (`API_URL`), sans passer par le CORS. Les futurs appels depuis le navigateur (panier, compte) passeront par une variable `NEXT_PUBLIC_…`, depuis l'origine autorisée par l'API (`http://localhost:3000` en dev).
3. **Données lues à chaque requête** (le modèle de cache de Next.js 16, `cacheComponents`) :
   - un composant asynchrone, rendu dans un `<Suspense>`, qui appelle `await connection()` **avant** l'API, et **en dehors** de tout `try` ;
   - sans `connection()`, Next.js lance l'appel pendant la compilation puis l'interrompt : une erreur dans le journal de compilation, attrapée comme une panne de l'API. Un test le vérifie (`LatestProducts.test.tsx`) ;
   - une donnée mise en cache (`'use cache'` et `cacheLife`) serait calculée à la compilation, donc avec l'API joignable à ce moment-là, ce que la CI n'a pas. À trancher avec les pages du catalogue (P2-11).
4. **API indisponible** : la page affiche un message en français et l'erreur part dans le journal du serveur. Jamais une page d'erreur pour une panne de l'API.
5. **Types de l'API générés** : `npm run api:types` après tout changement du contrat (`api/openapi.json`). La CI échoue si `src/api/schema.ts` n'est plus à jour.
6. **Langue** : le contenu est en français (`lang="fr"`), avec les prix au format français (`formatPrice`) ; le code, les noms et les commentaires sont en anglais, comme dans l'API.

## Configuration

- `API_URL` : l'adresse de l'API pour le serveur de la boutique, lue à chaque requête et non à la compilation. Une même compilation sert donc partout.
  - En dev : `shop/.env.development`, seul fichier d'environnement versionné, sans secret. Une surcharge locale va dans `shop/.env.local`.
  - En production : l'environnement du conteneur (P7-01).
- Le serveur de dev écoute sur le port 3000 exactement (`--port 3000`) : s'il est pris, Next.js s'arrête au lieu d'en changer.
- La télémétrie de Next.js est coupée (`NEXT_TELEMETRY_DISABLED=1` dans les scripts et dans la CI).
- Pas d'en-tête `X-Powered-By` (`poweredByHeader: false`).

## Tests

- Vitest et Testing Library, en environnement jsdom. `npm test`, ou `npm run check` pour tout ce que vérifie la CI (lint, types, tests).
- Vitest ne sait pas rendre un composant serveur asynchrone : le test l'attend (`await LatestProducts()`), puis rend son résultat. `next/server` est simulé (`connection`).
- `fakeApi()` remplace `fetch` : chaque route « MÉTHODE /chemin » répond, toute autre requête fait échouer le test.
- Pas encore de tests dans un vrai navigateur : Playwright arrive avec P7-06.

## Pièges connus

- TypeScript 5.9, et non 7 : `openapi-typescript` ne fonctionne pas encore avec TypeScript 7.
- `npm run typecheck` lance `next typegen` avant `tsc` : les types des routes (`LayoutProps`, `PageProps`) sont générés par Next.js, absents avant toute compilation.
- La sortie `standalone`, pour l'image Docker, sera activée avec P7-01 : `next start` ne sait pas la lancer.
- Testing Library replie les espaces du texte, l'espace insécable d'un prix compris : la valeur attendue se compare de la même façon (`asShown` dans les tests).
- `AGENTS.md` est réécrit par `next dev` : le garder versionné, sinon il réapparaît à chaque lancement.
- Pas de framework CSS ni de police web : l'apparence de la boutique reste à concevoir (P2-11).
