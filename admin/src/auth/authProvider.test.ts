import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { callsTo, fakeApi } from '../test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const REFRESH = 'POST /api/v1/token/refresh';
const REVOKE = 'POST /api/v1/token/revoke';
const ME = 'GET /api/v1/admin/me';

const EDITOR = {
    id: 'u-1',
    email: 'grace@example.test',
    first_name: 'Grace',
    last_name: 'Hopper',
    phone_number: null,
    roles: ['ROLE_USER', 'ROLE_EDITOR'],
    addresses: [],
    created_at: '2026-11-01T09:30:00+00:00',
    updated_at: '2026-11-01T09:30:00+00:00',
};
const SESSION = { token: 'jwt-1', refresh_token: 'refresh-1' };

let authModule: typeof import('./authProvider');
let session: typeof import('./session');

beforeEach(async () => {
    localStorage.clear();
    vi.resetModules();
    session = await import('./session');
    authModule = await import('./authProvider');
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function signIn() {
    return authModule.authProvider.login({ email: 'grace@example.test', password: 'a-password' });
}

describe('login', () => {
    it('signs in, then checks that the account may use the back-office', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: SESSION }, [ME]: { body: EDITOR } });

        await signIn();

        expect(session.hasSession()).toBe(true);
        expect(callsTo(calls, ME)).toHaveLength(1);
    });

    it('refuses an account without the editor or administrator role, and signs it out', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: SESSION }, [ME]: { status: 403, body: { error: 'Access denied.' } }, [REVOKE]: { status: 204 } });

        await expect(signIn()).rejects.toThrow(authModule.NO_ACCESS_MESSAGE);

        expect(callsTo(calls, REVOKE)[0].body).toEqual({ refresh_token: 'refresh-1' });
        expect(session.hasSession()).toBe(false);
    });

    it('passes on why the sign-in failed', async () => {
        fakeApi({ [SIGN_IN]: { status: 401, body: { error: 'Invalid credentials.' } } });

        await expect(signIn()).rejects.toThrow('Email ou mot de passe incorrect.');
    });
});

describe('identity and permissions', () => {
    it('come from the account of the session, read once', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: SESSION }, [ME]: { body: EDITOR } });
        await signIn();

        expect(await authModule.authProvider.getIdentity()).toEqual({ id: 'u-1', fullName: 'Grace Hopper' });
        expect(await authModule.authProvider.getPermissions()).toEqual(['ROLE_USER', 'ROLE_EDITOR']);
        expect(callsTo(calls, ME)).toHaveLength(1);
    });
});

describe('checkAuth', () => {
    it('lets a signed-in session through without asking the API', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: SESSION }, [ME]: { body: EDITOR } });
        await signIn();
        const before = calls.length;

        await authModule.authProvider.checkAuth();

        expect(calls).toHaveLength(before);
    });

    it('sends to the sign-in page, without an error message, when nobody signed in here', async () => {
        fakeApi({});

        await expect(authModule.authProvider.checkAuth()).rejects.toEqual({ message: false });
    });

    it('renews the session after a reload', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        const { calls } = fakeApi({ [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } } });

        await authModule.authProvider.checkAuth();

        expect(callsTo(calls, REFRESH)).toHaveLength(1);
        expect(session.getAccessToken()).toBe('jwt-2');
    });

    it('says the session is over when the API refuses the refresh token', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-expired');
        fakeApi({ [REFRESH]: { status: 401, body: { error: 'The refresh token is invalid.' } } });

        await expect(authModule.authProvider.checkAuth()).rejects.toEqual({ message: 'ra.notification.logged_out' });
    });

    it('keeps the session when the API cannot answer: the screens show the error', async () => {
        localStorage.setItem(session.REFRESH_TOKEN_KEY, 'refresh-1');
        fakeApi({ [REFRESH]: { status: 503 } });

        await authModule.authProvider.checkAuth();

        expect(localStorage.getItem(session.REFRESH_TOKEN_KEY)).toBe('refresh-1');
    });
});

describe('checkError', () => {
    it('signs out after a 401: the session could not be renewed', async () => {
        await expect(authModule.authProvider.checkError({ status: 401 })).rejects.toEqual({ message: 'ra.notification.logged_out' });
    });

    it('stays signed in after a 403: only a role is missing', async () => {
        await expect(authModule.authProvider.checkError({ status: 403 })).rejects.toEqual({ logoutUser: false, message: 'ra.notification.not_authorized' });
    });

    it.each([{ status: 404 }, { status: 500 }, new TypeError('Failed to fetch')])('lets the other errors be shown as they are (%o)', async error => {
        await expect(authModule.authProvider.checkError(error)).resolves.toBeUndefined();
    });
});

describe('logout', () => {
    it('revokes the refresh token', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: SESSION }, [ME]: { body: EDITOR }, [REVOKE]: { status: 204 } });
        await signIn();

        await authModule.authProvider.logout();

        expect(callsTo(calls, REVOKE)).toHaveLength(1);
        expect(session.hasSession()).toBe(false);
    });
});
