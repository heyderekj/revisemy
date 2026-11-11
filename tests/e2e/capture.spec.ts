import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { path, ROOT, seedReview } from './support/app';

test('matching named screenshots can be compared across passes', async ({ page }) => {
    const { api } = await seedReview();
    const image = 'data:image/png;base64,' + readFileSync(resolve(ROOT, 'tests/e2e/fixtures/shot.png')).toString('base64');
    const previous = await api.post('/api/reviews', {
        data: {
            title: 'Previous pass',
            images: [image, image],
            image_details: [{ state: 'Menu closed' }, { state: 'Menu open' }],
        },
    });
    expect(previous.status()).toBe(201);
    const first = await previous.json();
    const response = await api.post('/api/reviews', {
        data: {
            title: 'Current pass',
            parent_id: first.id,
            images: [image, image],
            image_details: [{ state: 'Menu open' }, { state: 'Form errors' }],
        },
    });
    expect(response.status()).toBe(201);
    const current = await response.json();
    expect(current.screenshots[0].comparison.url).toBe(first.screenshots[1].url);
    expect(current.screenshots[1].comparison).toBeNull();

    await page.goto(path(current.review_url));
    await expect(page.getByRole('button', { name: 'Screenshot: Menu open' })).toBeVisible();
    await page.getByRole('button', { name: 'Compare with previous pass' }).click();
    await expect(page.getByRole('img', { name: 'Previous pass screenshot' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'This pass screenshot' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'Screenshot 1' })).toBeHidden();
    await page.getByRole('button', { name: 'Back to screenshot' }).click();
    await expect(page.getByRole('img', { name: 'Screenshot 1' })).toBeVisible();
});
