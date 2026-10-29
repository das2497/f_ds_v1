@props(['name', 'id' => null, 'rows' => 5, 'placeholder' => ''])

@php
    $id = $id ?? $name.'Field';
@endphp

<div class="rich-text-editor" data-rich-text-editor>
    <div class="rich-text-editor__toolbar">
        <button type="button" class="rich-text-editor__btn" data-rte-command="bold" title="Bold"><i class="bi bi-type-bold"></i></button>
        <button type="button" class="rich-text-editor__btn" data-rte-command="italic" title="Italic"><i class="bi bi-type-italic"></i></button>
        <button type="button" class="rich-text-editor__btn" data-rte-command="underline" title="Underline"><i class="bi bi-type-underline"></i></button>
        <span class="rich-text-editor__divider"></span>
        <button type="button" class="rich-text-editor__btn" data-rte-command="insertUnorderedList" title="Bullet list"><i class="bi bi-list-ul"></i></button>
        <button type="button" class="rich-text-editor__btn" data-rte-command="insertOrderedList" title="Numbered list"><i class="bi bi-list-ol"></i></button>
        <span class="rich-text-editor__divider"></span>
        <button type="button" class="rich-text-editor__btn" data-rte-command="formatBlock" data-rte-value="h3" title="Heading"><i class="bi bi-type-h3"></i></button>
        <button type="button" class="rich-text-editor__btn" data-rte-command="formatBlock" data-rte-value="p" title="Paragraph text"><i class="bi bi-paragraph"></i></button>
        <span class="rich-text-editor__divider"></span>
        <button type="button" class="rich-text-editor__btn" data-rte-command="removeFormat" title="Clear formatting"><i class="bi bi-eraser"></i></button>
    </div>
    <div class="rich-text-editor__surface" contenteditable="true" data-rte-surface data-placeholder="{{ $placeholder }}" style="min-height:{{ $rows * 1.6 }}rem"></div>
    <textarea name="{{ $name }}" id="{{ $id }}" data-rte-source {{ $attributes->merge(['class' => 'd-none']) }}>{{ $slot }}</textarea>
</div>
