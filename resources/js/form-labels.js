/**
 * Accessible names for form fields (WCAG 1.3.1 / 3.3.2 / 4.1.2).
 *
 * Many forms render a visible `.form-label` without a `for` attribute, and table-row inputs
 * (quantities, prices) have no label at all. This links each label to its field and names
 * row inputs after their column header, including rows Alpine adds later.
 */
const FIELD_SELECTOR = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';
let generatedIdCounter = 0;

export function isNamed(field) {
    return field.closest('label')
        || field.hasAttribute('aria-label')
        || field.hasAttribute('aria-labelledby')
        || (field.id && document.querySelector(`label[for="${CSS.escape(field.id)}"]`));
}

function linkFormLabels(root) {
    root.querySelectorAll('label.form-label:not([for])').forEach((label) => {
        const container = label.parentElement;
        if (!container) {
            return;
        }

        const field = [...container.querySelectorAll(FIELD_SELECTOR)].find((candidate) => !isNamed(candidate));
        if (!field) {
            return;
        }

        if (!field.id) {
            generatedIdCounter += 1;
            field.id = `field-${generatedIdCounter}`;
        }
        label.htmlFor = field.id;
    });
}

function nameTableFields(root) {
    root.querySelectorAll('table').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.replace(/\s+/g, ' ').replace('*', '').trim());
        if (!headers.length) {
            return;
        }

        table.querySelectorAll('tbody td').forEach((cell) => {
            const header = headers[cell.cellIndex];
            if (!header) {
                return;
            }

            cell.querySelectorAll(FIELD_SELECTOR).forEach((field) => {
                if (!isNamed(field)) {
                    field.setAttribute('aria-label', header);
                }
            });
        });
    });
}

export function enhanceFormLabels(root = document) {
    linkFormLabels(root);
    nameTableFields(root);
}

/** Run once now and again whenever Alpine adds rows (debounced to one pass per frame). */
export function watchFormLabels() {
    enhanceFormLabels();

    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) {
            return;
        }
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            enhanceFormLabels();
        });
    }).observe(document.body, { childList: true, subtree: true });
}
