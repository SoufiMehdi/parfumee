import { apiClient } from '@/api/client';
import type { components } from '@/api/schema';

export type Product = components['schemas']['ProductView'];

/** How many products the home page shows. */
export const LATEST_COUNT = 8;

export class CatalogUnavailableError extends Error {}

/**
 * The newest products of the catalogue. Throws CatalogUnavailableError when the API
 * cannot be reached or does not answer with a list.
 */
export async function getLatestProducts(): Promise<Product[]> {
    let result;
    try {
        result = await apiClient().GET('/api/v1/shop/products', {
            params: { query: { sort: 'created_at', order: 'desc', per_page: LATEST_COUNT } },
        });
    } catch (cause) {
        throw new CatalogUnavailableError('The API cannot be reached.', { cause });
    }

    if (undefined === result.data) {
        throw new CatalogUnavailableError(`The API answered ${result.response.status} to the list of products.`);
    }

    return result.data.items;
}
