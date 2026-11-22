import { email, Login, LoginForm, PasswordInput, required, TextInput } from 'react-admin';

/**
 * The default page asks for a user name: the API signs in with an email.
 */
export const LoginPage = () => (
    <Login>
        <LoginForm>
            <TextInput autoFocus source="email" label="Email" type="email" autoComplete="email" validate={[required(), email()]} />
            <PasswordInput source="password" label="Mot de passe" autoComplete="current-password" validate={required()} />
        </LoginForm>
    </Login>
);
