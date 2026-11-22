import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { fakeApi } from '@/test/fakeApi';

import { aPageOf, aProduct } from './fixtures';
import { CatalogUnavailableError, getLatestProducts, LATEST_COUNT } from './latestProducts';

const PRODUCTS = 'GET /api/v1/shop/products';

beforeEach(() => {
    vi.stubEnv('API_URL', 'http://api.example.test');
});

afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
});

describe('getLatestProducts', () => {
    it('asks the API for the newest products, and returns them', async () => {
        const products = [aProduct(), aProduct({ id: 'p-2', name: 'Bougie Vanille' })];
        const { calls } = fakeApi({ [PRODUCTS]: { body: aPageOf(products) } });

        expect(await getLatestProducts()).toEqual(products);

        expect(calls).toHaveLength(1);
        expect(calls[0].url.origin).toBe('http://api.example.test');
        expect(Object.fromEntries(calls[0].url.searchParams)).toEqual({ sort: 'created_at', order: 'desc', per_page: String(LATEST_COUNT) });
    });

    it('says the catalogue is unavailable when the API answers an error', async () => {
        fakeApi({ [PRODUCTS]: { status: 500, body: { error: 'An unexpected error occurred.' } } });

        await expect(getLatestProducts()).rejects.toBeInstanceOf(CatalogUnavailableError);
    });

    it('says the catalogue is unavailable when the API cannot be reached', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('fetch failed')));

        await expect(getLatestProducts()).rejects.toBeInstanceOf(CatalogUnavailableError);
    });
});
