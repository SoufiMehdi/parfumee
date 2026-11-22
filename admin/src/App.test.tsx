import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { callsTo, fakeApi } from './test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const REFRESH = 'POST /api/v1/token/refresh';
const ME = 'GET /api/v1/admin/me';

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

let App: (typeof import('./App'))['App'];

beforeEach(async () => {
    localStorage.clear();
    // react-admin routes with the address of the page: each test starts at the root.
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
