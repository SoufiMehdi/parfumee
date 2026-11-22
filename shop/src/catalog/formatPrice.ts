const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

/**
 * A price of the API (in euros) as a French reader expects it: 24,90 €.
 */
export function formatPrice(price: number): string {
    return euros.format(price);
}
