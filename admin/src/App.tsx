import { Admin, Resource } from 'react-admin';

import { authProvider } from './auth/authProvider';
import { Dashboard } from './Dashboard';
import { i18nProvider } from './i18n';
import { LoginPage } from './LoginPage';

/**
 * The back-office. No data provider yet: the screens of the resources come with P1-10.
 * requireAuth: nothing is shown before the session is checked.
 * disableTelemetry: react-admin would otherwise tell its publisher where it runs.
 */
export const App = () => (
    <Admin title="Parfumée" authProvider={authProvider} i18nProvider={i18nProvider} loginPage={LoginPage} dashboard={Dashboard} requireAuth disableTelemetry>
        {/*
          Declared without screens until P1-10, which gives them their lists: a resource
          without a list stays out of the menu. Without any resource, react-admin would show
          its "Welcome to React-admin" page instead of the sign-in page and the dashboard.
        */}
        <Resource name="products" />
        <Resource name="users" />
    </Admin>
);
