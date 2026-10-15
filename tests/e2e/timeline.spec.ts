import { expect, test } from '@playwright/test';
import { path, seedReview } from './support/app';

test('a mark keeps a timeline of who did what, for the owner and a guest', async ({ page }) => {
    const { review, api } = await seedReview('Timeline');
    page.on('dialog', (dialog) => dialog.accept());

    // A person marks it and asks for changes.
    await page.goto(path(review.review_url));
    const box = (await page.getByRole('img', { name: 'Screenshot 1' }).boundingBox())!;
    await page.mouse.click(box.x + box.width * 0.5, box.y + box.height * 0.5);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Button label is vague.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();
    await page.getByRole('button', { name: 'Changes' }).first().click();
    await page.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('changes_requested');

    // The agent starts, resolves with a note and a reply, then replies again.
    const payload = await (await api.get(`/api/reviews/${review.id}`)).json();
    const mark = payload.work_packets.pins[0];
    const resolve = (data: object) => api.post(`/api/reviews/${review.id}/marks/resolve`, { data: { marks: [{ id: mark.id, ...data }] } });
    const started = await resolve({ status: 'in_progress' });
    expect(started.ok(), await started.text()).toBeTruthy();
    const resolved = await resolve({ status: 'resolved', note: 'Renamed it to "Start free trial".', reply: 'Matched the label on the pricing page.' });
    expect(resolved.ok(), await resolved.text()).toBeTruthy();
    const replied = await api.post(`/api/reviews/${review.id}/marks/reply`, {
        data: { replies: [{ id: mark.id, body: 'Say if the hover state needs the same.' }] },
    });
    expect(replied.ok(), await replied.text()).toBeTruthy();

    // The board's mark sheet reads it back in order, the agent in its round pill.
    await page.goto(path(review.board_url));
    await page.getByText('Button label is vague.').first().click();
    const sheet = page
        .locator('div')
        .filter({ has: page.getByText('What happened', { exact: true }) })
        .filter({ has: page.getByPlaceholder('Reply…') })
        .last();
    await expect(sheet).toBeVisible();
    const timeline = sheet.locator('ol');
    const agentPill = timeline.getByText('Agent', { exact: true }).first();
    await expect(agentPill).toBeVisible();
    await expect(agentPill).toHaveClass(/rounded-full/);
    await expect(agentPill.locator('svg')).toBeVisible();
    await expect(timeline.getByText('resolved it', { exact: true })).toBeVisible();
    await expect(timeline.getByText('Renamed it to "Start free trial".')).toBeVisible();
    await expect(timeline.getByText('Matched the label on the pricing page.')).toBeVisible();
    await expect(timeline.getByText('Say if the hover state needs the same.')).toBeVisible();
    await expect(timeline.getByText('started', { exact: true })).toBeVisible();

    // "Talk only" folds the bare steps away and keeps the conversation.
    await sheet.getByRole('button', { name: 'Talk only' }).click();
    await expect(timeline.getByText('started', { exact: true })).toBeHidden();
    await expect(timeline.getByText('Say if the hover state needs the same.')).toBeVisible();
    await sheet.getByRole('button', { name: 'Everything' }).click();
    await expect(timeline.getByText('started', { exact: true })).toBeVisible();

    // The owner answers from the sheet.
    await sheet.getByPlaceholder('Reply…').fill('Yes, give the hover the same label.');
    await sheet.getByRole('button', { name: 'Reply' }).click();
    await expect(timeline.getByText('Yes, give the hover the same label.')).toBeVisible();
    await expect(timeline.locator('li', { hasText: 'Yes, give the hover the same label.' }).getByText('You', { exact: true })).toBeVisible();

    // A guest sees the same timeline, with the owner named as such.
    const guest = (await (await api.get(`/api/reviews/${review.id}`)).json()).guest_share_url;
    await page.goto(path(guest));
    const card = page.locator(`#fb-mark-${mark.id}`);
    await expect(card.getByText('Yes, give the hover the same label.')).toBeVisible();
    const guestTimeline = card.locator('ol');
    await expect(guestTimeline.locator('li', { hasText: 'Yes, give the hover the same label.' }).getByText('Owner', { exact: true })).toBeVisible();
    await expect(guestTimeline.locator('li', { hasText: 'Say if the hover state needs the same.' }).getByText('Agent', { exact: true })).toBeVisible();
    await expect(guestTimeline.getByText('You', { exact: true })).toHaveCount(0);
});
