import { Card, CardContent, Typography } from '@mui/material';
import { Title, useGetIdentity, usePermissions } from 'react-admin';

import { roleLabel } from './auth/roles';

export const Dashboard = () => {
    const { identity } = useGetIdentity();
    const { permissions } = usePermissions<string[]>();
    const role = roleLabel(permissions);

    return (
        <Card sx={{ mt: 2 }}>
            <Title title="Accueil" />
            <CardContent>
                <Typography variant="h5" component="h2" gutterBottom>
                    Bonjour {identity?.fullName}
                </Typography>
                {role && <Typography gutterBottom>Rôle : {role}.</Typography>}
                <Typography color="text.secondary">Les listes sont dans le menu. Les fiches et les formulaires arrivent avec les prochaines tâches.</Typography>
            </CardContent>
        </Card>
    );
};
