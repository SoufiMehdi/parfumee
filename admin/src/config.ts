/**
 * Where the API answers, without a trailing slash: VITE_API_URL, read when the app is
 * built (admin/.env in development; a production build sets its own).
 */
export const API_URL: string = import.meta.env.VITE_API_URL.replace(/\/+$/, '');

/**
 * Full URL of a route of the API: apiUrl('/admin/me') → http://127.0.0.1:8000/api/v1/admin/me.
 */
export function apiUrl(path: string): string {
    return `${API_URL}/api/v1${path}`;
}
