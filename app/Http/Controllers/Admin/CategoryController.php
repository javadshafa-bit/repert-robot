<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\FieldOption;
use App\Support\FieldTree;
use App\Support\TenantRule;
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

        // اگر فیلدی از درخت جدا افتاده باشد، صفحه باید بگوید — نه اینکه بی‌صدا
        // خالی نشان بدهد. یک حلقه‌ی ناخواسته می‌تواند کل فرم را نامرئی کند.
        $detachedCount = $this->detachedFieldCount($category);

        return view('admin.categories.edit', compact('category', 'detachedCount'));
    }

    /** تعداد فیلدهای این دسته‌بندی که از ریشه قابل دسترس نیستند */
    private function detachedFieldCount(Category $category): int
    {
        $fields = CategoryField::where('category_id', $category->id)
            ->get(['id', 'parent_option_id', 'parent_field_id']);

        if ($fields->isEmpty()) return 0;

        $options = FieldOption::whereIn('field_id', $fields->pluck('id'))->get(['id', 'field_id']);

        return FieldTree::unreachable($fields, $options)->count();
    }

    private function fieldEagerLoad(): array
    {
        // eager load چند سطح عمق — options (شرطی) + alwaysChildFields (همیشگی) در همه سطوح
        return [
            // standalone paths برای اطمینان از لود شدن مستقیم (safety-net)
            'alwaysChildFields',
            'options',
            'options.childFields',
            'options.childFields.alwaysChildFields',
            // nested paths برای عمق بیشتر
            'options.childFields.options.childFields.options.childFields',
            'options.childFields.options.childFields.alwaysChildFields',
            'options.childFields.alwaysChildFields.options.childFields',
            'options.childFields.alwaysChildFields.alwaysChildFields',
            'alwaysChildFields.options.childFields.options.childFields',
            'alwaysChildFields.options.childFields.alwaysChildFields',
            'alwaysChildFields.alwaysChildFields.options.childFields',
            'alwaysChildFields.alwaysChildFields.alwaysChildFields',
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

    public function toggleActive(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        $status = $category->is_active ? 'فعال' : 'غیرفعال';
        return back()->with('success', "دسته‌بندی «{$category->name}» {$status} شد.");
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
            'parent_option_id' => ['nullable', TenantRule::exists('field_options')],
            'parent_field_id'  => ['nullable', TenantRule::exists('category_fields')],
            'type'             => ['required', 'string', Rule::in(['text', 'option', 'photo', 'link', 'date'])],
            'child_type'       => ['nullable', 'string', Rule::in(['text', 'option', 'photo', 'link', 'date'])],
            'child_label'      => 'nullable|string|max:100',
            'date_range'       => ['nullable', 'string', Rule::in(['past', 'future', 'any'])],
            'child_date_range' => ['nullable', 'string', Rule::in(['past', 'future', 'any'])],
        ]);

        $field = $category->fields()->create([
            'parent_option_id' => $request->parent_option_id ?: null,
            'parent_field_id'  => $request->parent_field_id ?: null,
            'label'            => $request->label,
            'description'      => $request->description,
            'sort_order'       => $request->sort_order ?? 0,
            'type'             => $request->type,
            'is_required'      => $request->boolean('is_required', true),
            'is_multiple'      => $request->type === 'date' ? false : $request->boolean('is_multiple', false),
            'date_range'       => $request->date_range ?: 'any',
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
                'date_range'      => $request->child_date_range ?: 'any',
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
            'type'        => ['nullable', 'string', Rule::in(['text', 'option', 'photo', 'link', 'date'])],
            'date_range'  => ['nullable', 'string', Rule::in(['past', 'future', 'any'])],
        ]);

        $data = [
            'label'       => $request->label,
            'description' => $request->description,
            'is_required' => $request->boolean('is_required'),
            'is_multiple' => $request->type === 'date' ? false : $request->boolean('is_multiple'),
        ];
        if ($request->filled('type'))       $data['type']       = $request->type;
        if ($request->filled('date_range')) $data['date_range'] = $request->date_range;

        $field->update($data);

        if (request()->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد ویرایش شد.']);
        return back()->with('success', 'فیلد ویرایش شد.');
    }

    public function destroyField(Category $category, CategoryField $field)
    {
        $this->deleteFieldSubtree($field);
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
        $this->deleteOptionSubtree($option);
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
            'parent_option_id' => ['nullable', TenantRule::exists('field_options')],
            'parent_field_id'  => ['nullable', TenantRule::exists('category_fields')],
        ]);

        $newParentFieldId  = $request->parent_field_id  ?: null;
        $newParentOptionId = $request->parent_option_id ?: null;

        // فیلدِ مقصد را پیدا کن — چه مستقیم، چه از راه صاحبِ گزینه
        $targetField = null;
        if ($newParentFieldId) {
            $targetField = CategoryField::find($newParentFieldId);
        } elseif ($newParentOptionId) {
            $opt         = FieldOption::find($newParentOptionId);
            $targetField = $opt ? CategoryField::find($opt->field_id) : null;
        }

        // بردن یک فیلد زیر خودش یا زیر نوادهٔ خودش یک حلقه می‌سازد: زنجیره‌ی
        // والد هرگز به ریشه نمی‌رسد، پس کل آن زیردرخت از صفحه ناپدید می‌شود
        // بدون اینکه چیزی حذف شده باشد. (۹ شهریور ۱۴۰۵ دقیقاً همین اتفاق افتاد.)
        if ($targetField) {
            if ($targetField->id === $field->id
                || in_array($field->id, $this->ancestorIds($targetField)['fields'], true)) {
                return $this->treeError($request, 'نمی‌توان یک فیلد را زیر خودش یا زیر زیرمجموعه‌اش برد.');
            }
        }

        $field->update([
            'parent_option_id' => $newParentOptionId,
            'parent_field_id'  => $newParentFieldId,
        ]);

        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد جابجا شد.']);
        return back()->with('success', 'فیلد جابجا شد.');
    }

    /**
     * جابه‌جایی یک فیلد بین برادرهایش (بالا/پایین) — بدون تغییر والد.
     *
     * درگ در درخت فقط «زیرمجموعه کردن» است؛ برای عوض کردن *ترتیب* راهی
     * وجود نداشت و کاربر ناچار درگ می‌کرد که نتیجه‌اش تودرتو شدن بود.
     */
    public function moveField(Request $request, Category $category, CategoryField $field)
    {
        $request->validate(['direction' => 'required|string|in:up,down']);

        if ($field->category_id !== $category->id) {
            return $this->treeError($request, 'این فیلد به این دسته‌بندی تعلق ندارد.');
        }

        $q = CategoryField::where('category_id', $field->category_id);
        if ($field->parent_field_id)       $q->where('parent_field_id', $field->parent_field_id);
        elseif ($field->parent_option_id)  $q->where('parent_option_id', $field->parent_option_id);
        else $q->whereNull('parent_field_id')->whereNull('parent_option_id');

        $ordered = $q->orderBy('sort_order')->orderBy('id')->get();
        $idx     = $ordered->search(fn ($f) => $f->id === $field->id);

        if ($idx === false) {
            return $this->treeError($request, 'فیلد در سطح خودش پیدا نشد.');
        }

        $swap = $request->direction === 'up' ? $idx - 1 : $idx + 1;
        if ($swap < 0 || $swap >= $ordered->count()) {
            return $this->treeError($request, 'این فیلد همین حالا در ابتدا/انتهای سطح خودش است.');
        }

        // کل گروه از نو شماره‌گذاری می‌شود؛ اینطور sort_orderهای تکراری یا
        // ناپیوسته‌ی قدیمی هم همان‌جا مرتب می‌شوند.
        $ids = $ordered->pluck('id')->all();
        [$ids[$idx], $ids[$swap]] = [$ids[$swap], $ids[$idx]];
        foreach ($ids as $i => $id) {
            CategoryField::where('id', $id)->update(['sort_order' => $i]);
        }

        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'ترتیب عوض شد.']);
        return back()->with('success', 'ترتیب عوض شد.');
    }

    /** کپی عمیق یک فیلد: خود فیلد، گزینه‌هایش، زیرفیلدهای هر گزینه،
     *  و زیرفیلدهای همیشگی‌اش — تا هر عمقی.
     *
     *  اگر parent_field_id در request باشد → کپی به عنوان always-child آن فیلد
     *  در غیر این صورت → sibling در همان سطح اصلی
     */
    public function duplicateField(Request $request, Category $category, CategoryField $field)
    {
        // این متد تنها جای کنترلر بود که هیچ اعتبارسنجی نداشت و
        // parent_field_id را خام (int) می‌کرد.
        $request->validate([
            'parent_field_id' => ['nullable', TenantRule::exists('category_fields')],
        ], [
            'parent_field_id.exists' => 'فیلد مقصد پیدا نشد.',
        ]);

        if ($field->category_id !== $category->id) {
            return $this->treeError($request, 'این فیلد به این دسته‌بندی تعلق ندارد.');
        }

        if ($request->filled('parent_field_id')) {
            $target = CategoryField::find((int) $request->parent_field_id);

            if (!$target || $target->category_id !== $category->id) {
                return $this->treeError($request, 'فیلد مقصد در این دسته‌بندی نیست.');
            }

            // paste داخل زیرمجموعه‌ی خودِ منبع، پیمایش کپی را بی‌پایان می‌کند:
            // رکورد تازه زیر یک نوادهٔ منبع می‌نشیند و در ادامه‌ی همان پیمایش
            // دوباره خوانده می‌شود. اینجا صریح جلویش گرفته می‌شود.
            if ($target->id === $field->id || in_array($field->id, $this->ancestorIds($target)['fields'], true)) {
                return $this->treeError($request, 'نمی‌توان یک فیلد را داخل زیرمجموعه‌ی خودش paste کرد.');
            }

            $parentFieldId  = $target->id;
            $parentOptionId = null;
            // paste_index حذف شد: max هر بار دوباره خوانده می‌شود، پس جمع کردن
            // هر دو باعث می‌شد sort_orderها 0، 2، 5 شوند به‌جای 0، 1، 2.
            $sortOrder = (CategoryField::where('parent_field_id', $parentFieldId)->max('sort_order') ?? -1) + 1;
        } else {
            // کپی به‌عنوان برادرِ بلافاصله بعدی: برادرهای بعدی یک واحد جابه‌جا
            // می‌شوند تا sort_order تکراری نشود.
            $parentFieldId  = $field->parent_field_id;
            $parentOptionId = $field->parent_option_id;

            $siblings = CategoryField::where('category_id', $field->category_id);
            if ($field->parent_field_id)       $siblings->where('parent_field_id', $field->parent_field_id);
            elseif ($field->parent_option_id)  $siblings->where('parent_option_id', $field->parent_option_id);
            else $siblings->whereNull('parent_field_id')->whereNull('parent_option_id');

            $siblings->where('sort_order', '>', $field->sort_order)->increment('sort_order');
            $sortOrder = $field->sort_order + 1;
        }

        $this->copiedFieldIds = [];
        $newField = $this->deepCopyField($field, $parentOptionId, $parentFieldId, $sortOrder, [], $category->id);

        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'فیلد کپی شد.', 'field_id' => $newField->id]);
        return back()->with('success', 'فیلد کپی شد.');
    }

    /**
     * شناسه‌ی فیلدهایی که در همین درخواستِ کپی ساخته شده‌اند.
     *
     * snapshot گرفتن از فرزندان پیش از insert کافی نیست: وقتی مقصد داخل
     * زیرمجموعه‌ی منبع باشد، رکورد تازه پیش از رسیدنِ پیمایش به آن گره،
     * فرزندِ همان گره شده است و در snapshot خودش دیده می‌شود. این مجموعه
     * تضمین می‌کند هیچ رکوردِ تازه‌ای دوباره کپی نشود.
     *
     * @var array<int,bool>
     */
    private array $copiedFieldIds = [];

    /** پاسخ خطای یکدست برای عملیات درخت (کلاینت پیام را از data.message می‌خواند) */
    private function treeError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }
        return back()->with('error', $message);
    }

    /** جابجایی یک گزینه زیر field دیگر */
    public function reparentOption(Request $request, Category $category, CategoryField $field, FieldOption $option)
    {
        $request->validate([
            'field_id' => ['required', TenantRule::exists('category_fields')],
        ]);

        // همان منطق حلقه، از سمت گزینه: بردن گزینه زیر فیلدی که خودش داخل
        // زیرمجموعه‌ی همان گزینه است، زنجیره را می‌بندد.
        $targetField = CategoryField::find((int) $request->field_id);
        if ($targetField && in_array($option->id, $this->ancestorIds($targetField)['options'], true)) {
            return $this->treeError($request, 'نمی‌توان یک گزینه را زیر زیرمجموعه‌ی خودش برد.');
        }

        $option->update(['field_id' => $request->field_id]);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'گزینه جابجا شد.']);
        return back()->with('success', 'گزینه جابجا شد.');
    }

    /** کپی عمیق چند گزینه زیر یک field */
    public function batchCopyOptions(Request $request, Category $category, CategoryField $fieldTarget)
    {
        $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => [TenantRule::exists('field_options')],
        ]);

        if ($fieldTarget->category_id !== $category->id) {
            return $this->treeError($request, 'فیلد مقصد در این دسته‌بندی نیست.');
        }

        // گزینه فقط زیر فیلدی از نوع «گزینه» معنا دارد؛ UI جلویش را می‌گرفت ولی API نه.
        if ($fieldTarget->type !== 'option') {
            return $this->treeError($request, 'مقصد باید فیلدی از نوع «گزینه» باشد.');
        }

        $sources = FieldOption::whereIn('id', $request->option_ids)->get();

        // paste داخل زیرمجموعه‌ی خودِ گزینه‌ی منبع، پیمایش کپی را بی‌پایان می‌کند
        $ancestorOptions = $this->ancestorIds($fieldTarget)['options'];
        foreach ($sources as $opt) {
            if (in_array($opt->id, $ancestorOptions, true)) {
                return $this->treeError($request, 'نمی‌توان یک گزینه را داخل زیرمجموعه‌ی خودش paste کرد.');
            }
        }

        $this->copiedFieldIds = [];
        $createdIds = [];
        foreach ($sources as $opt) {
            $createdIds[] = $this->deepCopyOption($opt, $fieldTarget->id, [], $fieldTarget->category_id)->id;
        }

        if ($request->expectsJson()) return response()->json([
            'success'    => true,
            'message'    => count($sources) . ' گزینه کپی شد.',
            'option_ids' => $createdIds,   // برای undo لازم است
        ]);
        return back()->with('success', 'گزینه‌ها کپی شدند.');
    }

    /**
     * @param array<int,bool> $seen شناسه‌ی فیلدهای مسیر جاری، برای جلوگیری از حلقه
     * @param int|null $categoryId دسته‌ی مقصد؛ اگر null باشد دسته‌ی منبع استفاده می‌شود
     */
    private function deepCopyOption(FieldOption $opt, int $newFieldId, array $seen = [], ?int $categoryId = null): FieldOption
    {
        // فرزندان را پیش از هر درجی می‌خوانیم — به دلیلی که در deepCopyField آمده
        $sourceChildren = $opt->childFields()->orderBy('sort_order')->get();

        $newOpt = FieldOption::create([
            'field_id'   => $newFieldId,
            'label'      => $opt->label,
            // count() وقتی sort_orderها پیوسته نباشند (۰،۱،۵) مقدار تکراری می‌داد
            'sort_order' => (FieldOption::where('field_id', $newFieldId)->max('sort_order') ?? -1) + 1,
        ]);

        foreach ($sourceChildren as $cf) {
            if (isset($seen[$cf->id]) || isset($this->copiedFieldIds[$cf->id])) continue;
            $this->deepCopyField($cf, $newOpt->id, null, $cf->sort_order, $seen, $categoryId);
        }

        return $newOpt;
    }

    /**
     * @param array<int,bool> $seen شناسه‌ی فیلدهای مسیر جاری.
     *        parent_field_id و parent_option_id در دیتابیس FK ندارند، پس یک حلقه
     *        (فیلدی که جد خودش شود) از نظر ساختاری ممکن است و بدون این محافظ
     *        کپی تا سقف حافظه پیش می‌رفت.
     */
    private function deepCopyField(CategoryField $f, ?int $parentOptId, ?int $parentFieldId = null, ?int $sortOrder = null, array $seen = [], ?int $categoryId = null): CategoryField
    {
        $seen[$f->id] = true;

        // فرزندان *پیش از* درج خوانده می‌شوند. اگر بعد از insert لود شوند،
        // رکوردهای تازه‌ساخته می‌توانند وارد همین پیمایش شوند (وقتی مقصد داخل
        // زیرمجموعه‌ی منبع باشد) و چون شناسه‌شان در $seen نیست، کپی بی‌پایان می‌شود.
        $sourceOptions = $f->options()->orderBy('sort_order')->get();
        $sourceAlways  = $f->alwaysChildFields()->orderBy('sort_order')->get();

        $nf = CategoryField::create([
            'category_id'      => $categoryId ?? $f->category_id,
            'parent_option_id' => $parentOptId,
            'parent_field_id'  => $parentFieldId,
            'label'            => $f->label,
            'description'      => $f->description,
            'type'             => $f->type,
            'is_required'      => $f->is_required,
            'is_multiple'      => $f->is_multiple,
            'date_range'       => $f->date_range,
            'sort_order'       => $sortOrder ?? ($f->sort_order + 1),
        ]);

        $this->copiedFieldIds[$nf->id] = true;

        foreach ($sourceOptions as $opt) {
            $this->deepCopyOption($opt, $nf->id, $seen, $nf->category_id);
        }

        // زیرفیلدهای همیشگی هم بخشی از همین زیردرخت‌اند و باید همراه کپی شوند.
        // پیش‌تر این حلقه پشت relationLoaded() بود و چون هیچ‌کدام از نقاط شروع
        // این رابطه را eager load نمی‌کردند، عملاً هیچ‌وقت اجرا نمی‌شد.
        foreach ($sourceAlways as $child) {
            if (isset($seen[$child->id]) || isset($this->copiedFieldIds[$child->id])) continue;
            $this->deepCopyField($child, null, $nf->id, $child->sort_order, $seen, $nf->category_id);
        }

        return $nf;
    }

    // ==========================================
    // Recursive delete
    // ==========================================

    /**
     * حذف یک فیلد به همراه کل زیردرختش.
     *
     * `field_options.field_id` در دیتابیس cascade دارد، پس گزینه‌های خود فیلد
     * خودکار پاک می‌شوند — ولی `parent_option_id` و `parent_field_id` هیچ FK
     * ندارند. یعنی حذف ساده‌ی `$field->delete()` فیلدهای زیر گزینه‌هایش و
     * زیرفیلدهای همیشگی‌اش را یتیم می‌گذاشت: رکوردهایی که چون لیست ریشه با
     * whereNull(parent_*) فیلتر می‌شود دیگر هیچ‌جا رندر نمی‌شدند و برای همیشه
     * در جدول می‌ماندند.
     *
     * @param array<int,bool> $seen محافظ حلقه (parent_field_id بدون FK)
     */
    private function deleteFieldSubtree(CategoryField $field, array $seen = []): int
    {
        $seen[$field->id] = true;
        $count = 1;

        foreach ($field->options as $opt) {
            $count += $this->deleteOptionSubtree($opt, $seen, false);
        }

        foreach ($field->alwaysChildFields as $child) {
            if (isset($seen[$child->id])) continue;
            $count += $this->deleteFieldSubtree($child, $seen);
        }

        $field->delete();   // cascade خودِ گزینه‌ها را می‌برد

        return $count;
    }

    /**
     * حذف یک گزینه به همراه فیلدهای زیرش.
     *
     * @param bool $deleteSelf وقتی از deleteFieldSubtree صدا زده می‌شود false است،
     *        چون cascade خودِ گزینه را پاک می‌کند و delete دوباره بی‌فایده است.
     */
    private function deleteOptionSubtree(FieldOption $option, array $seen = [], bool $deleteSelf = true): int
    {
        $count = 1;

        foreach ($option->childFields as $cf) {
            if (isset($seen[$cf->id])) continue;
            $count += $this->deleteFieldSubtree($cf, $seen);
        }

        if ($deleteSelf) $option->delete();

        return $count;
    }

    /**
     * زنجیره‌ی اجداد یک فیلد، رو به بالا.
     *
     * والدِ یک فیلد یا فیلد دیگری است (parent_field_id) یا یک گزینه
     * (parent_option_id) که خودش زیر یک فیلد نشسته. خروجی:
     *   ['fields' => [id...], 'options' => [id...]]
     *
     * @return array{fields: int[], options: int[]}
     */
    private function ancestorIds(CategoryField $field): array
    {
        $fields  = [];
        $options = [];
        $current = $field;
        $guard   = 0;

        // guard: parent_field_id هیچ FK ندارد، پس حلقه ساختاراً ممکن است
        while ($current && $guard++ < 100) {
            if ($current->parent_field_id) {
                $current = CategoryField::find($current->parent_field_id);
            } elseif ($current->parent_option_id) {
                $opt = FieldOption::find($current->parent_option_id);
                if (!$opt) break;
                $options[] = $opt->id;
                $current   = CategoryField::find($opt->field_id);
            } else {
                break;
            }

            if (!$current) break;
            if (in_array($current->id, $fields, true)) break;   // حلقه
            $fields[] = $current->id;
        }

        return ['fields' => $fields, 'options' => $options];
    }
}
