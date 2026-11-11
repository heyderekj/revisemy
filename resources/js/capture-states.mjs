/**
 * Capture up to five named states from an existing Playwright or Puppeteer
 * page. Actions run in order and each state starts where the last one ended.
 * Returns create_review's images and image_details. Actions stay local.
 */
export async function captureStates(page, collector, states) {
    if (!Array.isArray(states) || states.length < 1 || states.length > 5) {
        throw new Error('Provide one to five states.');
    }

    const images = [];
    const image_details = [];
    for (const { name, actions = [] } of states) {
        if (typeof name !== 'string' || !name.trim() || name.trim().length > 80) {
            throw new Error('Give each state a name of 1 to 80 characters.');
        }
        for (const { action, selector, value } of actions) {
            switch (action) {
                case 'click':
                    await page.click(selector);
                    break;
                case 'hover':
                    await page.hover(selector);
                    break;
                case 'fill':
                    await page.locator(selector).fill(value);
                    break;
                case 'wait_for':
                    await page.waitForSelector(selector, { timeout: 5000 });
                    break;
                default:
                    throw new Error('Unknown action: ' + action);
            }
        }

        await page.evaluate(async () => {
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
            await Promise.race([document.fonts.ready, new Promise((resolve) => setTimeout(resolve, 2000))]);
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        });
        const dpr = await page.evaluate(() => window.devicePixelRatio);
        const bytes = Buffer.from(await page.screenshot({ type: 'png', fullPage: true }));
        const elements = await page.evaluate(collector);
        const matches = (pixels, css) => Number.isFinite(css) && Math.abs(pixels - css * dpr) <= Math.max(2, pixels * 0.02);
        const width = bytes.readUInt32BE(16);
        const height = bytes.readUInt32BE(20);

        images.push('data:image/png;base64,' + bytes.toString('base64'));
        image_details.push({
            state: name.trim(),
            dpr,
            ...(matches(width, elements?.docWidth) && matches(height, elements?.docHeight) ? { elements } : {}),
        });
    }

    return { images, image_details };
}
