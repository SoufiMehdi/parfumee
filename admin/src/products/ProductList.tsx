import { AutocompleteInput, DataTable, DateField, List, NumberInput, ReferenceInput, SelectInput, TextInput } from 'react-admin';

import { STATUS_CHOICES, STATUS_LABELS, type ProductStatus } from './statuses';

// Named as the API names its filters (P1-04). Without a status, the API lists the drafts and
// the published products: the archived ones are asked for with the filter. A top-level
// category gives the products of its child categories too.
const filters = [
    <SelectInput key="status" source="status" choices={STATUS_CHOICES} translateChoice={false} />,
    <ReferenceInput key="category_id" source="category_id" reference="categories" sort={{ field: 'name', order: 'ASC' }} perPage={100}>
        <AutocompleteInput optionText="name" filterToQuery={q => ({ q })} />
    </ReferenceInput>,
    <TextInput key="fragrance" source="fragrance" />,
    <NumberInput key="min_price" source="min_price" />,
    <NumberInput key="max_price" source="max_price" />,
];

/**
 * The catalogue, newest first. A click on a product opens its edition.
 */
export const ProductList = () => (
    <List sort={{ field: 'created_at', order: 'DESC' }} filters={filters} perPage={25}>
        <DataTable rowClick="edit" bulkActionButtons={false}>
            <DataTable.Col source="name" />
            <DataTable.NumberCol source="price" options={{ style: 'currency', currency: 'EUR' }} />
            <DataTable.Col source="category.name" disableSort />
            <DataTable.Col source="attributes.fragrance" disableSort />
            <DataTable.Col source="status" disableSort render={(product: { status: ProductStatus }) => STATUS_LABELS[product.status]} />
            <DataTable.Col source="created_at">
                <DateField source="created_at" showTime />
            </DataTable.Col>
        </DataTable>
    </List>
);
