import { HttpError, type AuthProvider } from 'react-admin';

import type { components } from '../api/schema';
import { apiUrl } from '../config';
import { httpClient } from './httpClient';
import { getAccessToken, hasSession, renewSession, signIn, signOut } from './session';

type Account = components['schemas']['UserView'];

export const NO_ACCESS_MESSAGE = 'Ce compte n’a pas accès au back-office.';

// The account of the session, read once from /admin/me: identity and roles.
let account: Promise<Account> | null = null;

function currentAccount(): Promise<Account> {
    account ??= httpClient(apiUrl('/admin/me')).then(
        ({ json }) => json as Account,
        (error: unknown) => {
            account = null;
            throw error;
        },
    );

    return account;
}

export const authProvider = {
    async login({ email, password }: { email: string; password: string }) {
        account = null;
        await signIn(email, password);

        try {
            await currentAccount();
        } catch (error) {
            // Any account can sign in to the API; the routes of the back-office then refuse
            // one without the editor or administrator role (403).
            await signOut();
            account = null;
            throw error instanceof HttpError && 403 === error.status ? new Error(NO_ACCESS_MESSAGE) : error;
        }
    },

    async logout() {
        account = null;
        await signOut();
    },

    async checkAuth() {
        if (null !== getAccessToken()) {
            return;
        }
        if (!hasSession()) {
            // Never signed in here: off to the sign-in page, without an error message.
            throw { message: false };
        }

        try {
            if (null !== (await renewSession())) {
                return;
            }
        } catch {
            // The API cannot answer: keep the session, the screens show the error.
            return;
        }

        throw { message: 'ra.notification.logged_out' };
    },

    async checkError(error: unknown) {
        // Any error reaches here, a network failure included: only an HTTP error has a status.
        const status = typeof error === 'object' && null !== error && 'status' in error ? error.status : undefined;

        if (401 === status) {
            // httpClient has already tried to renew the session: it is over.
            account = null;
            throw { message: 'ra.notification.logged_out' };
        }
        if (403 === status) {
            // A role is missing (an editor on a page for administrators): stay signed in.
            throw { logoutUser: false, message: 'ra.notification.not_authorized' };
        }
    },

    async getIdentity() {
        const { id, email, first_name, last_name } = await currentAccount();

        return { id, fullName: `${first_name} ${last_name}`.trim() || email };
    },

    async getPermissions() {
        return (await currentAccount()).roles;
    },
} satisfies AuthProvider;
