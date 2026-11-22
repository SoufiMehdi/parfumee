import { fetchUtils, HttpError } from 'react-admin';

import { getAccessToken, renewSession } from './session';

type Reply = Awaited<ReturnType<typeof fetchUtils.fetchJson>>;

/**
 * fetchUtils.fetchJson, with the JWT of the session. Every call to the API goes through
 * it, the data provider's included (P1-10): never call fetch directly.
 *
 * Once the JWT has expired (15 minutes), the session is renewed and the request sent
 * again, once. If the session cannot be renewed, the 401 goes up to the authProvider
 * (checkError), which signs the user out.
 */
export async function httpClient(url: string, options: fetchUtils.Options = {}): Promise<Reply> {
    const token = getAccessToken() ?? (await renewSession());

    try {
        return await send(url, options, token);
    } catch (error) {
        if (!(error instanceof HttpError) || 401 !== error.status || null === token) {
            throw error;
        }

        // Another request may have renewed the session meanwhile: use its JWT rather
        // than renew again.
        const current = getAccessToken();
        const renewed = null !== current && current !== token ? current : await renewSession();
        if (null === renewed) {
            throw error;
        }

        return send(url, options, renewed);
    }
}

async function send(url: string, options: fetchUtils.Options, token: string | null): Promise<Reply> {
    try {
        return await fetchUtils.fetchJson(url, null === token ? options : { ...options, user: { authenticated: true, token: `Bearer ${token}` } });
    } catch (error) {
        // The API writes its errors as {"error": "..."}: show that message, not the HTTP status text.
        if (error instanceof HttpError && typeof error.body?.error === 'string') {
            throw new HttpError(error.body.error, error.status, error.body);
        }

        throw error;
    }
}
