import { vi } from 'vitest';

export interface Reply {
    status?: number;
    body?: unknown;
}

export interface Call {
    method: string;
    url: URL;
}

/**
 * Replaces fetch with a fake API: routes keyed by "METHOD /path" answer (the query string
 * is not matched, but recorded in call.url), and any other request fails the test.
 */
export function fakeApi(routes: Record<string, Reply>): { calls: Call[] } {
    const calls: Call[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const request = input instanceof Request ? input : new Request(input, init);
            const call: Call = { method: request.method, url: new URL(request.url) };
            calls.push(call);

            const reply = routes[`${call.method} ${call.url.pathname}`];
            if (undefined === reply) {
                throw new Error(`Unexpected request: ${call.method} ${call.url.pathname}`);
            }

            return new Response(undefined === reply.body ? null : JSON.stringify(reply.body), {
                status: reply.status ?? 200,
                headers: { 'Content-Type': 'application/json' },
            });
        }),
    );

    return { calls };
}
