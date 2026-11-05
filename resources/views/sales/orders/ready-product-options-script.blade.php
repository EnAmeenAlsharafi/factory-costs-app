<script>
window.attachReadyProductOptions = function (tr, data, updateTotal, setTitle) {
    const actualId = tr.querySelector('.material-id-input');
    const label = tr.querySelector('.material-label-input');
    const select = tr.querySelector('.ready-option-select');
    const box = tr.querySelector('.ready-option-box');
    const status = tr.querySelector('.ready-option-status');
    const price = tr.querySelector('.price-input');
    let request = null;
    let options = [];
    const resetPrice = () => {
        price.value = 0;
        const discount = tr.querySelector('.discount-input');
        if (discount) discount.value = 0;
        updateTotal();
    };
    const reset = () => {
        request?.abort();
        actualId.value = '';
        options = [];
        select.replaceChildren(new Option('اختر المقاس / الخيار...', ''));
        select.required = false;
        box.classList.add('d-none');
        tr.dataset.readyParentId = '';
        tr.dataset.readyParentLabel = '';
    };
    async function load(parentId, parentLabel, selectedId = null, preservePrice = false) {
        reset();
        tr.dataset.readyParentId = parentId;
        tr.dataset.readyParentLabel = parentLabel;
        box.classList.remove('d-none');
        select.required = true;
        select.disabled = true;
        status.textContent = 'جاري تحميل الخيارات...';
        if (!preservePrice) resetPrice();
        const current = new AbortController();
        request = current;
        try {
            const response = await fetch('/api/search/ready-products?parent_id=' + encodeURIComponent(parentId), { headers: { Accept: 'application/json' }, signal: current.signal });
            if (!response.ok) throw new Error('options');
            const result = await response.json();
            if (current.signal.aborted) return;
            options = result;
            options.forEach(option => select.add(new Option((option.option_label || option.name_ar) + ' — ' + Number(option.sales_price).toFixed(2) + ' ر.س — متاح: ' + option.available, option.id)));
            select.disabled = false;
            status.textContent = options.length ? 'اختر المقاس لتحديد الصنف وسعره.' : 'لا توجد خيارات نشطة مسجلة لهذا المنتج. أضف المقاسات من إدارة خيارات المنتج أولًا.';
            const selected = options.find(option => String(option.id) === String(selectedId));
            if (selected) {
                select.value = selected.id;
                actualId.value = selected.id;
                label.value = selected.name_ar;
                setTitle(tr, selected.name_ar);
            } else if (selectedId) {
                resetPrice();
            }
        } catch (error) {
            if (current.signal.aborted) return;
            select.disabled = false;
            status.textContent = 'تعذر تحميل الخيارات. أعد اختيار المنتج للمحاولة مجددًا.';
        }
    }
    tr.addEventListener('typeahead-select', event => {
        if (!event.target.closest('.ready-wrapper')) return;
        const item = event.detail?.item;
        if (!item) return;
        if (item.has_ready_options) {
            setTitle(tr, item.name_ar);
            load(item.id, item.name_ar);
        } else {
            reset();
            resetPrice();
            actualId.value = item.id;
            label.value = item.name_ar || item.label;
            price.value = Number(item.sales_price ?? 0).toFixed(2);
            setTitle(tr, item.name_ar);
            if (item.ready_parent_id) load(item.ready_parent_id, item.ready_parent_label || item.name_ar, item.id, true);
            updateTotal();
        }
    });
    tr.addEventListener('typeahead-clear', event => {
        if (!event.target.closest('.ready-wrapper')) return;
        reset();
        resetPrice();
        setTitle(tr, '');
    });
    select.addEventListener('change', () => {
        const option = options.find(item => String(item.id) === select.value);
        actualId.value = option?.id || '';
        label.value = option?.name_ar || '';
        price.value = Number(option?.sales_price ?? 0).toFixed(2);
        const discount = tr.querySelector('.discount-input');
        if (discount) discount.value = 0;
        setTitle(tr, option?.name_ar || tr.dataset.readyParentLabel);
        updateTotal();
    });
    if (data.ready_parent_id) {
        load(data.ready_parent_id, data.ready_parent_label || '', data.material_id, true);
    } else if (data.ready_has_options) {
        load(data.material_id, data.material_label || '');
    }
};
</script>
