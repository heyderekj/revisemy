import { expect, Page, test } from '@playwright/test';
import { path, seedReview } from './support/app';

/** A point on the fixture shot (1200×800), in page coordinates. */
async function at(page: Page, x: number, y: number) {
    const shot = page.getByRole('img', { name: 'Screenshot 1' });
    await expect(shot).toBeVisible();
    const box = (await shot.boundingBox())!;
    return { x: box.x + (x / 1200) * box.width, y: box.y + (y / 800) * box.height };
}

/** The brief floats over the top of the shot; put it away first, and wait for the element list. */
async function open(page: Page, url: string) {
    await page.goto(path(url));
    // The element list has loaded once Snap shows.
    await expect(page.locator('[data-pick-toggle]')).toBeVisible();
    await page.getByRole('button', { name: 'Hide', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Hide', exact: true })).toBeHidden();
}

async function pins(api: any, id: string) {
    return (await (await api.get(`/api/reviews/${id}`)).json()).work_packets.pins;
}

test('a click marks the element under it, and the agent is told which', async ({ page }) => {
    const { review, api } = await seedReview('Element marks', { elements: true });
    await open(page, review.review_url);

    // Hovering the button outlines it and names it.
    const button = await at(page, 200, 230);
    await page.mouse.move(button.x, button.y);
    await expect(page.locator('[data-element-hover]')).toContainText('Button · Buy now · 200 × 60');

    // A click on its label picks the button, not the words inside it.
    await page.mouse.click(button.x, button.y);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await expect(note.getByText('Leave a note on this button', { exact: true })).toBeVisible();
    await expect(note.locator('[data-element-chip]')).toContainText('Button');
    await note.getByText('Inspect').click();
    await expect(note.locator('[data-element-inspect]')).toContainText('200 × 60');
    await expect(note.locator('[data-element-inspect]')).toContainText('#2563eb');

    // Wider steps out to the hero around it.
    await note.getByRole('button', { name: 'Wider' }).click();
    await expect(note.getByText('Leave a note on this box', { exact: true })).toBeVisible();

    await note.getByPlaceholder(/Be specific/).fill('The hero needs more air.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();
    await expect(page.locator('#fb-mark-' + (await pins(api, review.id))[0].id).locator('[data-element-chip]')).toContainText('Box');

    const [pin] = await pins(api, review.id);
    expect(pin.element).toMatchObject({ selector: '#hero', kind: 'Box', via: 'picked', box: { x: 60, y: 60, w: 600, h: 300 } });
    expect(pin.area).toMatchObject({ x: 0.05, y: 0.075, w: 0.5, h: 0.375 });
});

test('Alt-click drops a spot, a drag matches what it covers, and Snap off keeps clicks free', async ({ page }) => {
    const { review, api } = await seedReview('Element spots', { elements: true });
    await open(page, review.review_url);
    const note = page.getByRole('dialog', { name: 'Leave a note' });

    // Alt-click: a spot, tied to nothing.
    const heading = await at(page, 300, 124);
    await page.keyboard.down('Alt');
    await page.mouse.click(heading.x, heading.y);
    await page.keyboard.up('Alt');
    await expect(note.getByText('Leave a note on this spot', { exact: true })).toBeVisible();
    await expect(note.locator('[data-pending-element]')).toHaveCount(0);
    await note.getByPlaceholder(/Be specific/).fill('Spot');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    // A drag close around the button is matched to it, box kept as drawn.
    const from = await at(page, 96, 196);
    const to = await at(page, 304, 264);
    await page.mouse.move(from.x, from.y);
    await page.mouse.down();
    await page.mouse.move(to.x, to.y, { steps: 6 });
    await page.mouse.up();
    await expect(note.locator('[data-element-chip]')).toContainText('Button');
    await note.getByPlaceholder(/Be specific/).fill('Drawn');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    // Snap off: a click on the heading is a plain spot.
    await page.locator('[data-pick-toggle]').click();
    await expect(page.locator('[data-pick-toggle]')).toHaveAttribute('aria-pressed', 'false');
    const free = await at(page, 450, 124);
    await page.mouse.click(free.x, free.y);
    await expect(note.getByText('Leave a note on this spot', { exact: true })).toBeVisible();
    await note.getByPlaceholder(/Be specific/).fill('Free');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    const byBody = Object.fromEntries((await pins(api, review.id)).map((p: any) => [p.body, p]));
    expect(byBody.Spot.element).toBeNull();
    expect(byBody.Drawn.element).toMatchObject({ selector: '#buy', via: 'matched' });
    expect(byBody.Free.element).toBeNull();
});

test('a tap on a phone marks the element under it @phone', async ({ page }) => {
    const { review, api } = await seedReview('Element tap', { elements: true });
    await open(page, review.review_url);

    const button = await at(page, 200, 230);
    await page.mouse.click(button.x, button.y);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await expect(note.getByText('Leave a note on this button', { exact: true })).toBeVisible();
    await note.getByPlaceholder(/Be specific/).fill('Bigger tap target.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    expect((await pins(api, review.id))[0].element).toMatchObject({ selector: '#buy', via: 'picked' });
});
