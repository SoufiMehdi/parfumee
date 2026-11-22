import { Create } from 'react-admin';

import { ProductForm } from './ProductForm';
import { toProductBody, type ProductFormValues } from './productBody';

/**
 * A new product is a draft unless told otherwise, as in the API. Once created, the product
 * opens in its edition, where its pictures are added.
 */
export const ProductCreate = () => (
    <Create transform={(values: ProductFormValues) => toProductBody(values)} redirect="edit">
        <ProductForm defaultValues={{ status: 'draft' }} />
    </Create>
);
