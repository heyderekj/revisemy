import { expect, Page, test } from '@playwright/test';
import { path, seedReview } from './support/app';

/**
 * The review on a phone, by touch: a finger scrolls and opens marks, Mark
 * turns the next drag or tap into one, and the dock under the shot holds the
 * marks and the decision. Every test here runs on the phone project.
 */

async function shotBox(page: Page) {
    const shot = page.getByRole('img', { name: 'Screenshot 1' });
    await expect(shot).toBeVisible();
    return (await shot.boundingBox())!;
}

/** A one-finger drag, as real touch events (Playwright's touchscreen only taps). */
async function drag(page: Page, from: { x: number; y: number }, to: { x: number; y: number }) {
    const cdp = await page.context().newCDPSession(page);
    const point = (p: { x: number; y: number }) => [{ x: Math.round(p.x), y: Math.round(p.y) }];
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point(from) });
    for (let i = 1; i <= 8; i++) {
        const at = { x: from.x + ((to.x - from.x) * i) / 8, y: from.y + ((to.y - from.y) * i) / 8 };
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: point(at) });
    }
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await cdp.detach();
}

async function saveNote(page: Page, body: string) {
    const note = page.getByRole('dialog', { name: /Leave a note/ });
    await expect(note).toBeVisible();
    await note.getByPlaceholder(/Be specific/).fill(body);
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();
}

test('a tap only marks once Mark is on, and a drag draws a box @phone', async ({ page }) => {
    const { review, api } = await seedReview('Thumbs');
    await page.goto(path(review.review_url));
    const box = await shotBox(page);

    // A stray tap on the shot leaves nothing behind.
    await page.touchscreen.tap(box.x + box.width * 0.5, box.y + box.height * 0.5);
    await page.waitForTimeout(400);
    await expect(page.getByRole('dialog', { name: /Leave a note/ })).toHaveCount(0);

    // Mark, then drag: a box mark.
    await page.getByRole('button', { name: 'Mark', exact: true }).tap();
    await expect(page.locator('[data-marking-hint]')).toBeVisible();
    await drag(page, { x: box.x + box.width * 0.1, y: box.y + box.height * 0.2 }, { x: box.x + box.width * 0.5, y: box.y + box.height * 0.6 });
    await saveNote(page, 'Hero copy is cramped.');

    const pins = (await (await api.get(`/api/reviews/${review.id}`)).json()).work_packets.pins;
    expect(pins).toHaveLength(1);
    expect(pins[0].area.w).toBeGreaterThan(0.2);

    // Mark is off again after one mark.
    await expect(page.getByRole('button', { name: 'Mark', exact: true })).toBeVisible();
    await expect(page.locator('[data-marking-hint]')).toBeHidden();
});

test('tapping a mark opens it from the bottom, and the dock holds the list @phone', async ({ page }) => {
    const { review } = await seedReview('Pocket');
    await page.goto(path(review.review_url));
    const box = await shotBox(page);

    await page.getByRole('button', { name: 'Mark', exact: true }).tap();
    await page.touchscreen.tap(box.x + box.width * 0.7, box.y + box.height * 0.4);
    await saveNote(page, 'Price needs a period.');

    // The mark on the shot and its chip in the dock both open the sheet, not the drawer.
    await page.getByRole('button', { name: 'Open mark M1' }).first().tap();
    const sheet = page.getByRole('dialog', { name: 'Mark M1' });
    await expect(sheet.getByText('Price needs a period.')).toBeVisible();
    await expect(page.locator('#rm-drawer')).toBeHidden();
    await sheet.getByRole('button', { name: 'Close mark' }).last().tap();
    await expect(sheet).toBeHidden();

    await page.locator('[data-phone-dock]').getByRole('button', { name: 'Open mark M1' }).tap();
    await expect(sheet).toBeVisible();
    await sheet.getByRole('button', { name: 'Close mark' }).last().tap();

    // All opens the whole list as a sheet; a tap on a card opens that mark over the shot.
    await page.locator('[data-dock-list]').tap();
    const drawer = page.locator('#rm-drawer');
    await expect(drawer).toBeVisible();
    await drawer.getByText('Price needs a period.').tap();
    await expect(drawer).toBeHidden();
    await expect(sheet).toBeVisible();

    // The decision stays under the thumb.
    await sheet.getByRole('button', { name: 'Close mark' }).last().tap();
    await expect(page.locator('[data-phone-dock]').getByRole('button', { name: 'Approve' })).toBeInViewport();
});

test('the phone shows the shot, not chrome @phone', async ({ page }) => {
    const { review } = await seedReview('Room to see');
    await page.goto(path(review.review_url));
    const box = await shotBox(page);

    // Header, folded brief and dock leave most of the screen to the shot's viewport.
    const viewport = page.viewportSize()!;
    const header = (await page.locator('header').first().boundingBox())!;
    const dock = (await page.locator('[data-phone-dock]').boundingBox())!;
    expect(viewport.height - header.height - dock.height).toBeGreaterThan(viewport.height * 0.7);
    expect(box.y).toBeLessThan(header.height + 80);

    // Fields are 16px so the phone doesn't zoom in on them.
    await page.getByRole('button', { name: 'Mark', exact: true }).tap();
    await page.touchscreen.tap(box.x + box.width * 0.3, box.y + box.height * 0.3);
    const size = await page.getByPlaceholder(/Be specific/).evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
    expect(size).toBeGreaterThanOrEqual(16);
});

test('two fingers zoom the shot, never the page @phone', async ({ page }) => {
    const { review } = await seedReview('Pinch');
    await page.goto(path(review.review_url));
    const before = await shotBox(page);

    const cdp = await page.context().newCDPSession(page);
    const cx = before.x + before.width / 2;
    const cy = before.y + before.height / 2;
    const fingers = (spread: number) => [{ x: Math.round(cx - spread), y: Math.round(cy), id: 1 }, { x: Math.round(cx + spread), y: Math.round(cy), id: 2 }];
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: fingers(30) });
    for (let s = 40; s <= 90; s += 10) {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: fingers(s) });
    }
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await cdp.detach();

    await expect.poll(async () => (await page.getByRole('img', { name: 'Screenshot 1' }).boundingBox())!.width).toBeGreaterThan(before.width * 2);
    expect(await page.evaluate(() => window.visualViewport?.scale ?? 1)).toBe(1);
    await expect(page.getByRole('dialog', { name: /Leave a note/ })).toHaveCount(0);
});
