import { GlobalWorkerOptions, TextLayer, getDocument } from 'pdfjs-dist';
import worker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import './pdf-viewer.css';

GlobalWorkerOptions.workerSrc = worker;
const documents = new Map();

function documentFor(url) {
    if (!documents.has(url)) {
        const task = getDocument({
            url,
            cMapUrl: '/pdf-assets/cmaps/',
            cMapPacked: true,
            standardFontDataUrl: '/pdf-assets/standard_fonts/',
            wasmUrl: '/pdf-assets/wasm/',
            isEvalSupported: false,
            maxImageSize: 64_000_000,
        });
        const entry = { task, promise: task.promise, query: '', results: [] };
        documents.set(url, entry);
        task.promise.catch(() => documents.delete(url));
        // Two passes can be open for comparison. Evict older documents.
        if (documents.size > 3) {
            const oldest = documents.keys().next().value;
            documents.get(oldest).task.destroy().catch(() => {});
            documents.delete(oldest);
        }
    }
    return documents.get(url);
}

export function mount(url, number, host, callbacks) {
    const entry = documentFor(url);
    const canvas = host.querySelector('canvas');
    let layer = host.querySelector('.textLayer');
    const controller = new AbortController();
    let renderTask;
    let textLayer;
    let frame;
    let currentWidth = 0;
    let renderVersion = 0;

    async function render() {
        const width = host.clientWidth;
        if (controller.signal.aborted || width === 0 || width === currentWidth) return;
        currentWidth = width;
        const version = ++renderVersion;
        renderTask?.cancel();
        textLayer?.cancel();
        try {
            const page = await (await entry.promise).getPage(number);
            if (controller.signal.aborted || version !== renderVersion) return;
            const base = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: width / base.width });
            const density = Math.min(window.devicePixelRatio || 1, 2, Math.sqrt(12_000_000 / (viewport.width * viewport.height)));
            canvas.width = Math.ceil(viewport.width * density);
            canvas.height = Math.ceil(viewport.height * density);
            host.style.setProperty('--total-scale-factor', viewport.scale * page.userUnit);
            host.style.setProperty('--scale-round-x', '1px');
            host.style.setProperty('--scale-round-y', '1px');
            const rendering = page.render({
                canvas,
                viewport,
                transform: [density, 0, 0, density, 0, 0],
                background: '#fff',
            });
            renderTask = rendering;
            const nextLayer = layer.cloneNode(false);
            layer.replaceWith(nextLayer);
            layer = nextLayer;
            textLayer = new TextLayer({ textContentSource: page.streamTextContent(), container: layer, viewport });
            await Promise.all([rendering.promise, textLayer.render()]);
            if (!controller.signal.aborted && renderTask === rendering) callbacks.ready();
        } catch (error) {
            if (!controller.signal.aborted && version === renderVersion && error.name !== 'RenderingCancelledException' && error.name !== 'AbortException') callbacks.failed();
        }
    }

    const observer = new ResizeObserver(() => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(render);
    });
    observer.observe(host);
    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || !layer.contains(selection.anchorNode) || !layer.contains(selection.focusNode)) {
            callbacks.selection(null);
            return;
        }
        const quote = selection.toString().replace(/\s+/g, ' ').trim();
        const page = host.getBoundingClientRect();
        const rects = Array.from(selection.getRangeAt(0).getClientRects())
            .filter((box) => box.width > 0.5 && box.height > 0.5)
            .slice(0, 60)
            .map((box) => ({
                x: (box.left - page.left) / page.width,
                y: (box.top - page.top) / page.height,
                w: box.width / page.width,
                h: box.height / page.height,
            }));
        callbacks.selection(quote && rects.length ? { quote, rects } : null);
    }, { signal: controller.signal });

    return () => {
        controller.abort();
        observer.disconnect();
        cancelAnimationFrame(frame);
        renderTask?.cancel();
        textLayer?.cancel();
    };
}

export function searchState(url) {
    const { query, results } = documentFor(url);
    return { query, results };
}

export async function search(url, query) {
    const entry = documentFor(url);
    entry.query = query;
    entry.results = [];
    const needle = query.trim().toLocaleLowerCase();
    if (!needle) return [];
    const pdf = await entry.promise;
    entry.text ??= (async () => {
        const pages = [];
        for (let index = 1; index <= pdf.numPages; index++) {
            const page = await pdf.getPage(index);
            const content = await page.getTextContent();
            pages.push(content.items.map((item) => item.str ?? '').join(' ').replace(/\s+/g, ' '));
        }
        return pages;
    })();
    const pages = await entry.text;
    const results = [];
    pages.forEach((text, index) => {
        const at = text.toLocaleLowerCase().indexOf(needle);
        if (at >= 0) {
            results.push({ page: index + 1, excerpt: text.slice(Math.max(0, at - 35), at + needle.length + 65).trim() });
        }
    });
    if (entry.query === query) entry.results = results;
    return results;
}
