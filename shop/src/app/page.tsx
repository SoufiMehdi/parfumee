import { Suspense } from 'react';

import { LatestProducts } from '@/catalog/LatestProducts';

import styles from './page.module.css';

/**
 * The home page. Its frame is prerendered; the newest products stream in at request
 * time. Search engines get the whole page, products included: Next.js waits for them
 * before answering a crawler.
 */
export default function HomePage() {
    return (
        <>
            <section className={styles.hero}>
                <h1>Bougies parfumées</h1>
                <p>Les dernières bougies arrivées au catalogue.</p>
            </section>

            <section aria-labelledby="latest-title" className={styles.section}>
                <h2 id="latest-title">Nouveautés</h2>
                <Suspense fallback={<p>Chargement des nouveautés…</p>}>
                    <LatestProducts />
                </Suspense>
            </section>
        </>
    );
}
