{{-- Visual tree: یک گزینه + زیرفیلدهایش --}}
<div class="vtree-node vtree-option-node"
     title="{{ $option->label }}"
     onclick="vtreeEditOption(this)"
     data-option-id="{{ $option->id }}"
     data-field-id="{{ $option->field_id }}"
     data-label="{{ $option->label }}"
     style="cursor:pointer">
    <span class="vtree-label">{{ Str::limit($option->label, 18) }}</span>
</div>
@if($option->childFields->isNotEmpty())
<ul>
    @foreach($option->childFields as $field)
    <li>
        @include('admin.categories._vtree_field', ['field' => $field])
    </li>
    @endforeach
</ul>
@endif
