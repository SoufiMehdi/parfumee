import { vi } from 'vitest';

export interface Reply {
    status?: number;
    body?: unknown;
    headers?: Record<string, string>;
}

export interface Call {
    method: string;
    path: string;
    /** The query string, which routes do not match on. */
    query: URLSearchParams;
    body: unknown;
    headers: Headers;
}

type Route = Reply | ((call: Call) => Reply | Promise<Reply>);

/**
 * Replaces fetch with a fake API: routes keyed by "METHOD /path" answer, every call is
 * recorded, and any other request fails the test.
 */
export function fakeApi(routes: Record<string, Route>): { calls: Call[] } {
    const calls: Call[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
            const url = new URL(input instanceof Request ? input.url : String(input));
            const call: Call = {
                method: (init.method ?? 'GET').toUpperCase(),
                path: url.pathname,
                query: url.searchParams,
                // JSON as an object; a multipart body (an upload) as the FormData itself.
                body: typeof init.body === 'string' ? JSON.parse(init.body) : init.body instanceof FormData ? init.body : undefined,
                headers: new Headers(init.headers),
            };
            calls.push(call);

            const route = routes[`${call.method} ${call.path}`];
            if (undefined === route) {
                throw new Error(`Unexpected request: ${call.method} ${call.path}`);
            }
            const reply = typeof route === 'function' ? await route(call) : route;

            return new Response(undefined === reply.body ? null : JSON.stringify(reply.body), {
                status: reply.status ?? 200,
                headers: { 'Content-Type': 'application/json', ...reply.headers },
            });
        }),
    );

    return { calls };
}

/**
 * The calls made to one route, in order.
 */
export function callsTo(calls: Call[], route: string): Call[] {
    return calls.filter(call => `${call.method} ${call.path}` === route);
}
