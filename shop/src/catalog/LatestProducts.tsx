import { connection } from 'next/server';

import { getLatestProducts, type Product } from './latestProducts';
import { ProductGrid } from './ProductGrid';

/**
 * The newest products, read from the API for each request: the catalogue shown is
 * always fresh, and the build never calls the API. The home page renders it inside
 * <Suspense>, so that the rest of the page does not wait for it.
 */
export async function LatestProducts() {
    // Prerendering stops here: what follows runs at request time. Outside the try,
    // so that the signal Next.js uses is never caught by mistake.
    await connection();

    let products: Product[];
    try {
        products = await getLatestProducts();
    } catch (error) {
        // In the server logs; the visitor gets a message, not an error page.
        console.error(error);

        return <p role="status">Le catalogue est momentanément indisponible. Réessayez dans quelques instants.</p>;
    }

    if (0 === products.length) {
        return <p>Le catalogue arrive bientôt.</p>;
    }

    return <ProductGrid products={products} />;
}
