import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
    // Next.js 16 defaults: data is read at request time unless cached ('use cache'),
    // and what can be prerendered ships as a static shell.
    cacheComponents: true,
    partialPrefetching: true,
    // Self-hosted in a Node container behind Caddy (ADR 0006): the Docker image of P7-01
    // will add output: 'standalone'. Not before, since "next start" does not run it.
    // No "X-Powered-By: Next.js" header: it tells only an attacker which stack runs.
    poweredByHeader: false,
};

export default nextConfig;
