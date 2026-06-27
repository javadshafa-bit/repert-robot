{{-- AJAX tree fragment — header + palette + visual --}}
<div class="px-4 py-3 bg-gray-50 border-b">
    <div class="flex items-center gap-2 mb-2 flex-wrap">
        <h3 class="text-sm font-semibold text-gray-700">
            ساختار درخت —
            <span class="text-indigo-600">{{ $category->fields->count() }}</span> فیلد سطح اول
        </h3>
        {{-- Undo --}}
        <button id="vtree-undo-btn" onclick="vtreeUndo()" disabled
                title="تغییری برای بازگشت وجود ندارد"
                style="display:flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;font-size:.72rem;color:#6b7280;cursor:default;opacity:.4;font-family:inherit">
            ↩ بازگشت
        </button>
        {{-- Zoom controls --}}
        <div style="display:flex;align-items:center;gap:.3rem;margin-right:.5rem">
            <button onclick="vtreeZoom(-0.1)"
                    style="width:1.6rem;height:1.6rem;border:1px solid #e5e7eb;border-radius:.4rem;background:#fff;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center">−</button>
            <span id="vtree-zoom-label"
                  style="font-size:.7rem;color:#6b7280;min-width:2.4rem;text-align:center;font-weight:600">٪۸۰</span>
            <button onclick="vtreeZoom(+0.1)"
                    style="width:1.6rem;height:1.6rem;border:1px solid #e5e7eb;border-radius:.4rem;background:#fff;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center">+</button>
            <button onclick="vtreeZoomFit()"
                    title="جاگذاری خودکار در کادر"
                    style="padding:.2rem .5rem;border:1px solid #6366f1;border-radius:.4rem;background:#eef2ff;color:#4f46e5;font-size:.65rem;font-weight:600;cursor:pointer">
                ⊡ Fit
            </button>
        </div>
        <span class="text-xs text-gray-400">کلیک=ویرایش | Ctrl+کلیک=انتخاب | درگ=جابجایی</span>
    </div>
    {{-- Palette داخل header --}}
    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
        <span style="font-size:.7rem;color:#9ca3af">درگ روی گره:</span>
        <div class="vtree-palette-chip" draggable="true"
             ondragstart="vtreePaletteDrag(event,'text')"
             style="background:#f9fafb;border:1.5px solid #9ca3af;color:#374151">📝 متن</div>
        <div class="vtree-palette-chip" draggable="true"
             ondragstart="vtreePaletteDrag(event,'option')"
             style="background:#f5f3ff;border:1.5px solid #a78bfa;color:#5b21b6">🔘 گزینه</div>
        <div class="vtree-palette-chip" draggable="true"
             ondragstart="vtreePaletteDrag(event,'photo')"
             style="background:#eff6ff;border:1.5px solid #60a5fa;color:#1d4ed8">📸 عکس</div>
        <div class="vtree-palette-chip" draggable="true"
             ondragstart="vtreePaletteDrag(event,'link')"
             style="background:#f0fdf4;border:1.5px solid #4ade80;color:#15803d">🔗 لینک</div>
        <div style="width:1px;height:1.2rem;background:#e5e7eb;margin:0 .15rem"></div>
        <div id="palette-copy-section" style="display:none;align-items:center;gap:.35rem">
            <span id="palette-sel-count" style="font-size:.7rem;color:#6366f1;font-weight:600"></span>
            <button onclick="vtreeCopySelected()"
                    style="font-size:.7rem;padding:.2rem .55rem;background:#6366f1;color:#fff;border:none;border-radius:.45rem;cursor:pointer">
                📋 کپی
            </button>
            <button onclick="vtreeClearSelection()"
                    style="font-size:.7rem;padding:.2rem .45rem;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;border-radius:.45rem;cursor:pointer">
                ✕
            </button>
        </div>
        <div id="palette-paste-section" style="display:none;align-items:center;gap:.35rem">
            <span style="font-size:.7rem;color:#d97706;font-weight:600">روی یک فیلد گزینه‌ای کلیک کنید ← paste</span>
            <button onclick="vtreeCancelPaste()"
                    style="font-size:.7rem;padding:.2rem .45rem;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:.45rem;cursor:pointer">
                انصراف
            </button>
        </div>
        <div id="palette-field-section" style="display:none;align-items:center;gap:.35rem">
            <span id="palette-field-count" style="font-size:.7rem;color:#6366f1;font-weight:600"></span>
            <button onclick="vtreeEnterFieldPasteMode()"
                    style="font-size:.7rem;padding:.2rem .55rem;background:#6366f1;color:#fff;border:none;border-radius:.45rem;cursor:pointer">
                📋 کپی
            </button>
            <button onclick="vtreeDeleteSelectedFields()"
                    style="font-size:.7rem;padding:.2rem .55rem;background:#dc2626;color:#fff;border:none;border-radius:.45rem;cursor:pointer">
                🗑 حذف
            </button>
            <button onclick="vtreeClearFieldSelection()"
                    style="font-size:.7rem;padding:.2rem .45rem;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;border-radius:.45rem;cursor:pointer">
                ✕
            </button>
        </div>
        <div id="palette-field-paste-section" style="display:none;align-items:center;gap:.35rem">
            <span style="font-size:.7rem;color:#0891b2;font-weight:600">روی فیلد مقصد کلیک کنید ← paste</span>
            <button onclick="vtreeCancelFieldPaste()"
                    style="font-size:.7rem;padding:.2rem .45rem;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:.45rem;cursor:pointer">
                انصراف
            </button>
        </div>
    </div>
</div>

{{-- نمای بصری --}}
<div id="tree-visual-view">
    @include('admin.categories._tree_visual', ['category' => $category])
</div>
