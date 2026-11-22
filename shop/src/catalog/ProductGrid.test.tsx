import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { aProduct } from './fixtures';
import { formatPrice } from './formatPrice';
import { ProductGrid } from './ProductGrid';

afterEach(() => {
    cleanup();
});

/** Testing Library reads the page with its spaces collapsed: the no-break space of a price too. */
function asShown(text: string): string {
    return text.replace(/\s+/g, ' ');
}

describe('ProductGrid', () => {
    it('shows the name, the fragrance and the price of each product', () => {
        render(<ProductGrid products={[aProduct(), aProduct({ id: 'p-2', name: 'Bougie Figue', price: 32, attributes: { ...aProduct().attributes, fragrance: 'figue' } })]} />);

        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        expect(screen.getByRole('heading', { name: 'Bougie Ambre' })).toBeTruthy();
        expect(screen.getByText('ambre')).toBeTruthy();
        expect(screen.getByText(asShown(formatPrice(24.9)))).toBeTruthy();
        expect(screen.getByText(asShown(formatPrice(32)))).toBeTruthy();
    });

    it('leaves the fragrance out when the product has none', () => {
        render(<ProductGrid products={[aProduct({ attributes: { ...aProduct().attributes, fragrance: null } })]} />);

        expect(screen.queryByText('ambre')).toBeNull();
    });
});
