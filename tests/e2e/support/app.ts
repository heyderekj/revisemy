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
export async function seedReview(title = 'Pricing page'): Promise<Seeded> {
    const anon = await request.newContext({ baseURL: APP });
    const minted = await anon.post('/api/try-token');
    expect(minted.status()).toBe(201);
    const { token } = await minted.json();

    const api = await request.newContext({
        baseURL: APP,
        extraHTTPHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    const created = await api.post('/api/reviews', { data: { title, type: 'ui', images: [SHOT] } });
    expect(created.status(), await created.text()).toBe(201);

    return { api, token, review: await created.json() };
}

/** The path part of a URL the API returned, so the page runs against baseURL. */
export function path(url: string): string {
    const u = new URL(url);
    return u.pathname + u.search;
}
