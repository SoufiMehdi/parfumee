import type { components } from '../api/schema';
import { apiUrl } from '../config';

type Session = components['schemas']['SessionView'];

/** Where the refresh token survives a reload. The tabs of the back-office share it. */
export const REFRESH_TOKEN_KEY = 'parfumee.admin.refresh_token';

/** Name of the lock that keeps two tabs from renewing the session at the same time. */
export const RENEWAL_LOCK = 'parfumee.admin.session_renewal';

export class SignInError extends Error {}

// The JWT (15 minutes) lives in memory only: after a reload, the refresh token renews it.
let accessToken: string | null = null;
let renewal: Promise<string | null> | null = null;

/**
 * Signs in with the route of the back-office. Any account can: whether it may use the
 * back-office is checked next, with /admin/me (see authProvider).
 */
export async function signIn(email: string, password: string): Promise<void> {
    const response = await post('/admin/login', { email, password });
    if (!response.ok) {
        throw new SignInError(signInFailure(response));
    }

    keep((await response.json()) as Session);
}

export function getAccessToken(): string | null {
    return accessToken;
}

/**
 * Whether there is a session to use or to renew, without asking the API.
 */
export function hasSession(): boolean {
    return accessToken !== null || readRefreshToken() !== null;
}

/**
 * Exchanges the refresh token for a new session, and returns the new JWT; null once the
 * session is over (no refresh token, or the API refused it: expired, revoked or used).
 * Throws if the API cannot answer: the session is kept, to be renewed later.
 *
 * A refresh token works once, and a second renewal with it would be refused, signing the
 * user out. So there is one renewal at a time: the callers in this tab share it, and the
 * other tabs wait for the lock, then read the refresh token the previous renewal left.
 */
export function renewSession(): Promise<string | null> {
    renewal ??= withRenewalLock(async () => {
        const refreshToken = readRefreshToken();
        if (refreshToken === null) {
            accessToken = null;

            return null;
        }

        const response = await post('/token/refresh', { refresh_token: refreshToken });
        if (401 === response.status) {
            forget();

            return null;
        }
        if (!response.ok) {
            throw new Error('La session n’a pas pu être renouvelée. Réessayez dans un instant.');
        }

        const session = (await response.json()) as Session;
        keep(session);

        return session.token;
    }).finally(() => {
        renewal = null;
    });

    return renewal;
}

/**
 * Ends the session: the API revokes the refresh token, and this tab forgets both tokens.
 * They are forgotten even when the API cannot be reached: signing out always works.
 */
export async function signOut(): Promise<void> {
    // Under the lock: a renewal in progress would otherwise store a new refresh token afterwards.
    const refreshToken = await withRenewalLock(async () => {
        const current = readRefreshToken();
        forget();

        return current;
    });

    if (refreshToken !== null) {
        try {
            await post('/token/revoke', { refresh_token: refreshToken });
        } catch {
            // The API cannot be reached: the refresh token will expire on its own (30 days).
        }
    }
}

function keep(session: Session): void {
    accessToken = session.token;
    localStorage.setItem(REFRESH_TOKEN_KEY, session.refresh_token);
}

function forget(): void {
    accessToken = null;
    localStorage.removeItem(REFRESH_TOKEN_KEY);
}

function readRefreshToken(): string | null {
    return localStorage.getItem(REFRESH_TOKEN_KEY);
}

async function withRenewalLock<T>(task: () => Promise<T>): Promise<T> {
    // Every current browser has the Web Locks API. Without it, only this tab is protected.
    if (!('locks' in navigator)) {
        return task();
    }

    let result!: T;
    await navigator.locks.request(RENEWAL_LOCK, async () => {
        result = await task();
    });

    return result;
}

function post(path: string, body: object): Promise<Response> {
    return fetch(apiUrl(path), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
}

function signInFailure(response: Response): string {
    switch (response.status) {
        case 401:
            return 'Email ou mot de passe incorrect.';
        case 422:
            return 'Saisissez votre email et votre mot de passe.';
        case 429: {
            // Retry-After: the number of seconds to wait (rate limits of the API, P1-07).
            const minutes = Math.max(1, Math.ceil(Number(response.headers.get('Retry-After') ?? 0) / 60));

            return `Trop de tentatives. Réessayez dans ${minutes} minute${minutes > 1 ? 's' : ''}.`;
        }
        default:
            return 'La connexion a échoué. Réessayez dans un instant.';
    }
}
