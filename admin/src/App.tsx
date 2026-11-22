import PeopleIcon from '@mui/icons-material/People';
import ProductIcon from '@mui/icons-material/Inventory2';
import { Admin, Resource } from 'react-admin';

import { authProvider } from './auth/authProvider';
import { Dashboard } from './Dashboard';
import { dataProvider } from './dataProvider';
import { i18nProvider } from './i18n';
import { LoginPage } from './LoginPage';
import { ProductList } from './products/ProductList';
import { UserList } from './users/UserList';

/**
 * The back-office.
 * requireAuth: nothing is shown before the session is checked.
 * disableTelemetry: react-admin would otherwise tell its publisher where it runs.
 *
 * The resources depend on the roles of the account (getPermissions): the editors manage
 * the catalogue, the accounts are for the administrators, as in the API.
 */
export const App = () => (
    <Admin
        title="Parfumée"
        dataProvider={dataProvider}
        authProvider={authProvider}
        i18nProvider={i18nProvider}
        loginPage={LoginPage}
        dashboard={Dashboard}
        requireAuth
        disableTelemetry
    >
        {(permissions: string[] | undefined) => (
            <>
                <Resource name="products" list={ProductList} icon={ProductIcon} />
                {permissions?.includes('ROLE_ADMIN') ? <Resource name="users" list={UserList} icon={PeopleIcon} /> : null}
            </>
        )}
    </Admin>
);
