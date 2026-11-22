import { AutocompleteInput, maxLength, maxValue, minValue, NumberInput, ReferenceInput, required, SelectInput, SimpleForm, TextInput, type SimpleFormProps } from 'react-admin';

import { STATUS_CHOICES } from './statuses';

const categoryChoice = (category: { name: string; parent?: { name: string } | null }) =>
    category.parent ? `${category.parent.name} › ${category.name}` : category.name;

/**
 * The fields of a product, for its creation and its edition. Named as in the record of the
 * API (ProductView): toProductBody() turns them into the body the API expects.
 *
 * The rules repeat those of the API (CreateProductDto), to answer at once; the API still
 * decides, and its errors show under the fields (dataProvider, withFormErrors).
 */
export const ProductForm = (props: Omit<SimpleFormProps, 'children'>) => (
    <SimpleForm {...props}>
        <TextInput source="name" validate={[required(), maxLength(255)]} fullWidth />
        {/* The column is NUMERIC(10, 2): eight digits before the decimal point. */}
        <NumberInput source="price" validate={[required(), minValue(0), maxValue(99999999.99)]} min={0} step={0.01} />
        {/* The categories are searched by name (q), the API serving 100 a page at most. */}
        <ReferenceInput source="category.id" reference="categories" sort={{ field: 'name', order: 'ASC' }} perPage={100}>
            <AutocompleteInput optionText={categoryChoice} validate={required()} filterToQuery={q => ({ q })} />
        </ReferenceInput>
        <SelectInput source="status" choices={STATUS_CHOICES} translateChoice={false} validate={required()} />
        <NumberInput source="attributes.weight" validate={minValue(0)} min={0} />
        <TextInput source="attributes.fragrance" validate={maxLength(255)} />
        <TextInput source="attributes.size" validate={maxLength(255)} />
        <TextInput source="attributes.description" validate={maxLength(5000)} multiline fullWidth />
        <TextInput source="attributes.presentation" validate={maxLength(5000)} multiline fullWidth />
    </SimpleForm>
);
