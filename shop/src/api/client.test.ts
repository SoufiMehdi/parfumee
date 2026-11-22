import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiBaseUrl } from './client';

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('apiBaseUrl', () => {
    it('reads API_URL, without a trailing slash', () => {
        vi.stubEnv('API_URL', 'http://api.example.test/');

        expect(apiBaseUrl()).toBe('http://api.example.test');
    });

    it('fails clearly when API_URL is not set', () => {
        vi.stubEnv('API_URL', '');

        expect(() => apiBaseUrl()).toThrow('API_URL is not set');
    });
});
