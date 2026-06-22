{{-- Visual tree: یک فیلد + گزینه‌هایش --}}
<div class="vtree-node vtree-type-{{ $field->type }}"
     title="{{ $field->label }}{{ $field->description ? ' — '.$field->description : '' }}"
     onclick="vtreeEditField(this)"
     data-field-id="{{ $field->id }}"
     data-label="{{ $field->label }}"
     data-description="{{ $field->description ?? '' }}"
     data-type="{{ $field->type }}"
     data-is-required="{{ $field->is_required ? '1' : '0' }}"
     data-is-multiple="{{ $field->is_multiple ? '1' : '0' }}"
     style="cursor:pointer">
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
@if($field->relationLoaded('alwaysChildFields') && $field->alwaysChildFields->isNotEmpty())
{{-- زیرفیلدهای همیشگی — بعد از پاسخ همیشه پرسیده می‌شوند --}}
<ul class="vtree-always-ul">
    @foreach($field->alwaysChildFields as $child)
    <li>
        <div class="vtree-always-connector" title="زیرفیلد همیشگی">↓</div>
        @include('admin.categories._vtree_field', ['field' => $child])
    </li>
    @endforeach
</ul>
@endif
