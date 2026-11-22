import { describe, expect, it } from 'vitest';

import { API_URL, uploadUrl } from './config';

describe('uploadUrl', () => {
    it('serves an uploaded file from the uploads directory of the API', () => {
        expect(uploadUrl('products/candle-1a2b.jpg')).toBe(`${API_URL}/uploads/products/candle-1a2b.jpg`);
        expect(uploadUrl('/products/candle-1a2b.jpg')).toBe(`${API_URL}/uploads/products/candle-1a2b.jpg`);
    });
});
