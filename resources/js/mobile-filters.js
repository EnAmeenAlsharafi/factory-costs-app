/**
 * Phones: list pages open onto the list, not onto a stack of filters.
 *
 * Any GET filter form with two or more pickers (dropdowns, dates, numbers) keeps its search box and
 * buttons in view and folds the pickers behind a «الفلاتر» button that shows how many are in use.
 * Pages built on <x-filter-sheet> already behave this way and are left alone, as is any form marked
 * `data-filters-open`. Desktop layouts are untouched.
 */
const PICKER_SELECTOR = 'select, input[type=date], input[type=month], input[type=week], input[type=number]';
const FIELD_SELECTOR = 'select, input:not([type=hidden]):not([type=submit]):not([type=button]), textarea';
const MOBILE_QUERY = window.matchMedia('(max-width: 767.98px)');

/**
 * The block a picker sits in (its label and wrapper): climb while the parent holds no other field.
 */
function pickerBlock(picker, form) {
    let block = picker;
    while (block.parentElement && block.parentElement !== form
        && block.parentElement.querySelectorAll(FIELD_SELECTOR).length === 1) {
        block = block.parentElement;
    }
    return block;
}

function isInUse(picker) {
    if (picker.tagName === 'SELECT') {
        return picker.value !== '' && picker.selectedIndex > 0;
    }
    return picker.value !== '';
}

function enhance(form) {
    if (form.dataset.filtersEnhanced || form.closest('.filter-sheet-wrapper, [data-filters-open]')) {
        return;
    }

    const pickers = [...form.querySelectorAll(PICKER_SELECTOR)].filter((picker) => !picker.closest('[hidden], .d-none'));
    if (pickers.length < 2) {
        return;
    }

    form.dataset.filtersEnhanced = '1';
    const blocks = [...new Set(pickers.map((picker) => pickerBlock(picker, form)))];
    blocks.forEach((block) => block.classList.add('filter-extra'));

    const inUse = pickers.filter(isInUse).length;
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-outline-secondary filter-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<i class="fas fa-sliders" aria-hidden="true"></i> الفلاتر'
        + (inUse > 0 ? ` <span class="badge rounded-pill bg-warning text-dark">${inUse}</span>` : '');
    toggle.addEventListener('click', () => {
        const open = form.classList.toggle('filters-open');
        toggle.setAttribute('aria-expanded', String(open));
    });

    blocks[0].before(toggle);
    form.classList.add('filters-collapsible');
}

export function collapseMobileFilters(root = document) {
    if (!MOBILE_QUERY.matches) {
        return;
    }
    root.querySelectorAll('#main-content form').forEach((form) => {
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') {
            enhance(form);
        }
    });
}
