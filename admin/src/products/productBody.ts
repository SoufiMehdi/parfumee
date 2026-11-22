import type { components } from '../api/schema';

type ProductBody = components['schemas']['CreateProductDto'];
type Attributes = components['schemas']['AttributesView'];

/** What the product form holds: the record of the API (ProductView), as edited. */
export interface ProductFormValues {
    name: string;
    price: number;
    category?: { id?: string | null } | null;
    attributes?: Partial<Record<keyof Attributes, string | number | null>> | null;
    status?: ProductBody['status'];
}

const ATTRIBUTES: readonly (keyof Attributes)[] = ['weight', 'fragrance', 'size', 'description', 'presentation'];

/**
 * The body the API expects (CreateProductDto, UpdateProductDto) from the values of the form.
 *
 * The record of the API holds more (id, slug, pictures, dates…), which the API would ignore:
 * only its fields are sent. An empty attribute is left out rather than sent as "" or null,
 * and the API stores none for it. Without a status, null leaves it to the API: a draft for a
 * creation, unchanged for an update (the creation form starts with "draft" anyway).
 */
export function toProductBody(values: ProductFormValues): ProductBody {
    const attributes: Record<string, string | number> = {};
    for (const name of ATTRIBUTES) {
        const value = values.attributes?.[name];
        if (typeof value === 'number' || (typeof value === 'string' && '' !== value.trim())) {
            attributes[name] = typeof value === 'string' ? value.trim() : value;
        }
    }

    return {
        name: values.name,
        price: values.price,
        category_id: values.category?.id ?? '',
        attributes,
        status: values.status ?? null,
    };
}
