{{-- AJAX tree fragment —  نمای بصری با قابلیت کلیک برای ویرایش --}}
<div class="px-4 py-3 bg-gray-50 border-b flex items-center gap-2">
    <h3 class="text-sm font-semibold text-gray-700">
        ساختار درخت —
        <span class="text-indigo-600">{{ $category->fields->count() }}</span> فیلد سطح اول
    </h3>
    <span class="text-xs text-gray-400 mr-auto">برای ویرایش روی هر گره کلیک کنید</span>
</div>

{{-- نمای بصری --}}
<div id="tree-visual-view">
    @include('admin.categories._tree_visual', ['category' => $category])
</div>
