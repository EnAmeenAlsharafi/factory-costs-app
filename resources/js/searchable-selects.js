/**
 * Search inside every dropdown (native <select>) in the system, with no per-page markup.
 *
 * The native select stays on the page as the field: it is still what the form submits, what
 * Alpine's x-model binds to and what shows the chosen value. Opening it is intercepted and a
 * search panel is shown instead; choosing an item writes back to the select and fires `input`
 * and `change`, so @change handlers, x-model and onchange="this.form.submit()" keep working.
 * The list is read from the select each time it opens, so options Alpine adds later are included.
 *
 * Skipped: multi-selects and list boxes (`multiple` / `size` > 1), disabled selects, and any
 * select marked `data-native-select`.
 */
let normalize = (text) => String(text ?? '').toLowerCase().trim();

let panel = null;
let activeSelect = null;
let items = [];
let visibleItems = [];
let highlighted = 0;
let touchStart = null;

const isEnhanceable = (select) => select instanceof HTMLSelectElement
    && !select.multiple
    && !(select.size > 1)
    && !select.disabled
    && !select.hasAttribute('data-native-select')
    && !select.closest('[data-native-select]');

function buildPanel() {
    const element = document.createElement('div');
    element.className = 'ss-panel';
    element.setAttribute('role', 'dialog');
    element.innerHTML = `
        <div class="ss-search">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="ss-input" autocomplete="off" spellcheck="false"
                   placeholder="ابحث في القائمة..." aria-label="ابحث في القائمة"
                   role="combobox" aria-expanded="true" aria-autocomplete="list">
        </div>
        <ul class="ss-list" role="listbox"></ul>
        <div class="ss-empty" hidden>لا توجد نتائج مطابقة</div>`;

    const input = element.querySelector('.ss-input');
    const list = element.querySelector('.ss-list');
    list.id = 'ss-list';
    input.setAttribute('aria-controls', list.id);

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('keydown', onPanelKeydown);
    list.addEventListener('mousedown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const option = event.target.closest('.ss-option');
        if (option && !option.classList.contains('is-disabled')) {
            choose(Number(option.dataset.index));
        }
    });

    return element;
}

function readItems(select) {
    return [...select.options].map((option, index) => ({
        index,
        label: option.textContent.replace(/\s+/g, ' ').trim(),
        group: option.parentElement instanceof HTMLOptGroupElement ? option.parentElement.label : '',
        disabled: option.disabled || option.parentElement?.disabled === true,
        hidden: option.hidden,
    })).filter((item) => !item.hidden);
}

function render(query = '') {
    const needle = normalize(query);
    const list = panel.querySelector('.ss-list');
    visibleItems = needle
        ? items.filter((item) => normalize(`${item.label} ${item.group}`).includes(needle))
        : items;

    const fragment = document.createDocumentFragment();
    let lastGroup = '';
    visibleItems.forEach((item, position) => {
        if (item.group && item.group !== lastGroup) {
            const header = document.createElement('li');
            header.className = 'ss-group';
            header.setAttribute('role', 'presentation');
            header.textContent = item.group;
            fragment.append(header);
        }
        lastGroup = item.group;

        const option = document.createElement('li');
        option.className = 'ss-option';
        option.id = `ss-option-${position}`;
        option.dataset.index = String(item.index);
        option.dataset.position = String(position);
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', String(item.index === activeSelect.selectedIndex));
        if (item.disabled) {
            option.classList.add('is-disabled');
            option.setAttribute('aria-disabled', 'true');
        }
        option.textContent = item.label || ' ';
        fragment.append(option);
    });
    list.replaceChildren(fragment);
    panel.querySelector('.ss-empty').hidden = visibleItems.length > 0;

    const selectedPosition = needle ? -1 : visibleItems.findIndex((item) => item.index === activeSelect.selectedIndex);
    highlight(selectedPosition >= 0 ? selectedPosition : firstEnabled(0, 1));
}

function firstEnabled(from, step) {
    for (let position = from; position >= 0 && position < visibleItems.length; position += step) {
        if (!visibleItems[position].disabled) {
            return position;
        }
    }
    return -1;
}

function highlight(position) {
    highlighted = position;
    const list = panel.querySelector('.ss-list');
    const input = panel.querySelector('.ss-input');
    list.querySelectorAll('.ss-option.is-active').forEach((option) => option.classList.remove('is-active'));
    const option = list.querySelector(`.ss-option[data-position="${position}"]`);
    if (!option) {
        input.removeAttribute('aria-activedescendant');
        return;
    }
    option.classList.add('is-active');
    input.setAttribute('aria-activedescendant', option.id);
    // Scroll the list only: scrollIntoView could also move the page.
    if (option.offsetTop < list.scrollTop) {
        list.scrollTop = option.offsetTop;
    } else if (option.offsetTop + option.offsetHeight > list.scrollTop + list.clientHeight) {
        list.scrollTop = option.offsetTop + option.offsetHeight - list.clientHeight;
    }
}

