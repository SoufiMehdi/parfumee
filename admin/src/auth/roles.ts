/**
 * The role shown for an account, from its roles (/admin/me). ROLE_ADMIN includes ROLE_EDITOR.
 */
export function roleLabel(roles: string[] | undefined): string | null {
    if (roles?.includes('ROLE_ADMIN')) {
        return 'administrateur';
    }
    if (roles?.includes('ROLE_EDITOR')) {
        return 'éditeur';
    }

    return null;
}
