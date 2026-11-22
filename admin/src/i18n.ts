import polyglotI18nProvider from 'ra-i18n-polyglot';
import frenchMessages from 'ra-language-french';

/**
 * The back-office speaks French. Messages written in French in the code (the errors of
 * the session, for instance) are not translation keys: allowMissing shows them as they are.
 */
export const i18nProvider = polyglotI18nProvider(() => frenchMessages, 'fr', [{ locale: 'fr', name: 'Français' }], { allowMissing: true });
