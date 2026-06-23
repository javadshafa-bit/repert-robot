{{-- Visual tree: یک گزینه + زیرفیلدهایش --}}
<div class="vtree-node vtree-option-node"
     draggable="true"
     ondragstart="vtreeDragStart(event,'option',this)"
     ondragover="vtreeDragOver(event,this)"
     ondragleave="vtreeDragLeave(event,this)"
     ondrop="vtreeDrop(event,this)"
     onclick="vtreeNodeClick(event,this,'option')"
     data-option-id="{{ $option->id }}"
     data-field-id="{{ $option->field_id }}"
     data-label="{{ $option->label }}"
     title="{{ $option->label }}"
     style="cursor:grab">
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
