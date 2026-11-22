import type { CreateParams, CreateResult, DataProvider, DeleteParams, DeleteResult, Identifier, RaRecord } from 'react-admin';

import type { operations } from './api/schema';
import { httpClient } from './auth/httpClient';
import { apiUrl } from './config';

type QueryOf<Operation extends keyof operations> = NonNullable<operations[Operation]['parameters']['query']>;
type SortOf<Operation extends keyof operations> = NonNullable<QueryOf<Operation> extends { sort?: infer Sort } ? Sort : never>;

/** The API refuses more than 100 items a page (Pagination::MAX_PER_PAGE). */
export const MAX_PER_PAGE = 100;

// Typed against the contract: a field the API cannot sort by does not compile.
const PRODUCT_SORTS: readonly SortOf<'get_admin_products_list'>[] = ['name', 'price', 'created_at'];
const USER_SORTS: readonly SortOf<'get_admin_users_list'>[] = ['email', 'first_name', 'last_name', 'created_at'];

/**
 * The resources of the back-office: where the API serves them, and the fields its
 * lists can be sorted by (any other answers 422).
 */
const RESOURCES: Record<string, { path: string; sortable: readonly string[] }> = {
    products: { path: '/admin/products', sortable: PRODUCT_SORTS },
    users: { path: '/admin/users', sortable: USER_SORTS },
};

function url(resource: string, id?: Identifier): string {
    const known = RESOURCES[resource];
    if (undefined === known) {
        throw new Error(`Unknown resource "${resource}".`);
    }

    return apiUrl(undefined === id ? known.path : `${known.path}/${encodeURIComponent(String(id))}`);
}

function sendJson(method: 'POST' | 'PUT', body: unknown): { method: string; body: string } {
    return { method, body: JSON.stringify(body) };
}

/**
 * React-admin's data provider for the API of the back-office (/api/v1/admin). Every call
 * goes through httpClient: the JWT of the session, its renewal, the messages of the API.
 */
export const dataProvider: DataProvider = {
    /**
     * A list is one page: {"items": [...], "pagination": {"total": ...}} (P1-04).
     */
    async getList(resource, { pagination, sort, filter }) {
        const query = new URLSearchParams();
        if (pagination) {
            query.set('page', String(pagination.page));
            query.set('per_page', String(Math.min(pagination.perPage, MAX_PER_PAGE)));
        }
        // React-admin sorts by id unless told otherwise, which the API refuses: a field it
        // cannot sort by is left out, and the API keeps its own order.
        if (sort && RESOURCES[resource]?.sortable.includes(sort.field)) {
            query.set('sort', sort.field);
            query.set('order', 'DESC' === sort.order ? 'desc' : 'asc');
        }
        // The filters are named as the API names them (fragrance, min_price, q…).
        for (const [name, value] of Object.entries(filter ?? {})) {
            if (undefined !== value && null !== value && '' !== value) {
                query.set(name, String(value));
            }
        }

        const { json } = await httpClient(`${url(resource)}?${query}`);

        return { data: json.items, total: json.pagination.total };
    },

    async getOne(resource, { id }) {
        const { json } = await httpClient(url(resource, id));

        return { data: json };
    },

    async getMany(resource, { ids }) {
        // The API has no filter on several ids: one request per record, in parallel.
        const data = await Promise.all(ids.map(async id => (await httpClient(url(resource, id))).json));

        return { data };
    },

    async getManyReference(resource, { target, id, ...params }) {
        return dataProvider.getList(resource, { ...params, filter: { ...params.filter, [target]: id } });
    },

    async create<RecordType extends Omit<RaRecord, 'id'> = Omit<RaRecord, 'id'>, ResultRecordType extends RaRecord = RecordType & { id: Identifier }>(
        resource: string,
        { data }: CreateParams<RecordType>,
    ): Promise<CreateResult<ResultRecordType>> {
        // A creation answers {"id": "..."}: the record is what was sent, with its id.
        const { json } = await httpClient(url(resource), sendJson('POST', data));

        return { data: { ...data, id: json.id } as unknown as ResultRecordType };
    },

    async update(resource, { id, data }) {
        // An update answers the record as stored.
        const { json } = await httpClient(url(resource, id), sendJson('PUT', data));

        return { data: json };
    },

    async updateMany(resource, { ids, data }) {
        await Promise.all(ids.map(id => httpClient(url(resource, id), sendJson('PUT', data))));

        return { data: ids };
    },

    async delete<RecordType extends RaRecord = RaRecord>(resource: string, { id, previousData }: DeleteParams<RecordType>): Promise<DeleteResult<RecordType>> {
        await httpClient(url(resource, id), { method: 'DELETE' });

        // The API answers a message, not the record: react-admin gets the one it had.
        return { data: previousData ?? ({ id } as RecordType) };
    },

    async deleteMany(resource, { ids }) {
        await Promise.all(ids.map(id => httpClient(url(resource, id), { method: 'DELETE' })));

        return { data: ids };
    },
};
