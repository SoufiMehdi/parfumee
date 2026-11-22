# Back-office Parfumée

Application React-admin (Vite, TypeScript) qui consomme l'API de `../api`.

## Lancer

```bash
npm install
npm run dev        # http://localhost:5173
```

L'API doit répondre à l'adresse de `VITE_API_URL` (`.env` : `http://127.0.0.1:8000`), et le compte doit être éditeur ou administrateur (`php bin/console app:user:create-admin`, depuis `api/`).

## Vérifier

```bash
npm run check      # lint, types et tests : ce que lance la CI
npm run build      # le site statique, dans dist/
```

L'installation complète est dans le README à la racine ; les règles de travail dans `CLAUDE.md`.
