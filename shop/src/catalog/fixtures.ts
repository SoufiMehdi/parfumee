import type { Product } from './latestProducts';

/**
 * A product as the API returns it (ProductView), for the tests.
 */
export function aProduct(overrides: Partial<Product> = {}): Product {
    return {
        id: 'p-1',
        name: 'Bougie Ambre',
        slug: 'bougie-ambre',
        price: 24.9,
        category: { id: 'c-1', name: 'Bougies Parfumées' },
        attributes: { weight: 180, fragrance: 'ambre', size: 'M', description: null, presentation: null },
        pictures: [],
        created_at: '2026-11-21T10:00:00+00:00',
        updated_at: '2026-11-21T10:00:00+00:00',
        ...overrides,
    };
}

export function aPageOf(items: Product[]) {
    return { items, pagination: { page: 1, per_page: 8, total: items.length, total_pages: 1 } };
}
