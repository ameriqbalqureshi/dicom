@props(['active' => false])

<a {{ $attributes->class([
    'rounded-md px-3 py-1.5 transition',
    'bg-slate-800 text-white' => $active,
    'text-slate-400 hover:bg-slate-800/60 hover:text-white' => ! $active,
]) }} @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
