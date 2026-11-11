import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { promisify } from 'node:util';
import { chromium } from '@playwright/test';
import puppeteer from 'puppeteer';
import { captureStates } from '../../resources/js/capture-states.mjs';

const run = promisify(execFile);
const fixture = (name) => new URL('./fixtures/' + name + '.html', import.meta.url).href;

async function hostedFunction(url, executablePath, image) {
    // Exercise the exact code and context sent to Browserless with Chromium,
    // without needing a provider account or a public fixture URL.
    const { stdout } = await run('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['revisemy.capture.function_endpoint' => 'https://capture.test/function', 'revisemy.capture.wait_ms' => 0]);
        Illuminate\Support\Facades\Http::fake(fn ($request) => Illuminate\Support\Facades\Http::response(['image' => base64_encode(file_get_contents($argv[2]))]));
        $viewports = app(App\Services\Capture\PageCaptureService::class)->viewports();
        app(App\Services\Capture\HostedCaptureDriver::class)->captureUrlVia('function', $argv[1], [$viewports[1]]);
        echo json_encode(Illuminate\Support\Facades\Http::recorded()->first()[0]->data());
    `, url, image]);
    const { code, context } = JSON.parse(stdout);
    const { default: capture } = await import('data:text/javascript;base64,' + Buffer.from(code).toString('base64'));
    const browser = await puppeteer.launch({ executablePath, headless: true });
    try {
        const started = performance.now();
        const { data } = await capture({ page: await browser.newPage(), context });
        console.log('Hosted function in local Chromium: ' + Math.round(performance.now() - started) + ' ms');
        return data;
    } finally {
        await browser.close();
    }
}

async function probe(url, folder, path, env) {
    const report = join(folder, 'report.json');
    const started = performance.now();
    await rm(report, { force: true });
    await run('php', ['artisan', 'revisemy:probe-capture', url, '--path=' + path, '--out=' + folder, '--report=' + report], { env })
        .catch((error) => { if (error.code !== 1) throw error; });
    const shots = JSON.parse(await readFile(report, 'utf8'));
    console.table(shots.map(({ path, viewport, width, height, bytes, quality, meta }) => ({
        path, viewport, width, height, bytes, status: quality?.status, ms: meta?.capture_ms,
    })));
    console.log(Math.round(performance.now() - started) + ' ms total');
    return shots;
}

test('capture benchmark: responsive layout, scroll reveals, lazy assets, broken assets and named states', async () => {
    const folder = await mkdtemp(join(tmpdir(), 'revisemy-capture-'));
    const executablePath = process.env.REVISEMY_CAPTURE_CHROME_PATH || chromium.executablePath();
    const browser = await chromium.launch({ executablePath });
    try {
        const env = {
            ...process.env,
            REVISEMY_CAPTURE_DRIVER: 'browsershot',
            REVISEMY_CAPTURE_CHROME_PATH: executablePath,
            REVISEMY_CAPTURE_SCROLL_PAGE: 'true',
            REVISEMY_CAPTURE_COLLECT_ELEMENTS: 'true',
            REVISEMY_CAPTURE_URL_FULL_PAGE: 'true',
            REVISEMY_CAPTURE_WAIT_MS: '0',
        };
        const page = await browser.newPage({ viewport: { width: 375, height: 812 }, deviceScaleFactor: 2 });
        const ready = await probe(fixture('ready'), folder, 'browsershot', env);
        assert.equal(ready.length, 3);
        for (const shot of ready) {
            assert.equal(shot.quality.status, 'ready', shot.quality.message);
            assert.equal(shot.meta.elements_source, 'same_load');
            const elements = shot.elements.elements;
            const reveal = elements.find((element) => element.selector === '#reveal');
            const lazy = elements.find((element) => element.selector === '#lazy');
            assert.ok(reveal && lazy);
            assert.equal(reveal.box.x, shot.meta.css_width >= 700 ? 48 : 16);
            const pixel = await page.evaluate(async ({ data, box, dpr }) => {
                const image = new Image();
                image.src = data;
                await image.decode();
                const context = document.createElement('canvas').getContext('2d');
                context.drawImage(image, -Math.round((box.x + 10) * dpr), -Math.round((box.y + 10) * dpr));
                return Array.from(context.getImageData(0, 0, 1, 1).data);
            }, {
                data: 'data:image/png;base64,' + (await readFile(shot.image)).toString('base64'),
                box: reveal.box,
                dpr: shot.meta.dpr,
            });
            assert.deepEqual(pixel, [24, 120, 60, 255], 'element coordinates point to the visible reveal');
        }

        const incomplete = await probe(fixture('incomplete'), folder, 'browsershot', env);
        for (const shot of incomplete) {
            assert.equal(shot.quality.status, 'partial');
            assert.equal(shot.meta.incomplete_images, shot.meta.css_width >= 700 ? 0 : 1);
            assert.equal(shot.meta.incomplete_backgrounds, 1);
            assert.equal(shot.meta.incomplete_fonts, 1);
        }

        const hosted = await hostedFunction(fixture('incomplete'), executablePath, incomplete[1].image);
        assert.equal(hosted.stable, true);
        assert.equal(hosted.loaded, false);
        assert.equal(hosted.quality.incomplete_images, 1);
        assert.equal(hosted.quality.incomplete_backgrounds, 1);
        assert.equal(hosted.quality.incomplete_fonts, 1);
        assert.ok(hosted.elements.elements.some(({ selector }) => selector === '#broken'));

        const { stdout: collector } = await run('php', ['-r', "require 'vendor/autoload.php'; echo App\\Services\\Capture\\CaptureSettleScript::collector();"]);
        await page.goto(fixture('ready'));
        const states = await captureStates(page, collector, [
            { name: 'Menu closed' },
            { name: 'Menu open', actions: [{ action: 'click', selector: '#open' }, { action: 'wait_for', selector: '#menu' }] },
        ]);
        assert.deepEqual(states.image_details.map(({ state }) => state), ['Menu closed', 'Menu open']);
        assert.ok(states.image_details.every(({ dpr, elements }) => dpr === 2 && elements));
        assert.ok(states.image_details[0].elements.elements.every(({ text }) => text !== 'Jump to the reveal'));
        assert.ok(states.image_details[1].elements.elements.some(({ text }) => text === 'Jump to the reveal'));
        assert.notEqual(states.images[0], states.images[1]);

        if (process.env.REVISEMY_CAPTURE_BENCHMARK_URL) {
            await probe(process.env.REVISEMY_CAPTURE_BENCHMARK_URL, folder, 'both', {
                ...process.env, REVISEMY_CAPTURE_DRIVER: 'hosted',
            });
        }
    } finally {
        await browser.close();
        await rm(folder, { recursive: true, force: true });
    }
});
