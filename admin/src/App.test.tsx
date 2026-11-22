import { cleanup, configure, render, screen, waitFor } from '@testing-library/react';
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

// The whole application is rendered: the first render loads react-admin and Material UI,
// which takes more than the default second on a CI runner (3.3 s seen). findBy… and waitFor
// wait up to 5 s; a test that passes still ends as soon as what it waits for shows.
configure({ asyncUtilTimeout: 5000 });

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

    describe('product screens', () => {
        const CATEGORIES = 'GET /api/v1/admin/categories';
        const CANDLES = { id: 'c-1', name: 'Bougies', slug: 'bougies', parent: null, created_at: '2026-11-01T09:30:00+00:00', updated_at: '2026-11-01T09:30:00+00:00' };
        const AMBER = {
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
        /** The routes the edition of AMBER reads: the product, and its category for the form. */
        const editRoutes = { 'GET /api/v1/admin/products/p-1': { body: AMBER }, 'GET /api/v1/admin/categories/c-1': { body: CANDLES }, [CATEGORIES]: onePage([CANDLES]) };

        it('creates a product, a draft unless told otherwise, then opens it', async () => {
            window.history.replaceState({}, '', '/#/products/create');
            // editRoutes: the categories to choose from, then the edition the creation opens.
            const { calls } = withSession(EDITOR, { 'POST /api/v1/admin/products': { status: 201, body: { id: 'p-1' } }, ...editRoutes });
            const user = userEvent.setup();
            render(<App />);

            await user.type(await screen.findByLabelText(/^Nom/), 'Bougie Ambre');
            await user.type(screen.getByLabelText(/^Prix/), '24.9');
            await user.type(screen.getByLabelText(/^Catégorie/), 'Bou');
            await user.click(await screen.findByRole('option', { name: 'Bougies' }));
            await user.type(screen.getByLabelText(/^Parfum/), 'ambre');
            await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

            await waitFor(() => expect(callsTo(calls, 'POST /api/v1/admin/products')).toHaveLength(1));
            expect(callsTo(calls, 'POST /api/v1/admin/products')[0].body).toEqual({
                name: 'Bougie Ambre',
                price: 24.9,
                category_id: 'c-1',
                attributes: { fragrance: 'ambre' },
                status: 'draft',
            });
            // Then its edition opens, where the pictures are added.
            await waitFor(() => expect(window.location.hash).toBe('#/products/p-1'));
            expect(await screen.findByText('Aucune image pour l’instant.', undefined, { timeout: 3000 })).toBeTruthy();
        });

        it('opens a product from the list', async () => {
            window.history.replaceState({}, '', '/#/products');
            withSession(EDITOR, { [PRODUCTS]: onePage([AMBER]), ...editRoutes });
            const user = userEvent.setup();
            render(<App />);

            await user.click(await screen.findByText('Bougie Ambre'));

            expect(await screen.findByDisplayValue('Bougie Ambre')).toBeTruthy();
            expect(window.location.hash).toBe('#/products/p-1');
        });

        it('saves a product as a whole, and shows it as the API stored it', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            const stored = { ...AMBER, price: 26.5, status: 'published', updated_at: '2026-11-22T10:00:00+00:00' };
            const { calls } = withSession(EDITOR, { ...editRoutes, 'PUT /api/v1/admin/products/p-1': { body: stored } });
            const user = userEvent.setup();
            render(<App />);

            const price = await screen.findByDisplayValue('24.9');
            await user.clear(price);
            await user.type(price, '26.5');
            await user.click(screen.getByRole('combobox', { name: /Statut/ }));
            await user.click(await screen.findByRole('option', { name: 'Publié' }));
            await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

            await waitFor(() => expect(callsTo(calls, 'PUT /api/v1/admin/products/p-1')).toHaveLength(1));
            // Only the fields of the API: no id, slug, pictures nor dates.
            expect(callsTo(calls, 'PUT /api/v1/admin/products/p-1')[0].body).toEqual({
                name: 'Bougie Ambre',
                price: 26.5,
                category_id: 'c-1',
                attributes: { weight: 180, fragrance: 'ambre', size: 'M' },
                status: 'published',
            });
        });

        it('shows the errors of the API under the fields', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            withSession(EDITOR, {
                ...editRoutes,
                'PUT /api/v1/admin/products/p-1': {
                    status: 422,
                    body: { error: 'Validation failed.', violations: [{ field: 'attributes[size]', message: 'This value is too long. It should have 255 characters or less.' }] },
                },
            });
            const user = userEvent.setup();
            render(<App />);

            await user.type(await screen.findByDisplayValue('M'), 'L');
            await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

            expect(await screen.findByText('This value is too long. It should have 255 characters or less.')).toBeTruthy();
            // Still on the form, with what the editor typed.
            expect(screen.getByDisplayValue('Bougie Ambre')).toBeTruthy();
        });

        it('archives a product once the editor confirms', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            const { calls } = withSession(EDITOR, { ...editRoutes, 'DELETE /api/v1/admin/products/p-1': { status: 204 }, [PRODUCTS]: onePage([]) });
            const user = userEvent.setup();
            render(<App />);

            await user.click(await screen.findByRole('button', { name: 'Archiver' }));
            expect(await screen.findByText('Archiver ce produit ?')).toBeTruthy();
            expect(callsTo(calls, 'DELETE /api/v1/admin/products/p-1')).toHaveLength(0);
            await user.click(screen.getByRole('button', { name: 'Confirmer' }));

            await waitFor(() => expect(callsTo(calls, 'DELETE /api/v1/admin/products/p-1')).toHaveLength(1));
            expect(await screen.findByText('Produit archivé')).toBeTruthy();
        });

        it('offers no archiving for an archived product: it is republished with its status', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            withSession(EDITOR, { ...editRoutes, 'GET /api/v1/admin/products/p-1': { body: { ...AMBER, status: 'archived' } } });
            render(<App />);

            expect(await screen.findByDisplayValue('Bougie Ambre')).toBeTruthy();
            expect(screen.queryByRole('button', { name: 'Archiver' })).toBeNull();
        });

        it('adds a picture to a product, then shows it', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            const withPicture = { ...AMBER, pictures: [{ id: 'img-1', url: 'products/ambre.png', alt: 'Une bougie', sort_order: null }] };
            // The product has its picture once it is sent, not before: a mere second read shows nothing.
            const { calls } = withSession(EDITOR, {
                ...editRoutes,
                'GET /api/v1/admin/products/p-1': () => ({ body: 0 < callsTo(calls, 'POST /api/v1/admin/products/p-1/pictures').length ? withPicture : AMBER }),
                'POST /api/v1/admin/products/p-1/pictures': { status: 201, body: { id: 'img-1' } },
            });
            const user = userEvent.setup();
            render(<App />);

            await user.upload(await screen.findByLabelText('Fichier de l’image'), new File(['fake'], 'ambre.png', { type: 'image/png' }));
            await user.type(screen.getByLabelText('Texte alternatif'), 'Une bougie');
            await user.click(screen.getByRole('button', { name: 'Envoyer l’image' }));

            const picture = await screen.findByRole('img', { name: 'Une bougie' });
            expect(picture.getAttribute('src')).toMatch(/\/uploads\/products\/ambre\.png$/);
            const form = callsTo(calls, 'POST /api/v1/admin/products/p-1/pictures')[0].body as FormData;
            expect((form.get('file') as File).name).toBe('ambre.png');
            expect(form.get('alt')).toBe('Une bougie');
        });

        it('keeps what the editor typed and has not saved when a picture is added', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            const withPicture = { ...AMBER, pictures: [{ id: 'img-1', url: 'products/ambre.png', alt: null, sort_order: null }] };
            const { calls } = withSession(EDITOR, {
                ...editRoutes,
                'GET /api/v1/admin/products/p-1': () => ({ body: 0 < callsTo(calls, 'POST /api/v1/admin/products/p-1/pictures').length ? withPicture : AMBER }),
                'POST /api/v1/admin/products/p-1/pictures': { status: 201, body: { id: 'img-1' } },
            });
            const user = userEvent.setup();
            render(<App />);

            const price = await screen.findByDisplayValue('24.9');
            await user.clear(price);
            await user.type(price, '26.5');
            await user.upload(screen.getByLabelText('Fichier de l’image'), new File(['fake'], 'ambre.png', { type: 'image/png' }));
            await user.click(screen.getByRole('button', { name: 'Envoyer l’image' }));

            // The product is read again, with its picture.
            await waitFor(() => expect(screen.queryByText('Aucune image pour l’instant.')).toBeNull());
            expect(screen.getByDisplayValue('26.5')).toBeTruthy();
        });

        it('tells why a picture is refused', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            withSession(EDITOR, {
                ...editRoutes,
                'POST /api/v1/admin/products/p-1/pictures': { status: 422, body: { error: 'Validation failed.', violations: [{ field: 'file', message: 'The picture must be a JPEG, PNG or WebP image.' }] } },
            });
            const user = userEvent.setup();
            render(<App />);

            await user.upload(await screen.findByLabelText('Fichier de l’image'), new File(['fake'], 'ambre.png', { type: 'image/png' }));
            await user.click(screen.getByRole('button', { name: 'Envoyer l’image' }));

            expect(await screen.findByText('The picture must be a JPEG, PNG or WebP image.')).toBeTruthy();
        });

        it('sends no picture over 5 MB: the API would refuse it once sent', async () => {
            window.history.replaceState({}, '', '/#/products/p-1');
            const { calls } = withSession(EDITOR, editRoutes);
            const user = userEvent.setup();
            render(<App />);

            await user.upload(await screen.findByLabelText('Fichier de l’image'), new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'lourde.png', { type: 'image/png' }));
            await user.click(screen.getByRole('button', { name: 'Envoyer l’image' }));

            expect(await screen.findByText('L’image pèse plus de 5 Mo : elle ne peut pas être envoyée.')).toBeTruthy();
            expect(callsTo(calls, 'POST /api/v1/admin/products/p-1/pictures')).toHaveLength(0);
        });

        it('filters the list by category', async () => {
            window.history.replaceState({}, '', `/#/products?filter=${encodeURIComponent(JSON.stringify({ category_id: 'c-1' }))}`);
            const { calls } = withSession(EDITOR, { [PRODUCTS]: onePage([AMBER]), [CATEGORIES]: onePage([CANDLES]), 'GET /api/v1/admin/categories/c-1': { body: CANDLES } });
            render(<App />);

            expect(await screen.findByDisplayValue('Bougies')).toBeTruthy();
            expect(Object.fromEntries(callsTo(calls, PRODUCTS)[0].query)).toMatchObject({ category_id: 'c-1' });
        });
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
