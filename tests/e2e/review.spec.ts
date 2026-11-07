import { expect, Page, test } from '@playwright/test';
import { path, seedReview } from './support/app';

/** Drag a box over the screenshot, the way a person outlines what's off. */
async function outline(page: Page) {
    const shot = page.getByRole('img', { name: 'Screenshot 1' });
    await expect(shot).toBeVisible();
    const box = (await shot.boundingBox())!;
    await page.mouse.move(box.x + box.width * 0.1, box.y + box.height * 0.2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width * 0.4, box.y + box.height * 0.5, { steps: 8 });
    await page.mouse.up();
}

test('a person marks a region and sends it back for changes', async ({ page }) => {
    const { review, api } = await seedReview();
    page.on('dialog', (dialog) => dialog.accept());

    await page.goto(path(review.review_url));
    await outline(page);

    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await expect(note).toBeVisible();
    await note.getByPlaceholder(/Be specific/).fill('The headline crowds the nav.');
    await note.getByText('Must fix').click();
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    await expect(page.getByText('The headline crowds the nav.').first()).toBeVisible();

    await page.getByRole('button', { name: 'Changes' }).first().click();
    await expect(page.getByText(/Sending changes to the agent in \d+s/)).toBeVisible();
    await page.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('changes_requested');
});

test('a person approves a pass with nothing marked', async ({ page }) => {
    const { review, api } = await seedReview('Empty pass');
    await page.goto(path(review.review_url));
    await page.getByRole('button', { name: 'Approve' }).first().click();
    // A decision waits a few seconds so it can be taken back; let it run out.
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status, { timeout: 15_000 }).toBe('approved');
});

test('a removed mark comes back with Undo, and a decision can be called off', async ({ page }) => {
    const { review, api } = await seedReview('Second thoughts');

    await page.goto(path(review.review_url));
    await outline(page);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Tighten the gap under the hero.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    await page.getByRole('button', { name: 'Remove' }).first().click();
    await expect(page.getByText('Removed M1')).toBeVisible();
    await expect(page.getByText('Tighten the gap under the hero.')).toHaveCount(0);
    await page.getByRole('button', { name: 'Undo' }).click();
    await expect(page.getByText('Tighten the gap under the hero.').first()).toBeVisible();

    await page.getByRole('button', { name: 'Approve' }).first().click();
    await expect(page.getByText(/Approving in \d+s/)).toBeVisible();
    await page.getByRole('button', { name: 'Undo' }).click();
    await page.waitForTimeout(9_000);
    expect((await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('pending');
});

test('the review answers to the keyboard', async ({ page }) => {
    const { review, api } = await seedReview('Keys');

    await page.goto(path(review.review_url));
    await outline(page);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Saved with the keyboard.');
    await note.getByPlaceholder(/Be specific/).press(process.platform === 'darwin' ? 'Meta+Enter' : 'Control+Enter');
    await expect(note).toBeHidden();

    await page.keyboard.press('j');
    await expect(page.locator('[id^="fb-mark-"]').first()).toHaveClass(/ring-key/);

    await page.keyboard.press('a');
    await expect(page.getByText(/Approving in \d+s/)).toBeVisible();
    await page.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('approved');
});

test('clicking a mark opens it beside the shot', async ({ page }) => {
    const { review } = await seedReview('Open a mark');

    await page.goto(path(review.review_url));
    await outline(page);
    let note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('The headline crowds the nav.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    // A click without a drag drops a spot: that one opens too.
    const shot = page.getByRole('img', { name: 'Screenshot 1' });
    const box = (await shot.boundingBox())!;
    await page.mouse.click(box.x + box.width * 0.8, box.y + box.height * 0.8);
    note = page.getByRole('dialog', { name: /Leave a note/ });
    await note.getByPlaceholder(/Be specific/).fill('Price needs a period.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    const m1 = page.getByRole('dialog', { name: 'Mark M1' });
    await page.getByRole('button', { name: 'Open mark M1' }).first().click();
    await expect(m1).toBeVisible();
    await expect(m1.getByText('The headline crowds the nav.')).toBeVisible();
    await expect(page).toHaveURL(/[?&]mark=\d+/);

    await page.keyboard.press('Escape');
    await expect(m1).toBeHidden();

    await page.getByRole('button', { name: 'Open mark M2' }).first().click();
    const m2 = page.getByRole('dialog', { name: 'Mark M2' });
    await expect(m2.getByText('Price needs a period.')).toBeVisible();

    // The card in the list opens its mark as well, and J steps along.
    await page.locator('[id^="fb-mark-"]').filter({ hasText: 'The headline crowds the nav.' }).getByText('The headline crowds the nav.').click();
    await expect(m1).toBeVisible();
    await expect(m2).toBeHidden();
    await page.keyboard.press('j');
    await expect(m2).toBeVisible();

    // A click on the shot puts the open mark away before it marks anything.
    await page.mouse.click(box.x + box.width * 0.05, box.y + box.height * 0.95);
    await expect(m2).toBeHidden();
    await expect(page.getByRole('dialog', { name: /Leave a note/ })).toBeHidden();
});

test('the decision bar sits under the thumb on a phone @phone', async ({ page }) => {
    const { review } = await seedReview('On the go');
    await page.goto(path(review.review_url));

    await expect(page.getByRole('button', { name: 'Approve' }).last()).toBeInViewport();
    await expect(page.getByRole('button', { name: 'Changes' }).last()).toBeInViewport();
});
