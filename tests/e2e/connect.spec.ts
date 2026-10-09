import { expect, test } from '@playwright/test';

test('the homepage shows how to connect before anyone gets a token', async ({ page }) => {
    await page.goto('/#setup');

    const hub = page.locator('#setup');
    await expect(hub.getByRole('heading', { name: 'Connect Claude' })).toBeVisible();
    await expect(hub.getByText('/mcp/revisemy').first()).toBeVisible();

    await hub.getByRole('tab', { name: /Cursor/ }).click();
    await expect(hub.getByRole('link', { name: /Add to Cursor/ })).toHaveAttribute('href', /^cursor:\/\//);

    await hub.getByRole('tab', { name: /Muse/ }).click();
    await hub.getByRole('button', { name: 'Get a try token' }).first().click();
    await expect(hub.getByText('Build a custom connector to ReviseMy and save this credential.')).toBeVisible();
    await expect(hub.getByText('Waiting for your assistant’s first call')).toBeVisible();
});
