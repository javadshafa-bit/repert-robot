<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\FieldOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('fields')->orderBy('sort_order')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $category = Category::create([
            'name'       => $request->name,
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.categories.edit', $category)
            ->with('success', 'دسته‌بندی ساخته شد. حالا فیلدها را اضافه کنید.');
    }

    public function edit(Category $category)
    {
        $category->load(['fields' => function ($q) {
            $q->whereNull('parent_option_id')
              ->whereNull('parent_field_id')
              ->orderBy('sort_order')
              ->with($this->fieldEagerLoad());
        }]);
        return view('admin.categories.edit', compact('category'));
    }

    private function fieldEagerLoad(): array
    {
        // eager load چند سطح عمق — options (شرطی) + alwaysChildFields (همیشگی)
        return [
            'options.childFields.options.childFields.options.childFields',
            'alwaysChildFields.options.childFields.options.childFields',
        ];
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $category->update([
            'name'       => $request->name,
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'دسته‌بندی با موفقیت ویرایش شد.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'دسته‌بندی حذف شد.');
    }

    // ==========================================
    // Fields
    // ==========================================

    // returns refreshed tree HTML for AJAX calls
    public function treeFragment(Category $category)
    {
        $category->load(['fields' => function ($q) {
            $q->whereNull('parent_option_id')
              ->whereNull('parent_field_id')
              ->orderBy('sort_order')
              ->with($this->fieldEagerLoad());
        }]);

        $html = view('admin.categories._tree_fragment', compact('category'))->render();

        return response()->json(['html' => $html, 'count' => $category->fields->count()]);
    }

    public function storeField(Request $request, Category $category)
    {
        $request->validate([
            'label'            => 'required|string|max:100',
            'description'      => 'nullable|string|max:255',
            'sort_order'       => 'integer|min:0',
            'parent_option_id' => 'nullable|exists:field_options,id',
            'parent_field_id'  => 'nullable|exists:category_fields,id',
            'type'             => ['required', 'string', Rule::in(['text', 'option', 'photo', 'link'])],
            'child_type'       => ['nullable', 'string', Rule::in(['text', 'option', 'photo', 'link'])],
            'child_label'      => 'nullable|string|max:100',
        ]);

        $field = $category->fields()->create([
            'parent_option_id' => $request->parent_option_id ?: null,
            'parent_field_id'  => $request->parent_field_id ?: null,
            'label'            => $request->label,
            'description'      => $request->description,
            'sort_order'       => $request->sort_order ?? 0,
            'type'             => $request->type,
            'is_required'      => $request->boolean('is_required', true),
            'is_multiple'      => $request->boolean('is_multiple', false),
        ]);

        // اگر نوع زیرفیلد مشخص شده، یک فیلد فرزند همیشگی بساز
        if ($request->filled('child_type')) {
            $category->fields()->create([
                'parent_field_id' => $field->id,
                'label'           => $request->filled('child_label') ? $request->child_label : ($field->label . ' — جزئیات'),
                'type'            => $request->child_type,
                'sort_order'      => 0,
                'is_required'     => true,
                'is_multiple'     => false,
            ]);
        }

        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد اضافه شد.', 'field_id' => $field->id]);
        return back()->with('success', 'فیلد اضافه شد.');
    }

    public function updateField(Request $request, Category $category, CategoryField $field)
    {
        $request->validate([
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'type'        => ['nullable', 'string', Rule::in(['text', 'option', 'photo', 'link'])],
        ]);

        $data = [
            'label'       => $request->label,
            'description' => $request->description,
            'is_required' => $request->boolean('is_required'),
            'is_multiple' => $request->boolean('is_multiple'),
        ];
        if ($request->filled('type')) $data['type'] = $request->type;

        $field->update($data);

        if (request()->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد ویرایش شد.']);
        return back()->with('success', 'فیلد ویرایش شد.');
    }

    public function destroyField(Category $category, CategoryField $field)
    {
        $field->delete();
        if (request()->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد حذف شد.']);
        return back()->with('success', 'فیلد حذف شد.');
    }

    // ==========================================
    // Options
    // ==========================================

    public function storeOption(Request $request, Category $category, CategoryField $field)
    {
        $request->validate(['label' => 'required|string|max:100']);

        $option = $field->options()->create([
            'label'      => $request->label,
            'sort_order' => $field->options()->count(),
        ]);

        if ($request->expectsJson()) return response()->json([
            'success'   => true,
            'message'   => 'گزینه اضافه شد.',
            'option_id' => $option->id,
        ]);
        return back()->with('success', 'گزینه اضافه شد.');
    }

    public function updateOption(Request $request, Category $category, CategoryField $field, FieldOption $option)
    {
        $request->validate(['label' => 'required|string|max:100']);
        $option->update(['label' => $request->label]);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'گزینه ویرایش شد.']);
        return back()->with('success', 'گزینه ویرایش شد.');
    }

    public function destroyOption(Category $category, CategoryField $field, FieldOption $option)
    {
        $option->delete();
        if (request()->expectsJson()) return response()->json(['success' => true, 'message' => 'گزینه حذف شد.']);
        return back()->with('success', 'گزینه حذف شد.');
    }

    // ==========================================
    // Reparent & Batch Copy
    // ==========================================

    /** جابجایی یک فیلد زیر option دیگر (یا root اگر null) */
    public function reparentField(Request $request, Category $category, CategoryField $field)
    {
        $request->validate([
            'parent_option_id' => 'nullable|exists:field_options,id',
        ]);
        $field->update(['parent_option_id' => $request->parent_option_id ?: null]);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد جابجا شد.']);
        return back()->with('success', 'فیلد جابجا شد.');
    }

    /** جابجایی یک گزینه زیر field دیگر */
    public function reparentOption(Request $request, Category $category, CategoryField $field, FieldOption $option)
    {
        $request->validate([
            'field_id' => 'required|exists:category_fields,id',
        ]);
        $option->update(['field_id' => $request->field_id]);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'گزینه جابجا شد.']);
        return back()->with('success', 'گزینه جابجا شد.');
    }

    /** کپی عمیق چند گزینه زیر یک field */
    public function batchCopyOptions(Request $request, Category $category, CategoryField $fieldTarget)
    {
        $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'exists:field_options,id',
        ]);

        $sources = FieldOption::with('childFields.options.childFields.options.childFields')
            ->whereIn('id', $request->option_ids)->get();

        foreach ($sources as $opt) {
            $this->deepCopyOption($opt, $fieldTarget->id);
        }

        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => count($sources) . ' گزینه کپی شد.']);
        return back()->with('success', 'گزینه‌ها کپی شدند.');
    }

    private function deepCopyOption(FieldOption $opt, int $newFieldId): void
    {
        $newOpt = FieldOption::create([
            'field_id'   => $newFieldId,
            'label'      => $opt->label,
            'sort_order' => FieldOption::where('field_id', $newFieldId)->count(),
        ]);
        foreach ($opt->childFields as $cf) {
            $this->deepCopyField($cf, $newOpt->id);
        }
    }

    private function deepCopyField(CategoryField $f, ?int $parentOptId): void
    {
        $nf = CategoryField::create([
            'category_id'      => $f->category_id,
            'parent_option_id' => $parentOptId,
            'label'            => $f->label,
            'description'      => $f->description,
            'type'             => $f->type,
            'is_required'      => $f->is_required,
            'is_multiple'      => $f->is_multiple,
            'sort_order'       => $f->sort_order,
        ]);
        foreach ($f->options as $opt) {
            $this->deepCopyOption($opt, $nf->id);
        }
    }
}
