import { formatPrice } from './formatPrice';
import type { Product } from './latestProducts';
import styles from './ProductGrid.module.css';

/**
 * Cards of products: name, fragrance, price. The product pages, the pictures and the
 * links come with the catalogue pages (P2-11).
 */
export function ProductGrid({ products }: { products: Product[] }) {
    return (
        <ul className={styles.grid}>
            {products.map(product => (
                <li key={product.id} className={styles.card}>
                    <h3 className={styles.name}>{product.name}</h3>
                    {product.attributes.fragrance && <p className={styles.fragrance}>{product.attributes.fragrance}</p>}
                    <p className={styles.price}>{formatPrice(product.price)}</p>
                </li>
            ))}
        </ul>
    );
}
