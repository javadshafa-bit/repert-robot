<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use Illuminate\Http\Request;
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
            'name' => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.categories.edit', $category)->with('success', 'دسته‌بندی ساخته شد. حالا فیلدها را اضافه کنید.');
    }

    public function edit(Category $category)
    {
        $category->load('fields');
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $category->update([
            'name' => $request->name,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'دسته‌بندی با موفقیت ویرایش شد.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'دسته‌بندی حذف شد.');
    }

    // Fields
    public function storeField(Request $request, Category $category)
    {
        $request->validate([
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0',
            'type' => ['required', 'string', Rule::in(['text', 'photo', 'document'])],
        ]);

        $category->fields()->create([
            'label' => $request->label,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'type' => $request->type,
            'is_required' => $request->boolean('is_required', true),
        ]);

        return back()->with('success', 'فیلد اضافه شد.');
    }

    public function updateField(Request $request, Category $category, CategoryField $field)
    {
        $request->validate([
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $field->update([
            'label' => $request->label,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? $field->sort_order,
            'is_required' => $request->boolean('is_required'),
        ]);

        return back()->with('success', 'فیلد ویرایش شد.');
    }

    public function destroyField(Category $category, CategoryField $field)
    {
        $field->delete();
        return back()->with('success', 'فیلد حذف شد.');
    }
}