function position() {
    if (!panel || !activeSelect) {
        return;
    }
    const rect = activeSelect.getBoundingClientRect();
    const gutter = 8;
    const width = Math.min(Math.max(rect.width, 260), window.innerWidth - gutter * 2);
    const isRtl = getComputedStyle(activeSelect).direction === 'rtl';
    let left = isRtl ? rect.right - width : rect.left;
    left = Math.min(Math.max(left, gutter), window.innerWidth - width - gutter);

    const spaceBelow = window.innerHeight - rect.bottom - gutter;
    const spaceAbove = rect.top - gutter;
    const above = spaceBelow < 240 && spaceAbove > spaceBelow;
    const maxHeight = Math.max(Math.min(above ? spaceAbove : spaceBelow, 380), 160);

    panel.style.width = `${width}px`;
    panel.style.left = `${left}px`;
    panel.style.maxHeight = `${maxHeight - 4}px`;
    panel.style.top = above ? '' : `${rect.bottom + 4}px`;
    panel.style.bottom = above ? `${window.innerHeight - rect.top + 4}px` : '';
    panel.classList.toggle('is-above', above);
}

function open(select, initialQuery = '') {
    if (activeSelect === select) {
        return;
    }
    close(false);
    panel ??= buildPanel();
    activeSelect = select;
    items = readItems(select);

    // Inside a Bootstrap modal or offcanvas the panel must live in it, or the focus trap steals the search box.
    (select.closest('.modal, .offcanvas, .offcanvas-md, dialog') ?? document.body).append(panel);

    const input = panel.querySelector('.ss-input');
    input.value = initialQuery;
    select.setAttribute('aria-expanded', 'true');
    render(initialQuery);
    position();
    panel.classList.add('is-open');
    input.focus({ preventScroll: true });
}

function close(returnFocus = true) {
    if (!panel || !activeSelect) {
        return;
    }
    const select = activeSelect;
    activeSelect = null;
    panel.classList.remove('is-open');
    panel.remove();
    select.setAttribute('aria-expanded', 'false');
    if (returnFocus) {
        select.focus({ preventScroll: true });
    }
}

function choose(index) {
    const select = activeSelect;
    if (!select) {
        return;
    }
    const changed = select.selectedIndex !== index;
    close();
    if (changed) {
        select.selectedIndex = index;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function onPanelKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        const next = firstEnabled(highlighted + step, step);
        if (next >= 0) {
            highlight(next);
        }
    } else if (event.key === 'Home' || event.key === 'End') {
        if (!event.currentTarget.value) {
            event.preventDefault();
            highlight(event.key === 'Home' ? firstEnabled(0, 1) : firstEnabled(visibleItems.length - 1, -1));
        }
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (highlighted >= 0 && visibleItems[highlighted]) {
            choose(visibleItems[highlighted].index);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
    } else if (event.key === 'Tab') {
        close();
    }
}

/** Open from the select's own keys: Enter, Space, Alt+↓, F4, or typing a letter (which starts the search). */
function onSelectKeydown(event) {
    const select = event.target;
    if (!isEnhanceable(select)) {
        return;
    }
    const opensList = event.key === 'Enter' || event.key === ' ' || event.key === 'F4'
        || (event.altKey && (event.key === 'ArrowDown' || event.key === 'ArrowUp'));
    const isTyping = event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey;
    if (opensList || isTyping) {
        event.preventDefault();
        open(select, isTyping ? event.key : '');
    }
}

export function enableSearchableSelects({ normalizeText } = {}) {
    if (normalizeText) {
        normalize = normalizeText;
    }

    // Mouse / pen: stop the native list from opening and show the search panel instead.
    document.addEventListener('mousedown', (event) => {
        const select = event.target;
        if (event.button !== 0 || !isEnhanceable(select)) {
            return;
        }
        event.preventDefault();
        if (activeSelect === select) {
            close();
            return;
        }
        select.focus({ preventScroll: true });
        open(select);
    });

    // Touch (iOS opens its picker on tap, not mousedown): cancel the tap and open the panel, unless the finger scrolled.
    document.addEventListener('touchstart', (event) => {
        touchStart = isEnhanceable(event.target) ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { passive: true });
    document.addEventListener('touchend', (event) => {
        const select = event.target;
        if (!touchStart || !isEnhanceable(select)) {
            return;
        }
        const touch = event.changedTouches[0];
        const moved = Math.abs(touch.clientX - touchStart.x) > 10 || Math.abs(touch.clientY - touchStart.y) > 10;
        touchStart = null;
        if (moved) {
            return;
        }
        event.preventDefault();
        open(select);
    }, { passive: false });

    document.addEventListener('keydown', (event) => {
        if (event.target instanceof HTMLSelectElement) {
            onSelectKeydown(event);
        }
    });

    document.addEventListener('mousedown', (event) => {
        if (panel && activeSelect && !panel.contains(event.target) && event.target !== activeSelect) {
            close(false);
        }
    }, true);
    document.addEventListener('focusin', (event) => {
        if (panel && activeSelect && !panel.contains(event.target) && event.target !== activeSelect) {
            close(false);
        }
    });

    // Follow the select when the page or a scrolling container moves; close if it scrolls out of view.
    document.addEventListener('scroll', (event) => {
        if (!activeSelect || panel.contains(event.target)) {
            return;
        }
        const rect = activeSelect.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > window.innerHeight) {
            close(false);
        } else {
            position();
        }
    }, true);
    window.addEventListener('resize', position);

    // Mark enhanced selects as opening a list (assistive tech announces it).
    const markAll = () => document.querySelectorAll('select').forEach((select) => {
        if (isEnhanceable(select) && !select.hasAttribute('aria-haspopup')) {
            select.setAttribute('aria-haspopup', 'listbox');
            select.setAttribute('aria-expanded', 'false');
        }
    });
    markAll();
    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) {
            return;
        }
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            markAll();
        });
    }).observe(document.body, { childList: true, subtree: true });
}
