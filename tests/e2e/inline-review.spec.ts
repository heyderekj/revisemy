import { expect, Page, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { mcp, ROOT, seedReview } from './support/app';

type HostMessage = { copy: 'older' | 'newer'; method: string; params: any };

/**
 * The inline review as an MCP Apps host shows it: the real ui:// resource,
 * twice in one chat (two get_review calls), inside a fake host page
 * (fixtures/mcp-host.html) that answers the app and logs what it sends.
 * Both are served by Playwright, on the app's origin, so the copies share
 * a BroadcastChannel and tools/call reaches the real MCP server.
 */
async function openInHost(page: Page, title: string, { silentMessage = false, elements = false } = {}) {
    const { api, token, review } = await seedReview(title, { elements });

    // A mark, so asking for changes has something to send.
    const shot = (await (await api.get(`/api/reviews/${review.id}`)).json()).screenshots[0];
    const marked = await mcp(api, 'tools/call', {
        name: 'add_mark',
        arguments: { review_id: review.id, screenshot_id: shot.id, x: 0.5, y: 0.5, severity: 'must-fix', body: 'Button label is vague.', suggested_copy: 'Start my free trial' },
    });
    expect(marked.isError, JSON.stringify(marked.content)).toBeFalsy();

    const resource = await mcp(api, 'resources/read', { uri: 'ui://revisemy/review-app' });
    const app = resource.contents[0].text as string;

    // Two calls, two snapshots: the second copy is the newer one.
    const older = await mcp(api, 'tools/call', { name: 'get_review', arguments: { id: review.id } });
    const newer = await mcp(api, 'tools/call', { name: 'get_review', arguments: { id: review.id } });
    expect(newer.structuredContent.snapshot_at > older.structuredContent.snapshot_at).toBeTruthy();

    const config = { token, silentMessage, results: { older, newer } };
    const host = readFileSync(resolve(ROOT, 'tests/e2e/fixtures/mcp-host.html'), 'utf8').replace('/*CONFIG*/null', JSON.stringify(config));

    await page.route('**/__e2e/host', (route) => route.fulfill({ contentType: 'text/html', body: host }));
    await page.route('**/__e2e/review-app', (route) =>
        route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body>${app}</body></html>` }),
    );
    await page.goto('/__e2e/host');

    const log = () => page.evaluate(() => (window as any).hostLog as HostMessage[]);
    const sent = async (copy: HostMessage['copy'], method: string) => (await log()).filter((m) => m.copy === copy && m.method === method);

    return { api, review, sent, older: page.frameLocator('#older'), newer: page.frameLocator('#newer') };
}

test('the inline review hands the decision back to the chat', async ({ page }) => {
    const { api, review, sent, older, newer } = await openInHost(page, 'Inline handoff');

    // One live copy: the older one folds away and points down the chat.
    await expect(older.getByText('continues further down the chat')).toBeVisible();
    await expect(newer.getByRole('button', { name: 'Changes' })).toBeVisible();
    await expect(newer.getByText('continues further down the chat')).toHaveCount(0);

    // What the agent should know arrives without starting a turn.
    await expect.poll(async () => (await sent('newer', 'ui/update-model-context')).length).toBeGreaterThan(0);
    const context = (await sent('newer', 'ui/update-model-context')).at(-1)!;
    expect(context.params.structuredContent.review_id).toBe(review.id);
    expect(context.params.content[0].text).toContain('1 open mark');

    // A decision waits with Undo, as on the web; Undo takes it back.
    await newer.getByRole('button', { name: 'Changes' }).click();
    await expect(newer.getByText(/Sending changes in \d+s/)).toBeVisible();
    await newer.getByRole('button', { name: 'Undo' }).click();
    await expect(newer.getByRole('button', { name: 'Changes' })).toBeVisible();
    expect((await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('pending');

    // Deciding says so in the chat, once, as the person.
    await newer.getByRole('button', { name: 'Changes' }).click();
    await newer.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).status).toBe('changes_requested');
    await expect.poll(async () => (await sent('newer', 'ui/message')).length).toBe(1);
    const [message] = await sent('newer', 'ui/message');
    expect(message.params.role).toBe('user');
    expect(message.params.content[0].text).toMatch(/^I asked for changes on Inline handoff, pass 1: 1 mark to fix\./);
    await page.waitForTimeout(1_000);
    expect(await sent('newer', 'ui/message')).toHaveLength(1);
    expect(await sent('older', 'ui/message')).toHaveLength(0);
    await expect(newer.getByText('Tell your agent in the chat to carry on.')).toHaveCount(0);

    // Full screen asks the host for it.
    await newer.getByRole('button', { name: 'Full screen' }).click();
    await expect.poll(async () => (await sent('newer', 'ui/request-display-mode')).map((m) => m.params.mode)).toEqual(['fullscreen']);
    await expect(newer.getByRole('button', { name: 'Back to the chat' })).toBeVisible();
});

test('opening a mark inline shows what the web shows', async ({ page }) => {
    const { newer } = await openInHost(page, 'Inline detail');

    await newer.locator('button', { hasText: /^M1$/ }).first().click();
    const detail = newer.locator('[data-mark-detail]:visible');
    await expect(detail.getByText('Button label is vague.')).toBeVisible();
    await expect(detail.getByText('Suggested copy')).toBeVisible();
    await expect(detail.getByText('Start my free trial')).toBeVisible();
});

test('a host that never takes the message leaves the person a nudge', async ({ page }) => {
    test.slow();
    const { sent, newer } = await openInHost(page, 'Silent host', { silentMessage: true });

    await newer.getByRole('button', { name: 'Changes' }).click();
    await newer.getByRole('button', { name: 'Send now' }).click();
    await expect.poll(async () => (await sent('newer', 'ui/message')).length).toBe(1);
    // The app gives the host eight seconds to answer.
    await expect(newer.getByText('Tell your agent in the chat to carry on.')).toBeVisible({ timeout: 12_000 });
});

test('the inline review marks the element under a click, as the web does', async ({ page }) => {
    const { api, review, sent, newer } = await openInHost(page, 'Inline elements', { elements: true });

    // The frame asks for the shot's elements, then Snap shows.
    await expect(newer.getByRole('button', { name: 'Snap' })).toBeVisible();
    expect((await sent('newer', 'tools/call')).some((m) => m.params.name === 'get_shot_elements')).toBeTruthy();

    // Hover the fixture's #buy button (100,200 200×60 on a 1200×800 shot).
    const shot = newer.locator('[x-ref="overlay"]');
    await shot.scrollIntoViewIfNeeded();
    const box = (await shot.boundingBox())!;
    const x = box.x + (200 / 1200) * box.width;
    const y = box.y + (230 / 800) * box.height;
    await page.mouse.move(x, y);
    await expect(newer.locator('[data-element-hover]')).toContainText('Button · Buy now · 200 × 60');

    // Click: the composer names it and shows what the page says about it.
    await page.mouse.click(x, y);
    const pending = newer.locator('[data-pending-element]');
    await expect(pending.locator('[data-element-chip]')).toContainText('Button');
    await pending.getByText('Inspect').click();
    await expect(pending.locator('[data-element-inspect]')).toContainText('#2563eb');
    await pending.getByRole('button', { name: /Reference Background #2563eb/ }).click();
    await pending.getByText('More properties', { exact: true }).click();
    await pending.getByRole('button', { name: 'Reference Line height 24px' }).click();
    await expect(newer.getByRole('button', { name: /Remove reference to Background/ })).toBeVisible();

    await newer.getByPlaceholder('What should change here?').fill('Optional: make the button more specific.');
    await expect(newer.getByRole('combobox', { name: 'Kind of mark' })).toHaveValue('nit');
    await newer.getByPlaceholder('What should change here?').fill('Make the button say what you get.');
    await newer.getByRole('button', { name: 'Add mark' }).click();

    await expect.poll(async () => (await (await api.get(`/api/reviews/${review.id}`)).json()).work_packets.pins
        .find((p: any) => p.body === 'Make the button say what you get.')).toMatchObject({
        element: { selector: '#buy' },
        element_references: [{ selector: '#buy', property: { label: 'Background', value: '#2563eb' } }, { selector: '#buy', property: { label: 'Line height', value: '24px' } }],
    });
    await expect(newer.locator('[data-element-chip]:visible', { hasText: 'Buy now' }).first()).toBeVisible();
    await newer.locator('button', { hasText: /^M2$/ }).first().click();
    const detail = newer.locator('[data-mark-detail]:visible');
    await detail.locator('[data-element-inspect] > summary').click();
    await expect(detail.getByRole('button', { name: /Reference Background #2563eb/ })).toBeVisible();
});

test('inline Share checks the latest guest link and works without clipboard access', async ({ page, context }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
    });
    const { newer, review, sent } = await openInHost(page, 'Share from chat');

    const share = newer.getByRole('button', { name: 'Share', exact: true });
    await share.click();
    const guestLink = newer.getByRole('textbox', { name: 'Guest link' });
    await expect(guestLink).toBeVisible();
    const previous = await guestLink.inputValue();
    expect(previous).not.toBe(review.review_url);

    await newer.getByRole('button', { name: 'Copy link' }).click();
    await expect(newer.getByText('Copy the selected link to share it.')).toBeVisible();
    await expect(guestLink).toBeFocused();
    expect((await page.request.get(previous)).ok()).toBeTruthy();

    await newer.getByRole('button', { name: 'Manage sharing on the web' }).click();
    await expect.poll(async () => (await sent('newer', 'ui/open-link')).at(-1)?.params.url).toBe(review.review_url + '?share=1');
    await share.click();

    const web = await context.newPage();
    try {
        await web.goto(review.review_url + '?share=1');
        await expect(web.getByRole('button', { name: 'Copy link' })).toBeVisible();
        web.once('dialog', (dialog) => dialog.accept());
        await web.getByRole('button', { name: 'New link' }).click();
        await expect(web.getByText('New guest link ready')).toBeVisible();
    } finally {
        await web.close();
    }

    await share.click();
    await expect(guestLink).not.toHaveValue(previous);
    expect((await page.request.get(await guestLink.inputValue())).ok()).toBeTruthy();
    expect((await page.request.get(previous)).status()).toBe(404);
});

test('an inline reaction also appears on the web review', async ({ page, context }) => {
    const { newer, review } = await openInHost(page, 'React from chat');
    await newer.getByRole('button', { name: 'M1', exact: true }).first().click();
    const detail = newer.locator('[data-mark-detail]:visible');
    await detail.getByRole('textbox', { name: 'Reply on this mark' }).fill('Try the shorter label.');
    await detail.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(detail.getByText('Try the shorter label.')).toBeVisible();

    await detail.getByRole('button', { name: 'Add reaction' }).click();
    await detail.getByRole('button', { name: 'Like', exact: true }).click();
    await expect(detail.getByRole('button', { name: 'Like (1)' })).toHaveAttribute('aria-pressed', 'true');

    const web = await context.newPage();
    try {
        await web.goto(review.review_url);
        await web.locator('[data-mark-id]').first().click();
        const reaction = web.getByRole('dialog', { name: 'Mark M1' }).getByRole('button', { name: 'Like (1)' });
        await expect(reaction).toHaveAttribute('aria-pressed', 'true');
    } finally {
        await web.close();
    }

    await detail.getByRole('button', { name: 'Like (1)' }).click();
    await expect(detail.getByRole('button', { name: 'Like (1)' })).toHaveCount(0);
});


test('the inline board reviews a fix with before and after evidence', async ({ page }) => {
    const { newer, api, review } = await openInHost(page, 'Inline fix review');
    const snapshot = (await (await api.get(`/api/reviews/${review.id}`)).json());
    const mark = snapshot.work_packets.pins[0];
    await mcp(api, 'tools/call', {
        name: 'decide_review',
        arguments: { review_id: review.id, decision: 'changes_requested' },
    });
    const after = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';
    const result = await api.post(`/api/reviews/${review.id}/marks/resolve`, {
        data: { marks: [{ id: mark.id, status: 'resolved', after_image: after }] },
    });
    expect(result.ok(), await result.text()).toBeTruthy();

    await newer.getByRole('button', { name: 'Refresh' }).click();
    await newer.getByRole('button', { name: 'Board · 1' }).click();
    await newer.getByRole('button', { name: 'Review 1 fix' }).click();
    const detail = newer.locator('[data-mark-detail]:visible');
    await detail.getByRole('button', { name: 'Side by side' }).click();
    await expect(detail.getByRole('img', { name: 'Before — marked area' })).toBeVisible();
    await expect(detail.getByRole('img', { name: 'After — submitted screenshot of the fix' })).toBeVisible();
    await detail.getByRole('button', { name: 'Verify & next' }).click();
    await expect(newer.getByText('All ready fixes reviewed.')).toBeVisible();
});
