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
        makeSortable(flowEl, () => saveFlow())
    }

})

// ─── flow step helpers (global) ─────────────────────────────────────────────
function saveFlow() {
    const flowEl = document.getElementById('flow-steps-sortable')
    if (!flowEl) return
    updateFlowNumbers()
    const steps = [...flowEl.querySelectorAll('.flow-step-item')]
        .filter(el => el.dataset.enabled === '1')
        .map(el => el.dataset.step)
    postJson(flowEl.dataset.saveUrl, flowEl.dataset.csrf, { steps })
        .then(data => {
            if (data.success) showToast('✅ تنظیمات جریان ذخیره شد', 'success')
            else showToast('❌ خطا در ذخیره', 'error')
        })
        .catch(() => showToast('❌ خطا در ارتباط با سرور', 'error'))
}

function updateFlowNumbers() {
    const flowEl = document.getElementById('flow-steps-sortable')
    if (!flowEl) return
    let activeIdx = 1
    flowEl.querySelectorAll('.flow-step-item').forEach(el => {
        const num = el.querySelector('.flow-step-number')
        if (!num) return
        if (el.dataset.enabled === '1') {
            num.textContent = activeIdx++
        } else {
            num.textContent = '–'
        }
    })
}

function toggleStep(btn) {
    const card = btn.closest('.flow-step-item')
    const isEnabled = card.dataset.enabled === '1'
    const step = card.dataset.step

    if (isEnabled) {
        // disable
        card.dataset.enabled = '0'
        card.classList.add('opacity-50')
        card.classList.remove('bg-blue-50','border-blue-200','bg-orange-50','border-orange-200','bg-purple-50','border-purple-200')
        card.classList.add('bg-gray-50','border-gray-200')
        btn.textContent = 'غیرفعال'
        btn.classList.remove('bg-green-100','text-green-700','hover:bg-red-100','hover:text-red-600')
        btn.classList.add('bg-gray-200','text-gray-400','hover:bg-green-100','hover:text-green-700')
        // replace drag handle with × icon
        const handle = card.querySelector('.drag-handle')
        if (handle) {
            handle.outerHTML = `<span class="mt-1 text-gray-300 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg></span>`
        }
    } else {
        // enable — reload to get proper colors back for each step type
        const colors = { month: ['bg-blue-50','border-blue-200'], department: ['bg-orange-50','border-orange-200'], category: ['bg-purple-50','border-purple-200'] }
        card.dataset.enabled = '1'
        card.classList.remove('opacity-50','bg-gray-50','border-gray-200')
        card.classList.add(...(colors[step] || ['bg-gray-50','border-gray-200']))
        btn.textContent = 'فعال'
        btn.classList.remove('bg-gray-200','text-gray-400','hover:bg-green-100','hover:text-green-700')
        btn.classList.add('bg-green-100','text-green-700','hover:bg-red-100','hover:text-red-600')
        // restore drag handle
        const xIcon = card.querySelector('svg')?.closest('span:not(.flow-step-number)')
        if (xIcon && !xIcon.classList.contains('step-toggle')) {
            xIcon.outerHTML = `<span class="drag-handle mt-0.5 text-gray-400 hover:text-gray-600 cursor-grab active:cursor-grabbing transition-colors shrink-0" title="بکشید">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="currentColor" viewBox="0 0 16 16">
                    <circle cx="6" cy="3.5" r="1.2"/><circle cx="10" cy="3.5" r="1.2"/>
                    <circle cx="6" cy="8" r="1.2"/><circle cx="10" cy="8" r="1.2"/>
                    <circle cx="6" cy="12.5" r="1.2"/><circle cx="10" cy="12.5" r="1.2"/>
                </svg></span>`
        }
    }

    saveFlow()
}

function postJson(url, csrf, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json())
}
