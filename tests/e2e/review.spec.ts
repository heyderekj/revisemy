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
    const markType = note.getByRole('combobox', { name: 'Kind of mark' });
    const body = note.getByPlaceholder(/Be specific/);
    await body.fill('Could we make more room for the navigation?');
    await expect(markType).toHaveValue('question');
    await markType.selectOption('must-fix');
    await body.fill('Could we make more room for the headline?');
    await expect(markType).toHaveValue('must-fix');
    await body.fill('The headline crowds the nav.');
    const copy = note.getByRole('textbox', { name: 'Suggested copy' });
    await expect(copy).toBeHidden();
    await note.getByRole('button', { name: 'Add copy' }).click();
    await expect(copy).toBeFocused();
    await copy.fill('A shorter headline');

    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();

    await expect(page.getByText('The headline crowds the nav.').first()).toBeVisible();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).work_packets.pins[0]?.suggested_copy).toBe('A shorter headline');


    await page.getByRole('button', { name: 'More review actions' }).click();
    await page.getByRole('menuitem', { name: 'Add a note' }).click();
    await page.getByRole('textbox', { name: 'Decision note' }).fill('Keep the navigation visible.');
    await page.getByRole('button', { name: 'Done', exact: true }).click();

    await page.getByRole('button', { name: 'More review actions' }).click();
    await page.getByRole('menuitem', { name: 'Request changes' }).click();
    await expect(page.getByText(/Sending changes to the agent in \d+s/)).toBeVisible();
    await page.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('changes_requested');
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).decision_note).toBe('Keep the navigation visible.');
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

    await page.locator('[data-opens-mark]').first().click();
    await page.getByRole('dialog', { name: 'Mark M1' }).getByRole('button', { name: 'Remove' }).click();
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

test('the screenshot picker lives with the preview and keeps keyboard focus', async ({ page }) => {
    const { review } = await seedReview('Two screenshots', { shots: 2 });
    await page.goto(path(review.review_url));

    const picker = page.getByRole('group', { name: 'Preview controls' }).getByRole('button', { name: /Screenshot: Shot/ });
    await expect(page.locator('header').getByRole('button', { name: /Screenshot: Shot/ })).toHaveCount(0);
    await picker.press('Enter');
    await expect(page.getByRole('menuitem', { name: 'Shot 1' })).toHaveAttribute('aria-current', 'true');
    await page.getByRole('menuitem', { name: 'Shot 2' }).click();
    await expect(page.getByRole('img', { name: 'Screenshot 2' })).toBeVisible();
    await expect(picker).toBeFocused();
    await expect(picker).toHaveAccessibleName('Screenshot: Shot 2');
});

test('the decision bar sits under the thumb on a phone @phone', async ({ page }) => {
    const { review } = await seedReview('On the go');
    await page.goto(path(review.review_url));

    await expect(page.getByRole('button', { name: 'Approve' }).last()).toBeInViewport();
    await expect(page.getByRole('button', { name: 'More review actions' })).toBeInViewport();
});

