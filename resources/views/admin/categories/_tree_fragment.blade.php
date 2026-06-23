{{-- AJAX tree fragment — header + palette + visual --}}
<div class="px-4 py-3 bg-gray-50 border-b">
    <div class="flex items-center gap-2 mb-2 flex-wrap">
        <h3 class="text-sm font-semibold text-gray-700">
            ساختار درخت —
            <span class="text-indigo-600">{{ $category->fields->count() }}</span> فیلد سطح اول
        </h3>
        {{-- Zoom controls --}}
        <div style="display:flex;align-items:center;gap:.3rem;margin-right:auto">
            <button onclick="vtreeZoom(-0.1)"
                    style="width:1.6rem;height:1.6rem;border:1px solid #e5e7eb;border-radiu