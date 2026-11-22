import createClient from 'openapi-fetch';

import type { paths } from './schema';

/**
 * Where the API answers, for the server of the shop (API_URL), without a trailing slash.
 * Read when a page is rendered, not when it is built: the same build runs everywhere.
 */
export function apiBaseUrl(): string {
    const url = process.env.API_URL;
    if (!url) {
        throw new Error('API_URL is not set: the shop does not know where the API answers.');
    }

    return url.replace(/\/+$/, '');
}

/**
 * The client of the API, typed from api/openapi.json (src/api/schema.ts, generated): a
 * route, a parameter or a field the contract does not have does not compile.
 *
 * The pages call it from the server: these calls send no Origin and need no CORS.
 */
export function apiClient() {
    return createClient<paths>({ baseUrl: apiBaseUrl() });
}
