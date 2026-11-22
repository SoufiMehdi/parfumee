import type { Metadata } from 'next';
import Link from 'next/link';

import './globals.css';

export const metadata: Metadata = {
    title: { default: 'Parfumée — Bougies parfumées', template: '%s · Parfumée' },
    description: 'Boutique de bougies parfumées.',
};

export default function RootLayout({ children }: LayoutProps<'/'>) {
    return (
        <html lang="fr">
            <body>
                <header className="site-header">
                    <Link href="/" className="brand">
                        Parfumée
                    </Link>
                </header>
                <main className="site-main">{children}</main>
            </body>
        </html>
    );
}
