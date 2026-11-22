import { describe, expect, it } from 'vitest';

import { toProductBody } from './productBody';

describe('toProductBody', () => {
    it('keeps the fields of the API only, with the category as category_id', () => {
        const record = {
            id: 'p-1',
            name: 'Bougie Ambre',
            slug: 'bougie-ambre',
            price: 24.9,
            category: { id: 'c-1', name: 'Bougies' },
            attributes: { weight: 180, fragrance: 'ambre', size: 'M', description: null, presentation: null },
            status: 'published' as const,
            pictures: [],
            created_at: '2026-11-21T10:00:00+00:00',
        };

        expect(toProductBody(record)).toEqual({
            name: 'Bougie Ambre',
            price: 24.9,
            category_id: 'c-1',
            attributes: { weight: 180, fragrance: 'ambre', size: 'M' },
            status: 'published',
        });
    });

    it('leaves out the empty attributes, and trims the others', () => {
        const body = toProductBody({ name: 'Figue', price: 19, category: { id: 'c-1' }, attributes: { fragrance: '  figue ', size: '   ', weight: null, description: '' } });

        expect(body.attributes).toEqual({ fragrance: 'figue' });
    });

    it('keeps a weight of 0', () => {
        expect(toProductBody({ name: 'Figue', price: 19, category: { id: 'c-1' }, attributes: { weight: 0 } }).attributes).toEqual({ weight: 0 });
    });

    it('leaves the status to the API when the form says nothing: an update must not turn a published product into a draft', () => {
        expect(toProductBody({ name: 'Figue', price: 19, category: { id: 'c-1' } }).status).toBeNull();
    });
});
