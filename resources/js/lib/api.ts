import { reactive } from 'vue';

/**
 * Thin fetch wrapper for the session-authenticated JSON API.
 *
 * Every call updates the shared `connection` state that the status bar
 * shows: last successful sync, online/offline and in-flight requests.
 */

export const connection = reactive({
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
    lastSync: null as Date | null,
    pending: 0,
    lastError: null as string | null,
});

export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }

    get isValidation(): boolean {
        return this.status === 422;
    }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

export type QueryValue = string | number | boolean | null | undefined | object;

export function buildQuery(params: Record<string, QueryValue>): string {
    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        search.set(
            key,
            typeof value === 'object' ? JSON.stringify(value) : String(value),
        );
    }

    const query = search.toString();

    return query ? `?${query}` : '';
}

export async function request<T>(
    method: Method,
    url: string,
    body?: unknown,
    init: { signal?: AbortSignal } = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = xsrfToken();

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    connection.pending++;

    let response: Response;

    try {
        response = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: init.signal,
        });
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            connection.online = false;
            connection.lastError = 'Cannot reach the server.';
        }

        throw error;
    } finally {
        connection.pending--;
    }

    connection.online = true;

    if (response.status === 401 || response.status === 419) {
        connection.lastError = 'Your session has expired.';
        window.location.href = '/login';

        throw new ApiError(response.status, 'Your session has expired.');
    }

    const payload =
        response.status === 204
            ? null
            : await response.json().catch(() => null);

    if (!response.ok) {
        connection.lastError = payload?.message ?? response.statusText;

        throw new ApiError(
            response.status,
            payload?.message ?? `Request failed (${response.status}).`,
            payload?.errors ?? {},
        );
    }

    connection.lastSync = new Date();
    connection.lastError = null;

    return payload as T;
}

export const api = {
    get: <T>(url: string, init?: { signal?: AbortSignal }) =>
        request<T>('GET', url, undefined, init),
    post: <T>(url: string, body?: unknown) => request<T>('POST', url, body),
    put: <T>(url: string, body?: unknown) => request<T>('PUT', url, body),
    patch: <T>(url: string, body?: unknown) => request<T>('PATCH', url, body),
    delete: <T = null>(url: string) => request<T>('DELETE', url),
};

/** Trigger a file download (CSV exports stream from the server). */
export function download(url: string): void {
    const link = document.createElement('a');
    link.href = url;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    connection.lastSync = new Date();
}

/** Save client-side text (e.g. a single exported row) as a file. */
export function saveText(
    filename: string,
    text: string,
    type = 'text/csv;charset=utf-8',
): void {
    const blob = new Blob(['﻿', text], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
