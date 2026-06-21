{{-- Visual tree container --}}
@if($category->fields->isEmpty())
    <div class="px-4 py-10 text-center text-sm text-gray-400">هنوز فیلدی تعریف نشده</div>
@else
<div class="vtree-wrap" dir="ltr">
    <ul class="vtree">
        @foreach($category->fields as $field)
        <li>
            @include('admin.categories._vtree_field', ['field' => $field])
        </li>
        @endforeach
    </ul>
</div>
@endif
