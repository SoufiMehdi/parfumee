# Boutique Parfumée

Application Next.js, rendue par le serveur, qui consomme l'API de `../api`.

## Lancer

```bash
npm install
npm run dev        # http://localhost:3000
```

L'API doit répondre à l'adresse de `API_URL` (`.env.development` : `http://127.0.0.1:8000`).

## Vérifier

```bash
npm run check      # lint, types et tests : ce que lance la CI
npm run build      # la compilation de production (sans besoin de l'API)
```

L'installation complète est dans le README à la racine ; les règles de travail dans `CLAUDE.md` et `AGENTS.md`.
