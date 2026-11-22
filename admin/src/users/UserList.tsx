import { DataTable, DateField, List, SearchInput } from 'react-admin';

import type { components } from '../api/schema';
import { roleLabel } from '../auth/roles';

type Account = components['schemas']['UserView'];

// q: part of the email, the first name or the last name, whatever the case (P1-04).
const filters = [<SearchInput key="q" source="q" alwaysOn />];

/**
 * The accounts, newest first. Administrators only: the API refuses them to the editors.
 * Read only for now: the account screens come with P3-06.
 */
export const UserList = () => (
    <List sort={{ field: 'created_at', order: 'DESC' }} filters={filters} perPage={25}>
        <DataTable<Account> rowClick={false} bulkActionButtons={false}>
            <DataTable.Col source="email" />
            <DataTable.Col source="first_name" />
            <DataTable.Col source="last_name" />
            <DataTable.Col source="roles" disableSort render={account => roleLabel(account.roles) ?? 'client'} />
            <DataTable.Col source="created_at">
                <DateField source="created_at" showTime />
            </DataTable.Col>
        </DataTable>
    </List>
);
