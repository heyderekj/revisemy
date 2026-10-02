import { expect, test } from '@playwright/test';
import { path, seedReview } from './support/app';

test('what the agent resolved waits on the board until a person verifies it', async ({ page }) => {
    const { review, api } = await seedReview('Board pass');
    page.on('dialog', (dialog) => dialog.accept());

    // A mark, then changes requested: the human half of the loop.
    await page.goto(path(review.review_url));
    const shot = page.getByRole('img', { name: 'Screenshot 1' });
    const box = (await shot.boundingBox())!;
    await page.mouse.click(box.x + box.width * 0.5, box.y + box.height * 0.5);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Button label is vague.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();
    await page.getByRole('button', { name: 'Changes' }).first().click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('changes_requested');

    // The agent's half: it resolves the mark.
    const payload = await (await api.get(`/api/reviews/${review.id}`)).json();
    const mark = payload.work_packets.pins[0];
    const resolved = await api.post(`/api/reviews/${review.id}/marks/resolve`, {
        data: { marks: [{ id: mark.id, status: 'resolved', note: 'Renamed it to "Start free trial".' }] },
    });
    expect(resolved.ok(), await resolved.text()).toBeTruthy();

    // Back to the person: the board shows it resolved, and the review page lets them verify it.
    await page.goto(path(review.board_url));
    await expect(page.getByText('Button label is vague.').first()).toBeVisible();

    await page.goto(path(review.review_url));
    await page.getByRole('button', { name: /^Verify all/ }).click();
    await expect
        .poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).loop.verified_count)
        .toBe(1);
});
