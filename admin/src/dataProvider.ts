import { HttpError, type CreateParams, type CreateResult, type DataProvider, type DeleteParams, type DeleteResult, type Identifier, type RaRecord } from 'react-admin';

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
const CATEGORY_SORTS: readonly SortOf<'get_admin_categories_list'>[] = ['name', 'created_at'];

/**
 * The resources of the back-office: where the API serves them, the fields its lists can
 * be sorted by (any other answers 422), and the form fields named otherwise than the
 * fields of the API (the product form edits category.id, the API reads category_id).
 */
const RESOURCES: Record<string, { path: string; sortable: readonly string[]; formFields?: Record<string, string> }> = {
    products: { path: '/admin/products', sortable: PRODUCT_SORTS, formFields: { category_id: 'category.id' } },
    categories: { path: '/admin/categories', sortable: CATEGORY_SORTS },
    users: { path: '/admin/users', sortable: USER_SORTS },
};

interface Violation {
    field: string;
    message: string;
}

/**
 * A 422 of the API lists its violations ({"field": "attributes[weight]", "message": ...}).
 * React-admin shows under each field of the form the errors it finds in body.errors, keyed
 * by the source of the field: attributes[weight] becomes {attributes: {weight: ...}}.
 */
function withFormErrors(resource: string, error: unknown): unknown {
    if (!(error instanceof HttpError) || !Array.isArray(error.body?.violations)) {
        return error;
    }

    const errors: Record<string, unknown> = {};
    for (const { field, message } of error.body.violations as Violation[]) {
        const source = RESOURCES[resource]?.formFields?.[field] ?? field.replace(/\[(\w+)\]/g, '.$1');
        const path = source.split('.');
        let node = errors;
        for (const key of path.slice(0, -1)) {
            node = (node[key] ??= {}) as Record<string, unknown>;
        }
        node[path[path.length - 1]] ??= message;
    }

    return new HttpError(error.message, error.status, { ...error.body, errors });
}

/** The data provider of react-admin, plus the calls its CRUD methods do not cover. */
export interface BackOfficeDataProvider extends DataProvider {
    /** Adds a picture to a product (multipart): JPEG, PNG or WebP, 5 MB at most. */
    uploadProductPicture(productId: Identifier, file: File, alt: string): Promise<{ id: string }>;
}

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
export const dataProvider: BackOfficeDataProvider = {
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
        const { json } = await httpClient(url(resource), sendJson('POST', data)).catch((error: unknown) => {
            throw withFormErrors(resource, error);
        });

        return { data: { ...data, id: json.id } as unknown as ResultRecordType };
    },

    async update(resource, { id, data }) {
        // An update answers the record as stored.
        const { json } = await httpClient(url(resource, id), sendJson('PUT', data)).catch((error: unknown) => {
            throw withFormErrors(resource, error);
        });

        return { data: json };
    },

    async updateMany(resource, { ids, data }) {
        await Promise.all(ids.map(id => httpClient(url(resource, id), sendJson('PUT', data))));

        return { data: ids };
    },

    async delete<RecordType extends RaRecord = RaRecord>(resource: string, { id, previousData }: DeleteParams<RecordType>): Promise<DeleteResult<RecordType>> {
        await httpClient(url(resource, id), { method: 'DELETE' });

        // The API answers no record (204 for a product, which it archives; a message for an
        // account): react-admin gets the one it had.
        return { data: previousData ?? ({ id } as RecordType) };
    },

    async deleteMany(resource, { ids }) {
        await Promise.all(ids.map(id => httpClient(url(resource, id), { method: 'DELETE' })));

        return { data: ids };
    },

    async uploadProductPicture(productId, file, alt) {
        const body = new FormData();
        body.append('file', file);
        if ('' !== alt.trim()) {
            body.append('alt', alt.trim());
        }
        // No Content-Type: the browser writes multipart/form-data with its boundary.
        const { json } = await httpClient(`${url('products', productId)}/pictures`, { method: 'POST', body }).catch((error: unknown) => {
            // A refused file is a 422 whose reason is in its violations (type, size), not in
            // its "Validation failed.": the reason is what the editor needs to read.
            if (error instanceof HttpError && Array.isArray(error.body?.violations) && 0 < error.body.violations.length) {
                throw new HttpError((error.body.violations as Violation[]).map(violation => violation.message).join(' '), error.status, error.body);
            }
            throw error;
        });

        return { id: json.id };
    },
};
