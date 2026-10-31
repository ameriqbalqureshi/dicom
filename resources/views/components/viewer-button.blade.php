@props(['label'])

<button type="button" aria-label="{{ $label }}" title="{{ $label }}"
    {{ $attributes->class('inline-flex h-8 min-w-8 items-center justify-center gap-1.5 rounded-md border border-slate-700 px-2 text-sm hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent') }}>
    {{ $slot }}
</button>
