const fs = require('node:fs');
const puppeteer = require('puppeteer');

async function capture() {
    const { url, executablePath, viewport, userAgent, goto, waitMs, settle, fullPage, maxHtml } =
        JSON.parse(fs.readFileSync(0, 'utf8'));
    const browser = await puppeteer.launch({ executablePath: executablePath || undefined, headless: true });

    try {
        const page = await browser.newPage();
        if (userAgent) await page.setUserAgent(userAgent);
        await page.setViewport(viewport);
        const navigation = await page.goto(url, goto).catch((error) => {
            if (error.name !== 'TimeoutError') throw error;
        });
        await new Promise((resolve) => setTimeout(resolve, waitMs));
        await page.evaluate(settle);

        const state = await page.evaluate(() => ({
            elements: window.__rmElements || null,
            stable: window.__rmSettleStable === true,
            loaded: window.__rmLoadComplete === true,
            quality: window.__rmQuality || {},
            timings: window.__rmTimings || {},
        }));
        const snapshot = await page.evaluate(() => ({
            html: (document.doctype ? new XMLSerializer().serializeToString(document.doctype) : '') + document.documentElement.outerHTML,
            base: document.baseURI,
        }));
        const image = await page.screenshot({ type: 'png', fullPage, encoding: 'base64' });

        process.stdout.write(JSON.stringify({
            image, html: Buffer.byteLength(snapshot.html) <= maxHtml ? snapshot.html : null,
            snapshotBase: snapshot.base, ...state,
            httpStatus: navigation?.status() ?? null,
            browser: await browser.version(),
        }));
    } finally {
        await browser.close();
    }
}

capture().catch((error) => {
    process.stderr.write(error.message + '\n');
    process.exitCode = 1;
});
