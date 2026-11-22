import ArchiveIcon from '@mui/icons-material/Archive';
import { DeleteButton, Edit, SaveButton, Toolbar, useRecordContext } from 'react-admin';

import { ProductForm } from './ProductForm';
import { ProductPictures } from './ProductPictures';
import { toProductBody, type ProductFormValues } from './productBody';

/**
 * Deleting a product archives it in the API (DELETE answers 204, the product stays with
 * the status "archived"): the button says so. An archived product is republished with
 * the status field.
 */
const ArchiveButton = () => {
    const record = useRecordContext<{ status?: string }>();
    if (!record || 'archived' === record.status) {
        return null;
    }

    return (
        <DeleteButton
            label="Archiver"
            icon={<ArchiveIcon />}
            mutationMode="pessimistic"
            confirmTitle="Archiver ce produit ?"
            confirmContent="Il quitte la boutique, mais reste dans le back-office (filtre « Statut : Archivé ») : il pourra être republié."
            successMessage="resources.products.notifications.archived"
        />
    );
};

const ProductToolbar = () => (
    <Toolbar sx={{ justifyContent: 'space-between' }}>
        <SaveButton />
        <ArchiveButton />
    </Toolbar>
);

/**
 * Pessimistic: the API checks the product before it is saved, and its errors show under
 * the fields. The record shown afterwards is the one the API stored.
 */
export const ProductEdit = () => (
    <Edit transform={(values: ProductFormValues) => toProductBody(values)} mutationMode="pessimistic" aside={<ProductPictures />}>
        {/* Adding a picture reads the product again: what the editor typed and has not saved yet stays. */}
        <ProductForm toolbar={<ProductToolbar />} resetOptions={{ keepDirtyValues: true }} />
    </Edit>
);
