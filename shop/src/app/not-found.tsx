import Link from 'next/link';

export default function NotFound() {
    return (
        <section>
            <h1>Page introuvable</h1>
            <p>Cette page n’existe pas, ou plus.</p>
            <Link href="/">Retour à l’accueil</Link>
        </section>
    );
}
