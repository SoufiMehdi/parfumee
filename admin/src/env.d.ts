/// <reference types="vite/client" />

interface ImportMetaEnv {
    /** Where the API answers, e.g. http://127.0.0.1:8000 (see admin/.env). */
    readonly VITE_API_URL: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
