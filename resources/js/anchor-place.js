// Where a floating panel sits beside the thing it's about: to the right when it
// fits (or when there's more room there), else to the left, centred on it
// vertically, and always inside the window. Shared by the new-mark composer
// and the mark popover on the review page.
window.rmPlaceBeside = function (rect, panelWidth, panelHeight, gap = 12) {
    if (! rect || (rect.width < 1 && rect.height < 1)) {
        return null;
    }

    const spaceRight = window.innerWidth - rect.right;
    const right = spaceRight >= panelWidth + gap || spaceRight >= rect.left;
    let left = right ? rect.right + gap : rect.left - panelWidth - gap;
    let top = rect.top + rect.height / 2 - panelHeight / 2;

    left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));
    top = Math.max(8, Math.min(top, window.innerHeight - panelHeight - 8));

    return { left, top, origin: right ? 'left center' : 'right center' };
};

// Scroll the shot so an element stays in view above a bottom sheet of the
// given height (phones), instead of hiding under it.
window.rmRevealAbove = function (el, sheetHeight) {
    if (! el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const visibleBottom = window.innerHeight - sheetHeight - 12;

    if (rect.top >= 56 && rect.bottom <= visibleBottom) {
        return;
    }

    el.style.scrollMarginBottom = sheetHeight + 24 + 'px';
    el.style.scrollMarginTop = '72px';
    el.scrollIntoView({ behavior: 'smooth', block: rect.height > visibleBottom - 72 ? 'start' : 'nearest', inline: 'nearest' });
};
