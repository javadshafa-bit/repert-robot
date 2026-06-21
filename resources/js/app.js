import 'preline'
import ApexCharts from 'apexcharts'
window.ApexCharts = ApexCharts

import Sortable from 'sortablejs'

// ─── shared toast ───────────────────────────────────────────────────────────
function showToast(msg, type) {
    const old = document.getElementById('sort-toast')
    if (old) old.remove()
    const el = document.createElement('div')
    el.id = 'sort-toast'
    el.style.cssText = `position:fixed;bottom:1.5rem;left:1.5rem;z-index:9999;
        display:flex;align-items:center;gap:.5rem;padding:.75rem 1.25rem;
        border-radius:.75rem;font-size:.875rem;font-weight:600;
        box-shadow:0 10px 25px rgba(0,0,0,.15);color:#fff;
        background:${type === 'success' ? '#10b981' : '#ef4444'};
        transition:opacity .3s ease,transform .3s ease;`
    el.textContent = msg
    document.body.appendChild(el)
    setTimeout(() => {
        el.style.opacity = '0'
        el.style.transform = 'translateY(.5rem)'
        setTimeout(() => el.remove(), 300)
    }, 2200)
}

// ─── shared ajax sortable factory ───────────────────────────────────────────
function makeSortable(container, onEndCallback) {
    container.querySelectorAll('.drag-handle').forEach(h => {
        h.addEventListener('click', e => e.stopPropagation())
    })
    Sortable.create(container, {
        handle: '.drag-handle',
        animation: 180,
        easing: 'cubic-bezier(.25,.1,.25,1)',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onStart() {
            const open = container.querySelector('details[open]')
            if (open) open.removeAttribute('open')
            document.body.style.cursor = 'grabbing'
        },
        onEnd() {
            document.body.style.cursor = ''
            onEndCallback()
        },
    })
}

function postJson(url, csrf, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json())
}

// ─── init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // صفحه ویرایش دسته‌بندی — ترتیب فیلدها
    const fieldsEl = document.getElementById('fields-sortable')
    if (fieldsEl) {
        makeSortable(fieldsEl, () => {
            const ids = [...fieldsEl.querySelectorAll('.field-item')].map(el => el.dataset.fieldId)
            postJson(fieldsEl.dataset.reorderUrl, fieldsEl.dataset.csrf, { order: ids })
                .then(data => {
                    if (data.success) {
                        showToast('✅ ترتیب فیلدها ذخیره شد', 'success')
                        fieldsEl.querySelectorAll('.field-order').forEach((el, i) => el.textContent = i + 1)
                    } else {
                        showToast('❌ خطا در ذخیره‌سازی', 'error')
                    }
                })
                .catch(() => showToast('❌ خطا در ارتباط با سرور', 'error'))
        })
    }

    // صفحه تنظیمات — ترتیب مراحل ربات
    const flowEl = document.getElementById('flow-steps-sortable')
    if (flowEl) {
        makeSortable(flowEl, () => {
            // آپدیت شماره‌های ترتیب
            flowEl.querySelectorAll('.flow-step-item').forEach((el, i) => {
                const num = el.querySelector('.flow-step-number')
                if (num) num.textContent = i + 1
            })
            const steps = [...flowEl.querySelectorAll('.flow-step-item')].map(el => el.dataset.step)
            postJson(flowEl.dataset.saveUrl, flowEl.dataset.csrf, { steps })
                .then(data => {
                    if (data.success) showToast('✅ ترتیب مراحل ذخیره شد', 'success')
                    else showToast('❌ خطا در ذخیره', 'error')
                })
                .catch(() => showToast('❌ خطا در ارتباط با سرور', 'error'))
        })
    }

})
