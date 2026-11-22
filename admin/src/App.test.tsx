import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { callsTo, fakeApi } from './test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const REFRESH = 'POST /api/v1/token/refresh';
const ME = 'GET /api/v1/admin/me';
const PRODUCTS = 'GET /api/v1/admin/products';
const USERS = 'GET /api/v1/admin/users';

const ADMIN = {
    id: 'u-1',
    email: 'ada@example.test',
    first_name: 'Ada',
    last_name: 'Admin',
    phone_number: null,
    roles: ['ROLE_ADMIN', 'ROLE_USER'],
    addresses: [],
    created_at: '2026-11-01T09:30:00+00:00',
    updated_at: '2026-11-01T09:30:00+00:00',
};

const EDITOR = { ...ADMIN, id: 'u-2', email: 'grace@example.test', first_name: 'Grace', last_name: 'Hopper', roles: ['ROLE_EDITOR', 'ROLE_USER'] };

const onePage = (items: object[]) => ({ body: { items, pagination: { page: 1, per_page: 25, total: items.length, total_pages: 1 } } });

let App: (typeof import('./App'))['App'];

/** A session left by a previous visit, for this account, and the API that answers it. */
function withSession(account: object, routes: Parameters<typeof fakeApi>[0] = {}) {
    localStorage.setItem('parfumee.admin.refresh_token', 'refresh-1');

    return fakeApi({ [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } }, [ME]: { body: account }, ...routes });
}

beforeEach(async () => {
    localStorage.clear();
    // react-admin routes with the hash of the address (/#/products): each test starts at the root.
    window.history.replaceState({}, '', '/');
    vi.resetModules();
    ({ App } = await import('./App'));
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('App', () => {
    it('opens on the sign-in page when nobody is signed in', async () => {
        fakeApi({});

        render(<App />);

        expect(await screen.findByLabelText(/Email/)).toBeTruthy();
        // The page react-admin shows when it is given no resource.
        expect(screen.queryByText(/Welcome to React-admin/i)).toBeNull();
    });

    it('shows the name and the role of the account of the session', async () => {
        localStorage.setItem('parfumee.admin.refresh_token', 'refresh-1');
        fakeApi({ [REFRESH]: { body: { token: 'jwt-2', refresh_token: 'refresh-2' } }, [ME]: { body: ADMIN } });

        render(<App />);

        expect(await screen.findByText('Bonjour Ada Admin')).toBeTruthy();
        expect(screen.getByText('Rôle : administrateur.')).toBeTruthy();
    });

    it('gives an administrator the products and the accounts', async () => {
        withSession(ADMIN);

        render(<App />);

        expect(await screen.findByRole('menuitem', { name: /Produits/ })).toBeTruthy();
        expect(screen.getByRole('menuitem', { name: /Comptes/ })).toBeTruthy();
    });

    it('gives an editor the products only: the accounts are for the administrators', async () => {
        withSession(EDITOR);

        render(<App />);

        expect(await screen.findByRole('menuitem', { name: /Produits/ })).toBeTruthy();
        expect(screen.queryByRole('menuitem', { name: /Comptes/ })).toBeNull();
    });

    it('lists the products of the API, newest first', async () => {
        window.history.replaceState({}, '', '/#/products');
        const product = {
            id: 'p-1',
            name: 'Bougie Ambre',
            slug: 'bougie-ambre',
            price: 24.9,
            category: { id: 'c-1', name: 'Bougies' },
            attributes: { weight: 180, fragrance: 'ambre', size: 'M', description: null, presentation: null },
            status: 'draft',
            pictures: [],
            created_at: '2026-11-21T10:00:00+00:00',
            updated_at: '2026-11-21T10:00:00+00:00',
        };
        const { calls } = withSession(ADMIN, { [PRODUCTS]: onePage([product]) });

        render(<App />);

        expect(await screen.findByText('Bougie Ambre')).toBeTruthy();
        expect(screen.getByText('Bougies')).toBeTruthy();
        expect(screen.getByText('Brouillon')).toBeTruthy();
        expect(Object.fromEntries(callsTo(calls, PRODUCTS)[0].query)).toEqual({ page: '1', per_page: '25', sort: 'created_at', order: 'desc' });
    });

    it('asks the API for the archived products when the filter says so', async () => {
        // The filter as react-admin keeps it in the address, once chosen in the list.
        window.history.replaceState({}, '', `/#/products?filter=${encodeURIComponent(JSON.stringify({ status: 'archived' }))}`);
        const { calls } = withSession(ADMIN, { [PRODUCTS]: onePage([]) });

        render(<App />);

        expect(await screen.findByText('Archivé')).toBeTruthy();
        expect(Object.fromEntries(callsTo(calls, PRODUCTS)[0].query)).toMatchObject({ status: 'archived' });
    });

    it('lists the accounts, with their role, for an administrator', async () => {
        window.history.replaceState({}, '', '/#/users');
        withSession(ADMIN, { [USERS]: onePage([EDITOR]) });

        render(<App />);

        expect(await screen.findByText('grace@example.test')).toBeTruthy();
        expect(screen.getByText('éditeur')).toBeTruthy();
    });

    it('signs in from the sign-in page', async () => {
        const { calls } = fakeApi({ [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } }, [ME]: { body: ADMIN } });
        const user = userEvent.setup();
        render(<App />);

        await user.type(await screen.findByLabelText(/Email/), 'ada@example.test');
        await user.type(screen.getByLabelText(/Mot de passe/), 'a-password');
        await user.click(screen.getByRole('button', { name: /connexion/i }));

        expect(await screen.findByText('Bonjour Ada Admin')).toBeTruthy();
        expect(callsTo(calls, SIGN_IN)[0].body).toEqual({ email: 'ada@example.test', password: 'a-password' });
    });
});
