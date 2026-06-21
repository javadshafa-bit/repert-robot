import Sortable from 'sortablejs';

function showToast(message, type) {
    const old = document.getElementById('sort-toast');
    if (old) old.remove();

    const el = document.createElement('div');
    el.id = 'sort-toast';
    el.style.cssText = `
        position:fixed;bottom:1.5rem;left:1.5rem;z-index:9999;
        display:flex;align-items:center;gap:.5rem;
        padding:.75rem 1.25rem;border-radius:.75rem;
        font-size:.875rem;font-weight:600;
        box-shadow:0 10px 25px rgba(0,0,0,.15);
        color:#fff;
        background:${type === 'success' ? '#10b981' : '#ef4444'};
        transition:opacity .3s ease,transform .3s ease;
    `;
    el.textContent = message;
    document.body.appendChild(el);

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(.5rem)';
        setTimeout(() => el.remove(), 300);
    }, 2200);
}

function refreshOrderNumbers(container) {
    container.querySelectorAll('.field-order').forEach((el, i) => {
        el.textContent = i + 1;
    });
}

function saveOrder(container, url, csrf) {
    const ids = Array.from(container.querySelectorAll('.field-item'))
        .map(el => el.dataset.fieldId);

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ order: ids }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ترتیب فیلدها ذخیره شد', 'success');
                refreshOrderNumbers(container);
            } else {
                showToast('❌ خطا در ذخیره‌سازی', 'error');
            }
        })
        .catch(() => showToast('❌ خطا در ارتباط با سرور', 'error'));
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('fields-sortable');
    if (!container) return;

    const url  = container.dataset.reorderUrl;
    const csrf = container.dataset.csrf;

    // جلوگیری از toggle شدن details هنگام کلیک روی handle
    container.querySelectorAll('.drag-handle').forEach(handle => {
        handle.addEventListener('click', e => e.stopPropagation());
        handle.addEventListener('pointerdown', e => e.stopPropagation());
    });

    Sortable.create(container, {
        handle: '.drag-handle',
        animation: 180,
        easing: 'cubic-bezier(.25,.1,.25,1)',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onStart() {
            const opened = container.querySelector('details[open]');
            if (opened) opened.removeAttribute('open');
            document.body.style.cursor = 'grabbing';
        },
        onEnd() {
            document.body.style.cursor = '';
            saveOrder(container, url, csrf);
        },
    });
});
