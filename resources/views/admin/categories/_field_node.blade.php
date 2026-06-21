{{-- Recursive partial: یک فیلد + گزینه‌هایش + زیرفیلدهایش --}}
{{-- $field: CategoryField, $category: Category, $depth: int --}}
@php $depth = $depth ?? 0; @endphp

<div class="field-node border rounded-lg {{ $depth === 0 ? 'border-gray-200 bg-white' : 'border-dashed border-gray-300 bg-gray-50' }} overflow-hidden">

    {{-- هدر فیلد --}}
    <div class="flex items-center gap-2 px-4 py-3 {{ $depth === 0 ? 'bg-white' : 'bg-gray-50' }}">
        @if($depth > 0)
            <span class="text-gray-300 text-xs font-mono shrink-0">{{ str_repeat('└─', $depth) }}</span>
        @endif
        <span class="text-xs font-mono text-gray-400 shrink-0">#{{ $field->id }}</span>
        <span class="text-sm font-semibold text-gray-800 flex-1 truncate">{{ $field->label }}</span>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0 {{ $field->type_color }}">
            {{ $field->type_fa }}@if($field->is_multiple) ×چند @endif
        </span>
        @if($field->is_required)
            <span class="text-xs text-red-400 shrink-0">*</span>
        @endif

        {{-- دکمه ویرایش --}}
        <button type="button"
                onclick="toggleEdit('field-edit-{{ $field->id }}')"
                class="text-xs text-blue-500 hover:text-blue-700 shrink-0">ویرایش</button>

        {{-- حذف فیلد --}}
        <form action="{{ route('admin.categories.fields.destroy', [$category, $field]) }}" method="POST"
              onsubmit="return confirm('این فیلد و تمام زیرمجموعه‌هایش حذف شوند؟');" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-red-500 hover:text-red-700 shrink-0">حذف</button>
        </form>
    </div>

    {{-- فرم ویرایش فیلد (مخفی) --}}
    <div id="field-edit-{{ $field->id }}" class="hidden px-4 pb-4 pt-2 bg-blue-50 border-t border-blue-100">
        <form action="{{ route('admin.categories.fields.update', [$category, $field]) }}" method="POST"
              class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @csrf @method('PUT')
            <div class="sm:col-span-2">
                <label class="block text-xs mb-1 text-gray-500">عنوان</label>
                <input type="text" name="label" value="{{ $field->label }}"
                       class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs mb-1 text-gray-500">توضیح راهنما</label>
                <input type="text" name="description" value="{{ $field->description }}"
                       class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer text-xs">
                    <input type="checkbox" name="is_required" value="1" {{ $field->is_required ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600">اجباری
                </label>
                @if($field->type !== 'option')
                <label class="flex items-center gap-2 cursor-pointer text-xs">
                    <input type="checkbox" name="is_multiple" value="1" {{ $field->is_multiple ? 'checked' : '' }}
                           class="rounded border-gray-300 text-purple-600">چندتایی
                </label>
                @endif
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="py-1.5 px-4 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700">
                    ذخیره
                </button>
            </div>
        </form>
    </div>

    {{-- اگر نوع گزینه است: نمایش گزینه‌ها و زیرفیلدهایشان --}}
    @if($field->type === 'option')
        <div class="border-t border-gray-100 px-4 py-3 space-y-3">

            @forelse($field->options as $option)
                <div class="border border-purple-200 rounded-lg overflow-hidden bg-purple-50">
                    {{-- هدر گزینه --}}
                    <div class="flex items-center gap-2 px-3 py-2 bg-purple-100">
                        <span class="text-purple-600 text-xs">⬡</span>
                        <span class="text-sm font-medium text-purple-800 flex-1">{{ $option->label }}</span>

                        {{-- حذف گزینه --}}
                        <form action="{{ route('admin.categories.fields.options.destroy', [$category, $field, $option]) }}"
                              method="POST" onsubmit="return confirm('این گزینه و تمام زیرفیلدهایش حذف شوند؟');" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">حذف گزینه</button>
                        </form>
                    </div>

                    {{-- زیرفیلدهای این گزینه --}}
                    <div class="px-3 py-2 space-y-2">
                        @forelse($option->childFields as $childField)
                            @include('admin.categories._field_node', [
                                'field'    => $childField,
                                'category' => $category,
                                'depth'    => $depth + 1,
                            ])
                        @empty
                            <p class="text-xs text-gray-400 italic">هنوز فیلدی برای این گزینه تعریف نشده</p>
                        @endforelse

                        {{-- فرم افزودن زیرفیلد به این گزینه --}}
                        <button type="button"
                                onclick="toggleEdit('add-subfield-{{ $option->id }}')"
                                class="text-xs text-purple-600 hover:text-purple-800 mt-1">
                            + افزودن فیلد برای این گزینه
                        </button>
                        <div id="add-subfield-{{ $option->id }}" class="hidden mt-2">
                            <form action="{{ route('admin.categories.fields.store', $category) }}" method="POST"
                                  class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-white border border-purple-200 rounded-lg p-3">
                                @csrf
                                <input type="hidden" name="parent_option_id" value="{{ $option->id }}">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs mb-1 text-gray-500">عنوان فیلد <span class="text-red-500">*</span></label>
                                    <input type="text" name="label"
                                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm"
                                           placeholder="مثلاً: عکس محل" required>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs mb-1 text-gray-500">توضیح (اختیاری)</label>
                                    <input type="text" name="description"
                                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1 text-gray-500">نوع فیلد</label>
                                    <select name="type" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                                        <option value="text">متن</option>
                                        <option value="option">گزینه</option>
                                        <option value="photo">عکس</option>
                                        <option value="link">لینک</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1 justify-end">
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="checkbox" name="is_required" value="1" checked
                                               class="rounded border-gray-300 text-blue-600">اجباری
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="checkbox" name="is_multiple" value="1"
                                               class="rounded border-gray-300 text-purple-600">چندتایی
                                    </label>
                                </div>
                                <div class="sm:col-span-2 flex gap-2">
                                    <button type="submit"
                                            class="flex-1 py-1.5 px-3 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700">
                                        افزودن فیلد
                                    </button>
                                    <button type="button"
                                            onclick="toggleEdit('add-subfield-{{ $option->id }}')"
                                            class="py-1.5 px-3 bg-gray-200 text-gray-600 rounded-lg text-xs">
                                        انصراف
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-gray-400 italic">هنوز گزینه‌ای تعریف نشده</p>
            @endforelse

            {{-- فرم افزودن گزینه جدید --}}
            <form action="{{ route('admin.categories.fields.options.store', [$category, $field]) }}" method="POST"
                  class="flex gap-2 mt-2">
                @csrf
                <input type="text" name="label"
                       class="flex-1 py-1.5 px-3 border border-purple-300 rounded-lg text-sm"
                       placeholder="عنوان گزینه جدید..." required>
                <button type="submit"
                        class="py-1.5 px-4 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 shrink-0">
                    + گزینه
                </button>
            </form>
        </div>
    @endif
</div>
