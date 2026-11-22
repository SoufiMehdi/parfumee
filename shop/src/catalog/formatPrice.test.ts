import { describe, expect, it } from 'vitest';

import { formatPrice } from './formatPrice';

describe('formatPrice', () => {
    it.each([
        [24.9, '24,90 €'],
        [19, '19,00 €'],
        [1250.5, '1 250,50 €'],
    ])('writes %s euros the French way', (price, expected) => {
        // Intl separates with narrow no-break spaces: compared with plain spaces here.
        expect(formatPrice(price).replace(/\s/g, ' ')).toBe(expected);
    });
});
