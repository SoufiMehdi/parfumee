import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { callsTo, fakeApi, type Reply } from './test/fakeApi';

const SIGN_IN = 'POST /api/v1/admin/login';
const PRODUCTS = 'GET /api/v1/admin/products';
const USERS = 'GET /api/v1/admin/users';

const AMBER = { id: 'p-1', name: 'Ambre', price: 24.9 };
const page = (items: object[], total: number): Reply => ({ body: { items, pagination: { page: 1, per_page: 25, total, total_pages: Math.ceil(total / 25) } } });

let dataProvider: (typeof import('./dataProvider'))['dataProvider'];

beforeEach(async () => {
    localStorage.clear();
    vi.resetModules();
    ({ dataProvider } = await import('./dataProvider'));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

/** The fake API, with a session open: every call of the data provider needs a JWT. */
async function signedIn(routes: Parameters<typeof fakeApi>[0]) {
    const api = fakeApi({ [SIGN_IN]: { body: { token: 'jwt-1', refresh_token: 'refresh-1' } }, ...routes });
    await (await import('./auth/session')).signIn('ada@example.test', 'a-password');

    return api;
}

describe('getList', () => {
    it('asks for one page, sorted and filtered, and reads the items and the total', async () => {
        const { calls } = await signedIn({ [PRODUCTS]: page([AMBER], 41) });

        const result = await dataProvider.getList('products', {
            pagination: { page: 2, perPage: 25 },
            sort: { field: 'price', order: 'DESC' },
            filter: { fragrance: 'ambre', min_price: 10, size: '' },
        });

        expect(result).toEqual({ data: [AMBER], total: 41 });
        const query = callsTo(calls, PRODUCTS)[0].query;
        expect(Object.fromEntries(query)).toEqual({ page: '2', per_page: '25', sort: 'price', order: 'desc', fragrance: 'ambre', min_price: '10' });
        expect(callsTo(calls, PRODUCTS)[0].headers.get('Authorization')).toBe('Bearer jwt-1');
    });

    it('leaves out a sort the API refuses, such as the id react-admin uses by default', async () => {
        const { calls } = await signedIn({ [USERS]: page([], 0) });

        await dataProvider.getList('users', { pagination: { page: 1, perPage: 10 }, sort: { field: 'id', order: 'ASC' }, filter: { q: 'love' } });

        expect(Object.fromEntries(callsTo(calls, USERS)[0].query)).toEqual({ page: '1', per_page: '10', q: 'love' });
    });

    it('never asks for more than 100 items a page, the most the API gives', async () => {
        const { calls } = await signedIn({ [PRODUCTS]: page([], 0) });

        await dataProvider.getList('products', { pagination: { page: 1, perPage: 1000 }, sort: { field: 'name', order: 'ASC' }, filter: {} });

        expect(callsTo(calls, PRODUCTS)[0].query.get('per_page')).toBe('100');
    });

    it('passes the message of a refused list on', async () => {
        await signedIn({ [PRODUCTS]: { status: 422, body: { error: 'Validation failed.', violations: [] } } });

        await expect(dataProvider.getList('products', { pagination: { page: 1, perPage: 25 }, sort: { field: 'name', order: 'ASC' }, filter: {} })).rejects.toMatchObject({
            status: 422,
            message: 'Validation failed.',
        });
    });
});

describe('reading records', () => {
    it('reads one record by its id', async () => {
        const { calls } = await signedIn({ 'GET /api/v1/admin/products/p-1': { body: AMBER } });

        expect(await dataProvider.getOne('products', { id: 'p-1' })).toEqual({ data: AMBER });
        expect(callsTo(calls, 'GET /api/v1/admin/products/p-1')).toHaveLength(1);
    });

    it('reads several records one by one: the API has no filter on several ids', async () => {
        const { calls } = await signedIn({
            'GET /api/v1/admin/products/p-1': { body: AMBER },
            'GET /api/v1/admin/products/p-2': { body: { id: 'p-2', name: 'Vanille' } },
        });

        const { data } = await dataProvider.getMany('products', { ids: ['p-1', 'p-2'] });

        expect(data.map(record => record.id)).toEqual(['p-1', 'p-2']);
        expect(calls.filter(call => call.path.startsWith('/api/v1/admin/products/'))).toHaveLength(2);
    });

    it('reads the records of another one through a filter', async () => {
        const { calls } = await signedIn({ [PRODUCTS]: page([AMBER], 1) });

        await dataProvider.getManyReference('products', {
            target: 'category_id',
            id: 'c-1',
            pagination: { page: 1, perPage: 25 },
            sort: { field: 'name', order: 'ASC' },
            filter: {},
        });

        expect(callsTo(calls, PRODUCTS)[0].query.get('category_id')).toBe('c-1');
    });

    it('refuses a resource the API does not serve', async () => {
        await signedIn({});

        await expect(dataProvider.getOne('orders', { id: 'o-1' })).rejects.toThrow('Unknown resource "orders".');
    });
});

describe('writing records', () => {
    it('creates a record, and gives it the id the API answered', async () => {
        const { calls } = await signedIn({ 'POST /api/v1/admin/products': { status: 201, body: { id: 'p-new' } } });
        const sent = { name: 'Figue', price: 19.5, category_id: 'c-1' };

        expect(await dataProvider.create('products', { data: sent })).toEqual({ data: { ...sent, id: 'p-new' } });
        expect(callsTo(calls, 'POST /api/v1/admin/products')[0].body).toEqual(sent);
    });

    it('updates a record, and keeps the record as the API stored it', async () => {
        const stored = { ...AMBER, price: 26.5, updated_at: '2026-11-22T10:00:00+00:00' };
        const { calls } = await signedIn({ 'PUT /api/v1/admin/products/p-1': { body: stored } });

        const result = await dataProvider.update('products', { id: 'p-1', data: { name: 'Ambre', price: 26.5 }, previousData: AMBER });

        expect(result).toEqual({ data: stored });
        expect(callsTo(calls, 'PUT /api/v1/admin/products/p-1')[0].body).toEqual({ name: 'Ambre', price: 26.5 });
    });

    it('deletes a record, and gives react-admin back the one it had: the API answers a message', async () => {
        const { calls } = await signedIn({ 'DELETE /api/v1/admin/users/u-1': { body: { message: 'User deleted successfully' } } });
        const account = { id: 'u-1', email: 'grace@example.test' };

        expect(await dataProvider.delete('users', { id: 'u-1', previousData: account })).toEqual({ data: account });
        expect(callsTo(calls, 'DELETE /api/v1/admin/users/u-1')).toHaveLength(1);
    });
});
