import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { DB, ROOT, appEnv } from './support/app';

/**
 * A fresh database for every run. The specs make their own try tokens and
 * reviews through the API, so there is nothing to seed.
 */
export default function globalSetup() {
    // The specs run on the built assets. A `public/hot` left by `npm run dev`
    // points every page at a Vite server that may not be running.
    if (existsSync(resolve(ROOT, 'public/hot'))) {
        throw new Error('public/hot is there, so pages load from Vite. Stop `npm run dev` (or delete public/hot) and run `npm run build`.');
    }
    if (!existsSync(resolve(ROOT, 'public/build/manifest.json'))) {
        throw new Error('No built assets. Run `npm run build` first.');
    }

    rmSync(DB, { force: true });
    writeFileSync(DB, '');

    const artisan = (...args: string[]) =>
        execFileSync('php', ['artisan', ...args], { cwd: ROOT, env: { ...process.env, ...appEnv() }, stdio: 'inherit' });

    artisan('migrate:fresh', '--force');
    // Try-token minting is throttled in the file cache, which outlasts a run.
    artisan('cache:clear');
}