test('a public submission shares a working guest link and guests can react', async ({ page, browser, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto('/new?kind=images');
    await page.locator('input[name="images[]"]').setInputFiles('tests/e2e/fixtures/shot.png');
    await page.getByRole('button', { name: 'Start the review', exact: true }).click();
    await expect(page).toHaveURL(/\/r\/[\w]+$/, { timeout: 20_000 });

    await expect(page.getByRole('button', { name: 'Feedback', exact: true })).toHaveAttribute('aria-expanded', 'true');
    await expect.poll(() => page.getByRole('img', { name: 'Screenshot 1' }).evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
    await outline(page);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Give the headline more room.');
    await note.getByRole('button', { name: 'Save mark' }).click();
    await expect(note).toBeHidden();
    await page.locator('[data-mark-id]').first().click();
    const thread = page.getByRole('dialog', { name: 'Mark M1' });
    await thread.getByRole('button', { name: 'Comment', exact: true }).click();
    await thread.getByPlaceholder('Add a comment…').fill('Try a shorter subtitle.');
    await thread.getByRole('button', { name: 'Post', exact: true }).click();
    await expect(thread.getByText('Try a shorter subtitle.')).toBeVisible();
    await thread.getByRole('button', { name: 'Close mark' }).last().click();

    await page.getByRole('button', { name: 'Share', exact: true }).click();
    await page.getByRole('button', { name: 'Copy link', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Guest link copied' })).toBeVisible();
    const guestLink = await page.evaluate(() => navigator.clipboard.readText());
    expect(guestLink).not.toBe(page.url());

    const guestContext = await browser.newContext();
    try {
        const guest = await guestContext.newPage();
        const response = await guest.goto(guestLink);
        expect(response?.ok()).toBeTruthy();
        const shot = guest.getByRole('img', { name: 'Screenshot 1' });
        await expect.poll(() => shot.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
        await expect(guest.getByRole('button', { name: 'Share', exact: true })).toHaveCount(0);
        await guest.locator('[data-mark-id]').first().click();
        const guestThread = guest.getByRole('dialog', { name: 'Mark M1' });
        await guestThread.getByRole('button', { name: 'Add reaction' }).click();
        await guestThread.getByRole('menuitem', { name: 'Like', exact: true }).click();
        await expect(guestThread.getByRole('button', { name: 'Like (1)' })).toHaveAttribute('aria-pressed', 'true');

        await guest.reload();
        await expect(guestThread.getByRole('button', { name: 'Like (1)' })).toHaveAttribute('aria-pressed', 'true');
        await guestThread.getByRole('button', { name: 'Like (1)' }).click();
        await expect(guestThread.getByRole('button', { name: 'Like (1)' })).toHaveCount(0);

        await guestThread.getByRole('button', { name: 'Close mark' }).last().click();
        const box = (await shot.boundingBox())!;
        await guest.mouse.click(box.x + box.width * 0.75, box.y + box.height * 0.75);
        const suggestion = guest.getByRole('dialog', { name: 'Suggest a change' });
        await suggestion.getByPlaceholder('Your name').fill('Alex');
        await suggestion.getByPlaceholder(/Be specific/).fill('Use a shorter label.');
        await suggestion.getByRole('button', { name: 'Suggest', exact: true }).click();
        await expect(suggestion).toBeHidden();
        await expect(guest.locator('[data-finding]').first()).toBeVisible();

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'New link', exact: true }).click();
        await expect(page.getByRole('status').filter({ hasText: 'New guest link ready' })).toBeVisible();
        const newLink = await page.getByLabel('Guest link', { exact: true }).inputValue();
        expect(newLink).not.toBe(guestLink);
        expect((await guest.goto(guestLink))?.status()).toBe(404);
        expect((await guest.goto(newLink))?.ok()).toBeTruthy();
    } finally {
        await guestContext.close();
    }
});

test('a personal feedback link opens the requested mark and puts the reply on its thread', async ({ page, browser }) => {
    const { review } = await seedReview('Checkout feedback');
    await page.goto(path(review.review_url));
    await outline(page);
    const note = page.getByRole('dialog', { name: 'Leave a note' });
    await note.getByPlaceholder(/Be specific/).fill('Check the checkout button.');
    await note.getByRole('button', { name: 'Save mark' }).click();

    await page.getByRole('button', { name: 'Open mark M1' }).first().click();
    await page.getByRole('dialog', { name: 'Mark M1' }).getByRole('button', { name: 'Request feedback' }).click();
    const request = page.getByRole('dialog', { name: 'Request feedback' });
    await expect(request.getByText('Mark M1', { exact: true })).toBeVisible();
    await expect.poll(async () => {
        const bounds = await request.boundingBox();
        return bounds ? Math.round(bounds.x + bounds.width) : 0;
    }).toBe(page.viewportSize()!.width);
    await request.getByLabel('Reviewer name').fill('Maya');
    await request.getByLabel('What should they look at?').fill('Does this label tell you what happens next?');
    await request.getByRole('button', { name: 'Create personal link' }).click();
    const link = await request.getByRole('textbox', { name: /Personal feedback link/ }).inputValue();

    const guest = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    try {
        await guest.goto(link);
        await expect(guest.getByRole('img', { name: /focused on mark M1/ })).toBeVisible();
        await expect(guest.getByText('Does this label tell you what happens next?')).toBeVisible();
        await guest.getByLabel('Your name').fill('Maya');
        await guest.getByLabel('Your feedback').fill('I would use Continue to payment.');
        await guest.getByRole('button', { name: 'Send feedback' }).click();
        await expect(guest.getByText('Feedback sent')).toBeVisible();

        const desktop = page.viewportSize()!;
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(path(review.review_url) + '?feedback=1');
        await expect(request.getByText('Replied')).toBeVisible();
        await expect.poll(async () => Math.round((await request.boundingBox())?.width ?? 0)).toBe(390);
        await request.locator('summary').filter({ hasText: 'Maya' }).click();
        await expect(request.getByText('I would use Continue to payment.')).toBeVisible();

        await request.getByRole('button', { name: 'Close feedback panel' }).click();
        await page.setViewportSize(desktop);
        await page.getByRole('button', { name: 'Open mark M1' }).first().click();
        await expect(page.getByRole('dialog', { name: 'Mark M1' }).getByText('I would use Continue to payment.')).toBeVisible();
    } finally {
        await guest.close();
    }
});

test('a group request creates a separate link for each reviewer', async ({ page }) => {
    const { review } = await seedReview('Group feedback');
    await page.goto(path(review.review_url) + '?feedback=1');
    const drawer = page.getByRole('dialog', { name: 'Request feedback' });
    await drawer.getByText('A group', { exact: true }).click();
    await drawer.getByLabel('Reviewer names').fill('Maya\nJordan');
    await drawer.getByLabel('What should they look at?').fill('Is the main action clear?');
    await drawer.getByRole('button', { name: 'Create personal links' }).click();

    await expect(drawer.getByText('0 of 2 replied')).toBeVisible();
    const maya = await drawer.getByRole('textbox', { name: 'Personal feedback link for Maya' }).inputValue();
    await drawer.locator('summary').filter({ hasText: 'Jordan' }).click();
    const jordan = await drawer.getByRole('textbox', { name: 'Personal feedback link for Jordan' }).inputValue();
    expect(maya).not.toBe(jordan);
});
