import { expect, test } from '@playwright/test';
import { PDFArray, PDFDict, PDFDocument, PDFHexString, PDFName, StandardFonts } from 'pdf-lib';
import { path, seedReview } from './support/app';

const original = 'Checkout policy for the wholesale dashboard requires an account. Pay within 30 days.';
const updated = original.replace('30 days', '45 days');

async function deck(revised = false) {
    const pdf = await PDFDocument.create();
    const font = await pdf.embedFont(StandardFonts.Helvetica);
    const order = Array.from({ length: 30 }, (_, index) => index + 1);
    if (revised) order.splice(3, 0, ...order.splice(17, 1));
    for (const number of order) {
        const page = pdf.addPage([720, 480]);
        page.drawText('Review slide ' + number, { x: 36, y: 380, size: 34, font });
        page.drawText(number === 18 ? (revised ? updated : original) : 'Notes for section ' + number, { x: 36, y: 305, size: 15, font });
    }
    return Buffer.from(await pdf.save());
}

test('a 30-page PDF preserves a text mark through export and a moved, revised slide', async ({ page }) => {
    test.setTimeout(60_000);
    const { api } = await seedReview();
    const input = await deck();
    const response = await api.post('/api/reviews', {
        data: { title: 'Wholesale deck', pdf: input.toString('base64') },
    });
    expect(response.status(), await response.text()).toBe(201);
    const review = await response.json();
    expect(review.screenshots).toHaveLength(30);
    expect(review.screenshots[17].meta.page).toBe(18);

    await page.goto(path(review.review_url));
    const search = page.getByRole('searchbox', { name: 'Find in PDF' });
    await search.fill('wholesale dashboard');
    await search.press('Enter');
    await page.getByRole('button', { name: /Page 18.*wholesale dashboard/ }).click();
    await expect(page.getByRole('spinbutton', { name: 'PDF page number' })).toHaveValue('18');
    const line = page.locator('.textLayer span').filter({ hasText: original });
    await expect(line).toBeVisible();
    await line.evaluate((element) => {
        const text = element.firstChild!;
        const range = document.createRange();
        const start = text.textContent!.indexOf('Pay within');
        range.setStart(text, start);
        range.setEnd(text, text.textContent!.length);
        const selection = window.getSelection()!;
        selection.removeAllRanges();
        selection.addRange(range);
    });
    await page.getByRole('button', { name: 'Suggest replacement' }).click();
    await expect(page.getByRole('dialog', { name: 'Leave a note' })).toContainText('Pay within 30 days.');
    await page.getByRole('textbox', { name: 'Suggested copy' }).fill('Pay within 45 days.');
    await page.getByRole('button', { name: 'Save mark' }).click();
    await expect(page.getByRole('dialog', { name: 'Leave a note' })).toBeHidden();
    const refreshed = await (await api.get('/api/reviews/' + review.id)).json();
    expect(refreshed.screenshots[17].pins[0]).toMatchObject({
        suggested_copy: 'Pay within 45 days.',
        pdf_anchor: { page: 18, kind: 'replace', quote: 'Pay within 30 days.' },
    });

    const download = await page.request.get(path(review.review_url) + '/annotated.pdf');
    expect(download.status(), await download.text()).toBe(200);
    const annotated = await PDFDocument.load(await download.body());
    expect(annotated.getPageCount()).toBe(30);
    const annotations = annotated.getPage(17).node.lookup(PDFName.of('Annots'), PDFArray);
    expect(annotations.size()).toBe(1);
    const annotation = annotations.lookup(0, PDFDict);
    expect(annotation.get(PDFName.of('Subtype'))?.toString()).toBe('/Highlight');
    expect(annotation.lookup(PDFName.of('Contents'), PDFHexString).decodeText()).toContain('Pay within 45 days.');
    const stored = await page.request.get(path(review.review_url) + '/original.pdf');
    expect(await stored.body()).toEqual(input);

    const followUp = await api.post('/api/reviews', {
        data: { title: 'Wholesale deck revision', parent_id: review.id, pdf: (await deck(true)).toString('base64') },
    });
    expect(followUp.status(), await followUp.text()).toBe(201);
    const revision = await followUp.json();
    expect(revision.screenshots[3].comparison).toMatchObject({
        previous_page: 18,
        text_change: { before: '30', after: '45' },
    });
    await page.goto(path(revision.review_url));
    const jump = page.getByRole('spinbutton', { name: 'PDF page number' });
    await jump.fill('4');
    await jump.press('Enter');
    await page.getByRole('button', { name: 'Compare with previous pass' }).click();
    await expect(page.getByText('Previous pass · Page 18')).toBeVisible();
    await expect(page.getByText('This pass · Page 4')).toBeVisible();
    await expect(page.getByLabel('Changed text')).toContainText('45');
});
