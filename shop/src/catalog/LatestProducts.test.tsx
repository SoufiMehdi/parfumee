import { cleanup, render, screen } from '@testing-library/react';
import { connection } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { fakeApi } from '@/test/fakeApi';

import { aPageOf, aProduct } from './fixtures';
import { LatestProducts } from './LatestProducts';

// Outside of Next.js there is no request to wait for.
vi.mock('next/server', () => ({ connection: vi.fn(async () => undefined) }));

const PRODUCTS = 'GET /api/v1/shop/products';

beforeEach(() => {
    vi.stubEnv('API_URL', 'http://api.example.test');
    vi.mocked(connection).mockClear();
});

afterEach(() => {
    cleanup();
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** Vitest cannot render an async Server Component: it is awaited, then its result rendered. */
async function renderLatestProducts() {
    render(await LatestProducts());
}

describe('LatestProducts', () => {
    it('waits for a request before calling the API, so that the build never calls it', async () => {
        fakeApi({ [PRODUCTS]: { body: aPageOf([aProduct()]) } });

        await renderLatestProducts();

        // Without it, Next.js starts the call while prerendering, then aborts it: an
        // error in the build logs, caught here as an unavailable catalogue.
        const waited = vi.mocked(connection).mock.invocationCallOrder.at(-1) ?? Infinity;
        const called = vi.mocked(globalThis.fetch).mock.invocationCallOrder[0];
        expect(waited).toBeLessThan(called);
    });

    it('shows the newest products of the API', async () => {
        fakeApi({ [PRODUCTS]: { body: aPageOf([aProduct(), aProduct({ id: 'p-2', name: 'Bougie Vanille' })]) } });

        await renderLatestProducts();

        expect(screen.getByRole('heading', { name: 'Bougie Ambre' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Bougie Vanille' })).toBeTruthy();
    });

    it('says the catalogue is coming when it is still empty', async () => {
        fakeApi({ [PRODUCTS]: { body: aPageOf([]) } });

        await renderLatestProducts();

        expect(screen.getByText('Le catalogue arrive bientôt.')).toBeTruthy();
    });

    it('says the catalogue is unavailable, rather than failing, when the API does not answer', async () => {
        const logged = vi.spyOn(console, 'error').mockImplementation(() => undefined);
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('fetch failed')));

        await renderLatestProducts();

        expect(screen.getByRole('status').textContent).toContain('momentanément indisponible');
        expect(logged).toHaveBeenCalledOnce();
    });
});
