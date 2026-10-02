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

test('the decision bar sits under the thumb on a phone @phone', async ({ page }) => {
    const { review } = await seedReview('On the go');
    await page.goto(path(review.review_url));

    await expect(page.getByRole('button', { name: 'Approve' }).last()).toBeInViewport();
    await expect(page.getByRole('button', { name: 'Changes' }).last()).toBeInViewport();
});
