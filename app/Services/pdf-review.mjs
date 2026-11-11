import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { createCanvas } from '@napi-rs/canvas';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';

const [action, path, pageNumber, width, height] = process.argv.slice(2);
const bytes = new Uint8Array(await readFile(path));
const assets = (folder) => fileURLToPath(new URL('../../node_modules/pdfjs-dist/' + folder + '/', import.meta.url));
const task = getDocument({
    data: bytes.slice(),
    cMapUrl: assets('cmaps'),
    cMapPacked: true,
    standardFontDataUrl: assets('standard_fonts'),
    wasmUrl: assets('wasm'),
    useSystemFonts: false,
    isEvalSupported: false,
    maxImageSize: 64_000_000,
    stopAtErrors: true,
    verbosity: 0,
});

try {
    const document = await task.promise;
    if (action === 'inspect') {
        const pages = [];
        const limit = Number(pageNumber);
        for (let number = 1; number <= Math.min(document.numPages, limit); number++) {
            const page = await document.getPage(number);
            const size = page.getViewport({ scale: 1 });
            const text = (await page.getTextContent()).items.map((item) => item.str ?? '').join(' ').replace(/\s+/g, ' ').trim();
            pages.push({ width: size.width, height: size.height, text: text.slice(0, 24_000) });
            page.cleanup();
        }
        process.stdout.write(JSON.stringify({ count: document.numPages, pages }));
    } else if (action === 'render') {
        const page = await document.getPage(Number(pageNumber));
        const viewport = page.getViewport({ scale: 1 });
        const canvas = createCanvas(Number(width), Number(height));
        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport,
            transform: [canvas.width / viewport.width, 0, 0, canvas.height / viewport.height, 0, 0],
            background: '#fff',
        }).promise;
        process.stdout.write(canvas.toBuffer('image/png'));
    } else if (action === 'export') {
        const { PDFDocument, PDFHexString, PDFName } = await import('pdf-lib');
        const pdf = await PDFDocument.load(bytes, { updateMetadata: false });
        const marks = JSON.parse(await readFile(pageNumber, 'utf8'));
        const viewports = new Map();
        for (const mark of marks) {
            if (!viewports.has(mark.page)) {
                viewports.set(mark.page, (await document.getPage(mark.page)).getViewport({ scale: 1 }));
            }
            const viewport = viewports.get(mark.page);
            const point = (x, y) => viewport.convertToPdfPoint(x * viewport.width, y * viewport.height);
            const selection = mark.anchor?.rects;
            const boxes = selection?.length ? selection : mark.area ? [mark.area] : [];
            const quads = boxes.map(({ x, y, w, h }) => [
                ...point(x, y), ...point(x + w, y), ...point(x, y + h), ...point(x + w, y + h),
            ]).flat();
            const xs = quads.filter((_, i) => i % 2 === 0);
            const ys = quads.filter((_, i) => i % 2 === 1);
            const [x, y] = point(mark.x, mark.y);
            const type = selection?.length ? (mark.anchor.kind === 'strikeout' ? 'StrikeOut' : 'Highlight') : boxes.length ? 'Square' : 'Text';
            const rect = quads.length ? [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)] : [x, y, x + 18, y + 18];
            const page = pdf.getPage(mark.page - 1);
            const contents = [mark.body, mark.anchor?.quote && 'Selected text: ' + mark.anchor.quote, mark.replacement && 'Suggested text: ' + mark.replacement].filter(Boolean).join('\n\n');
            const annotation = pdf.context.obj({
                Type: 'Annot', Subtype: type, Rect: rect,
                P: page.ref, T: PDFHexString.fromText('ReviseMy · M' + mark.number),
                Contents: PDFHexString.fromText(contents), C: [1, 0.76, 0.12], F: 4,
                ...(selection?.length ? { QuadPoints: quads } : {}),
                ...(type === 'Square' ? { BS: { W: 1.5, S: PDFName.of('S') } } : {}),
            });
            page.node.addAnnot(pdf.context.register(annotation));
        }
        process.stdout.write(await pdf.save({ updateFieldAppearances: false }));
    } else {
        throw new Error('Unknown PDF operation.');
    }
} finally {
    await task.destroy();
}
