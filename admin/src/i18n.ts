import polyglotI18nProvider from 'ra-i18n-polyglot';
import frenchMessages from 'ra-language-french';

/**
 * Names of the resources and of their fields, as the menu, the column headers and the
 * filters show them.
 */
const resources = {
    products: {
        name: 'Produit |||| Produits',
        fields: {
            name: 'Nom',
            price: 'Prix',
            category: { name: 'Catégorie' },
            attributes: { fragrance: 'Parfum' },
            fragrance: 'Parfum',
            min_price: 'Prix minimum',
            max_price: 'Prix maximum',
            created_at: 'Créé le',
        },
    },
    users: {
        name: 'Compte |||| Comptes',
        fields: {
            email: 'Email',
            first_name: 'Prénom',
            last_name: 'Nom',
            roles: 'Rôle',
            created_at: 'Inscrit le',
        },
    },
};

/**
 * The back-office speaks French. Messages written in French in the code (the errors of
 * the session, for instance) are not translation keys: allowMissing shows them as they are.
 */
export const i18nProvider = polyglotI18nProvider(() => ({ ...frenchMessages, resources }), 'fr', [{ locale: 'fr', name: 'Français' }], { allowMissing: true });
