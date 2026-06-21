{{-- Visual tree: یک فیلد + گزینه‌هایش --}}
<div class="vtree-node vtree-type-{{ $field->type }}" title="{{ $field->label }}{{ $field->description ? ' — '.$field->description : '' }}">
    <span class="vtree-badge">{{ $field->type_fa }}@if($field->is_multiple) ×چند@endif</span>
    <span class="vtree-label">{{ Str::limit($field->label, 22) }}</span>
</div>
@if($field->type === 'option' && $field->options->isNotEmpty())
<ul>
    @foreach($field->options as $opt)
    <li>
        @include('admin.categories._vtree_option', ['option' => $opt])
    </li>
    @endforeach
</ul>
@endif
