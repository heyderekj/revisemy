import { APIRequestContext, expect, request } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Where the app runs for the browser specs, and on what.
 *
 * Its own port and its own SQLite file, so a running `composer dev` and the
 * database behind it are never touched. Nothing reaches a real service: the
 * queue runs inline, broadcasting goes to the log, and second opinion is off,
 * so a review reads the same on every run.
 */
export const APP = 'http://127.0.0.1:8126';
export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
export const DB = resolve(ROOT, 'database/e2e.sqlite');

export function appEnv(): Record<string, string> {
    return {
        APP_ENV: 'local',
        APP_DEBUG: 'true',
        APP_URL: APP,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB,
        QUEUE_CONNECTION: 'sync',
        BROADCAST_CONNECTION: 'log',
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
        REVISEMY_DISK: 'local',
        REVISEMY_SECOND_OPINION: 'false',
        REVISEMY_TRY_TOKEN_PER_HOUR: '1000',
        REVISEMY_TRY_TOKEN_PER_DAY: '1000',
        VITE_REVERB_APP_KEY: '',
        NIGHTWATCH_ENABLED: 'false',
    };
}

const SHOT = `data:image/png;base64,${readFileSync(resolve(ROOT, 'tests/e2e/fixtures/shot.png')).toString('base64')}`;

export type Seeded = { api: APIRequestContext; token: string; review: { id: string; review_url: string; board_url: string } };

/**
 * A fresh try token and a one-screenshot review, made the way an agent makes
 * them: through the API. There is no seeder because there are no accounts.
 */
/**
 * An element map for the fixture shot (1200×800 at 1×), as the collector at
 * /capture/elements.js would return it: a hero box holding a heading and a
 * #buy button.
 */
export const FIXTURE_ELEMENTS = {
    version: 2,
    docWidth: 1200,
    docHeight: 800,
    viewport: { width: 1200, height: 800 },
    elements: [
        { selector: '#hero', tag: 'section', kind: 'Box', text: 'Plans for every team Buy now', box: { x: 60, y: 60, w: 600, h: 300 }, parent: null, styles: { bg: '#eef2ff', p: [40, 40, 40, 40], r: 16 } },
        { selector: '#hero > h1', tag: 'h1', kind: 'Heading', text: 'Plans for every team', box: { x: 100, y: 100, w: 400, h: 48 }, parent: 0, styles: { fs: 40, lh: 48, fw: 700, ff: 'Inter', c: '#111827' } },
        { selector: '#buy', tag: 'a', kind: 'Button', text: 'Buy now', role: 'link', box: { x: 100, y: 200, w: 200, h: 60 }, parent: 0, styles: { fs: 18, lh: 24, fw: 600, ff: 'Inter', c: '#ffffff', bg: '#2563eb', p: [18, 32, 18, 32], r: 10 } },
        { selector: '#buy > span', tag: 'span', kind: 'Text', text: 'Buy now', box: { x: 130, y: 218, w: 80, h: 24 }, parent: 2, styles: { fs: 18, fw: 600, c: '#ffffff' } },
    ],
};

export async function seedReview(title = 'Pricing page', { elements = false }: { elements?: boolean } = {}): Promise<Seeded> {
    const anon = await request.newContext({ baseURL: APP });
    const minted = await anon.post('/api/try-token');
    expect(minted.status()).toBe(201);
    const { token } = await minted.json();

    const api = await request.newContext({
        baseURL: APP,
        extraHTTPHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    const created = await api.post('/api/reviews', {
        data: { title, type: 'ui', images: [SHOT], ...(elements ? { image_details: [{ dpr: 1, elements: FIXTURE_ELEMENTS }] } : {}) },
    });
    expect(created.status(), await created.text()).toBe(201);

    return { api, token, review: await created.json() };
}

/** The path part of a URL the API returned, so the page runs against baseURL. */
export function path(url: string): string {
    const u = new URL(url);
    return u.pathname + u.search;
}

/**
 * One JSON-RPC call to the MCP server, as an assistant makes it with the
 * same try token. Returns the result, or fails the spec on an error.
 */
export async function mcp(api: APIRequestContext, method: string, params: object = {}): Promise<any> {
    const response = await api.post('/mcp/revisemy', {
        headers: { Accept: 'application/json, text/event-stream' },
        data: { jsonrpc: '2.0', id: 1, method, params },
    });
    const body = await response.json();
    expect(body.error, JSON.stringify(body.error)).toBeUndefined();
    return body.result;
}
