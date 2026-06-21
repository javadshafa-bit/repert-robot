{{-- AJAX tree fragment — header + toggle + both views --}}
<div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between gap-3">
    <h3 class="text-sm font-semibold text-gray-700 shrink-0">
        ساختار درخت —
        <span class="text-indigo-600">{{ $category->fields->count() }}</span> فیلد سطح اول
    </h3>
    <div class="flex gap-1 mr-auto" dir="ltr">
        <button onclick="setTreeView('visual')" id="btn-visual"
                class="tree-view-btn active text-xs px-3 py-1 rounded-lg font-medium transition-all">
            🌳 بصری
        </button>
        <button onclick="setTreeView('edit')" id="btn-edit"
                class="tree-view-btn text-xs px-3 py-1 rounded-lg font-medium transition-all">
            ✏️ مدیریت
        </button>
    </div>
</div>

{{-- نمای بصری --}}
<div id="tree-visual-view">
    @include('admin.categories._tree_visual', ['category' => $category])
</div>

{{-- نمای مدیریت (card-based) --}}
<div id="tree-edit-view" class="hidden">
    @if($category->fields->isEmpty())
        <div class="px-4 py-10 text-center text-sm text-gray-400">هنوز فیلدی تعریف نشده</div>
    @else
        <div class="p-4 space-y-3">
            @foreach($category->fields as $field)
                @include('admin.categories._field_node', [
                    'field'    => $field,
                    'category' => $category,
                    'depth'    => 0,
                ])
            @endforeach
        </div>
    @endif
</div>
