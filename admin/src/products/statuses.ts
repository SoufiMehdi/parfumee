import type { components } from '../api/schema';

export type ProductStatus = components['schemas']['ProductStatus'];

/** The statuses of a product, in French. Every status of the contract must have one. */
export const STATUS_LABELS: Record<ProductStatus, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
};

/** Choices of the status filter: the API answers each with the products of that status. */
export const STATUS_CHOICES = (Object.entries(STATUS_LABELS) as [ProductStatus, string][]).map(([id, name]) => ({ id, name }));
