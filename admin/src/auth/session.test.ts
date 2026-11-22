import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { callsTo, fakeApi } from '../test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const REFRESH = 'POST /api/v1/token/refresh';
const REVOKE = 'POST /api/v1/token/revoke';

let session: typeof import('./session');

beforeEach(async () => {
    localStorage.clear();
    // A fresh module for each test: the JWT is kept in its memory.
    vi.resetModules();
    session = await import('./session');
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('signIn', () => {
    it('keeps the JWT in memory and the refresh token in localStorage', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } } });

        await session.signIn('ada@example.test', 'a-password');

        expect(callsTo(calls, SIGN_IN)[0].body).toEqual({ email: 'ada@example.test', password: 'a-password' });
        expect(session.getAccessToken()).toBe('jwt-1');
        expect(localStorage.getItem(session.REFRESH_TOKEN_KEY)).toBe('refresh-1');
    });

    it('says when the email or the password is wrong, and keeps nothing', async () => {
        fakeApi({ [SIGN_IN]: { status: 401, body: { error: 'Invalid credentials.' } } });

        await expect(session.signIn('ada@example.test', 'wrong')).rejects.toThrow('Email ou mot de passe incorrect.');
        expect(session.hasSession()).toBe(false);
    });

    it.each([
        ['120', 'Trop de tentatives. Réessayez dans 2 minutes.'],
        ['30', 'Trop de tentatives. Réessayez dans 1 minute.'],
    ])('tells how long to wait after too many attempts (Retry-After: %s)', async (retryAfter, message) => {
        fakeApi({ [SIGN_IN]: { status: 429, body: { error: 'Too many sign-in attempts. Try again later.' }, headers: { 'Retry-After': retryAfter } } });

        await expect(session.signIn('ada@example.test', 'wrong')).rejects.toThrow(message);
    });
});

describe('renewSession', () => {
    it('renews only once for callers at the same time: the refresh token works once', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        const { calls } = fakeApi({ [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } } });

        const tokens = await Promise.all([session.renewSession(), session.renewSession(), session.renewSession()]);

        expect(tokens).toEqual(['jwt-2', 'jwt-2', 'jwt-2']);
        expect(callsTo(calls, REFRESH)).toHaveLength(1);
        expect(callsTo(calls, REFRESH)[0].body).toEqual({ refresh_token: 'refresh-1' });
        expect(localStorage.getItem(session.REFRESH_TOKEN_KEY)).toBe('refresh-2');
    });

    it('uses the refresh token another tab left, once it holds the lock', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        // While this tab waits for the lock, another one renews the session.
        const request = vi.fn(async (_name: string, task: () => Promise<unknown>) => {
            localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-left-by-the-other-tab');

            return task();
        });
        vi.stubGlobal('navigator', { locks: { request } });
        const { calls } = fakeApi({ [REFRESH]: { body: { token: 'jwt-3', refresh_token: 'refresh-3' } } });

        expect(await session.renewSession()).toBe('jwt-3');

        expect(request).toHaveBeenCalledWith(session.RENEWAL_LOCK, expect.any(Function));
        expect(callsTo(calls, REFRESH)[0].body).toEqual({ refresh_token: 'refresh-left-by-the-other-tab' });
    });

    it('ends the session when the API refuses the refresh token', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-expired');
        fakeApi({ [REFRESH]: { status: 401, body: { error: 'The refresh token is invalid.' } } });

        expect(await session.renewSession()).toBeNull();
        expect(session.hasSession()).toBe(false);
    });

    it('keeps the session when the API cannot answer', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        fakeApi({ [REFRESH]: { status: 503 } });

        await expect(session.renewSession()).rejects.toThrow('La session n’a pas pu être renouvelée');
        expect(localStorage.getItem(session.REFRESH_TOKEN_KEY)).toBe('refresh-1');
    });

    it('has nothing to renew without a refresh token', async () => {
        const { calls } = fakeApi({});

        expect(await session.renewSession()).toBeNull();
        expect(calls).toHaveLength(0);
    });
});

describe('signOut', () => {
    it('revokes the refresh token and forgets both tokens', async () => {
        const { calls } = fakeApi({
            [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } },
            [REVOKE]: { status: 204 },
        });
        await session.signIn('ada@example.test', 'a-password');

        await session.signOut();

        expect(callsTo(calls, REVOKE)[0].body).toEqual({ refresh_token: 'refresh-1' });
        expect(session.getAccessToken()).toBeNull();
        expect(session.hasSession()).toBe(false);
    });

    it('forgets the tokens even when the API cannot be reached', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        await session.signOut();

        expect(session.hasSession()).toBe(false);
    });
});
