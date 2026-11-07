/**
 * Phone card layout for read-only list tables.
 *
 * On narrow screens a wide table forces sideways scrolling, which hides columns and actions.
 * Eligible tables get `table-stack`: each row becomes a card, the first column its title and every
 * other cell is shown with its column name (data-label). The CSS lives in app.css under
 * "Phone card layout for list tables".
 *
 * A table is skipped when it already has its own card version (has-mobile-cards*), opts out
 * (table-no-stack), holds form fields (line-item editors), or its rows don't match its header.
 */
const SKIP_ANCESTORS = '.has-mobile-cards, .has-mobile-cards-lg, .table-no-stack';

function headerLabels(table) {
    const headRows = table.tHead?.rows;
    if (!headRows || headRows.length !== 1) {
        return null;
    }

    return [...headRows[0].cells].map((th) => th.textContent.replace(/\s+/g, ' ').replace('*', '').trim());
}

function isEligible(table, labels) {
    if (!labels || labels.length < 3 || table.closest(SKIP_ANCESTORS) || table.classList.contains('table-no-stack')) {
        return false;
    }
    if ([...table.tBodies].some((body) => body.querySelector('input:not([type=hidden]), select, textarea'))) {
        return false;
    }

    const rows = [...table.tBodies].flatMap((body) => [...body.rows]);
    return rows.length > 0 && rows.every((row) => row.cells.length === labels.length || (row.cells.length === 1 && row.cells[0].colSpan > 1));
}

function isActionCell(cell) {
    const hasControls = cell.querySelector('a.btn, button, form');
    const textOutsideControls = [...cell.childNodes].some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
    return Boolean(hasControls) && !textOutsideControls && !cell.querySelector('.badge, small, strong');
}

export function stackTables(root = document) {
    root.querySelectorAll('.table-responsive > table:not(.table-stack)').forEach((table) => {
        const labels = headerLabels(table);
        if (!isEligible(table, labels)) {
            return;
        }

        table.classList.add('table-stack');
        [...table.tBodies].forEach((body) => [...body.rows].forEach((row) => {
            if (row.cells.length === 1) {
                row.classList.add('table-stack-empty');
                return;
            }
            [...row.cells].forEach((cell, index) => {
                cell.dataset.label = labels[index] || '';
                if (index > 0 && isActionCell(cell)) {
                    cell.classList.add('table-stack-actions');
                }
            });
        }));
    });
}
