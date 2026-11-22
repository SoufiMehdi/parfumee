import { HttpError } from 'react-admin';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { apiUrl } from '../config';
import { callsTo, fakeApi, type Call, type Reply } from '../test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const REFRESH = 'POST /api/v1/token/refresh';
const ME = 'GET /api/v1/admin/me';

let session: typeof import('./session');
let httpClient: (typeof import('./httpClient'))['httpClient'];

beforeEach(async () => {
    localStorage.clear();
    vi.resetModules();
    session = await import('./session');
    ({ httpClient } = await import('./httpClient'));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

/** /admin/me accepts the second JWT only: the first one has expired. */
function acceptsOnly(token: string): (call: Call) => Reply {
    return call => (call.headers.get('Authorization') === `Bearer ${token}` ? { body: { id: 'u-1' } } : { status: 401, body: { error: 'Expired JWT Token' } });
}

describe('httpClient', () => {
    it('sends the JWT of the session', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } }, [ME]: { body: { id: 'u-1' } } });
        await session.signIn('ada@example.test', 'a-password');

        const { json } = await httpClient(apiUrl('/admin/me'));

        expect(json).toEqual({ id: 'u-1' });
        expect(callsTo(calls, ME)[0].headers.get('Authorization')).toBe('Bearer jwt-1');
    });

    it('renews an expired JWT once, and sends the request again', async () => {
        const { calls } = fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } },
            [ME]: acceptsOnly('jwt-2'),
        });
        await session.signIn('ada@example.test', 'a-password');

        const { json } = await httpClient(apiUrl('/admin/me'));

        expect(json).toEqual({ id: 'u-1' });
        expect(callsTo(calls, REFRESH)).toHaveLength(1);
        expect(callsTo(calls, ME).map(call => call.headers.get('Authorization'))).toEqual(['Bearer jwt-1', 'Bearer jwt-2']);
    });

    it('renews the session first after a reload, when only the refresh token is left', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        const { calls } = fakeApi({ [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } }, [ME]: acceptsOnly('jwt-2') });

        await httpClient(apiUrl('/admin/me'));

        expect(callsTo(calls, ME)).toHaveLength(1);
    });

    it('renews once for two requests whose JWT expired together', async () => {
        const { calls } = fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } },
            [ME]: acceptsOnly('jwt-2'),
        });
        await session.signIn('ada@example.test', 'a-password');

        await Promise.all([httpClient(apiUrl('/admin/me')), httpClient(apiUrl('/admin/me'))]);

        expect(callsTo(calls, REFRESH)).toHaveLength(1);
    });

    it('uses the JWT another request just renewed, rather than renew again', async () => {
        let answerTheSlowRequest!: () => void;
        const slowAnswer = new Promise<void>(resolve => {
            answerTheSlowRequest = resolve;
        });
        const { calls } = fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } },
            [ME]: acceptsOnly('jwt-2'),
            // Sent with the first JWT, its 401 arrives once the other request has renewed the session.
            'GET /api/v1/admin/products': async call => {
                if (call.headers.get('Authorization') === 'Bearer jwt-2') {
                    return { body: [] };
                }
                await slowAnswer;

                return { status: 401, body: { error: 'Expired JWT Token' } };
            },
        });
        await session.signIn('ada@example.test', 'a-password');

        const slowRequest = httpClient(apiUrl('/admin/products'));
        await httpClient(apiUrl('/admin/me'));
        answerTheSlowRequest();
        await slowRequest;

        expect(callsTo(calls, REFRESH)).toHaveLength(1);
    });

    it('lets the 401 through when the session cannot be renewed', async () => {
        fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            [REFRESH]: { status: 401, body: { error: 'The refresh token is invalid.' } },
            [ME]: { status: 401, body: { error: 'Expired JWT Token' } },
        });
        await session.signIn('ada@example.test', 'a-password');

        await expect(httpClient(apiUrl('/admin/me'))).rejects.toMatchObject({ status: 401 });
        expect(session.hasSession()).toBe(false);
    });

    it('shows the message the API wrote, not the HTTP status text', async () => {
        fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            'GET /api/v1/admin/products/p-1': { status: 404, body: { error: 'Product with id "p-1" not found.' } },
        });
        await session.signIn('ada@example.test', 'a-password');

        const error = await httpClient(apiUrl('/admin/products/p-1')).catch((caught: unknown) => caught);

        expect(error).toBeInstanceOf(HttpError);
        expect(error).toMatchObject({ status: 404, message: 'Product with id "p-1" not found.' });
    });
});
