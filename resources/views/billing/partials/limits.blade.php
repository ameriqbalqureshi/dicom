{{-- The limits of a package or subscription: both have the same max_* columns, where null means unlimited. --}}
<ul class="flex flex-col gap-1 text-sm text-slate-300">
    @foreach (App\Enums\Quota::cases() as $quota)
        @php($limit = $limits->{$quota->limitColumn()})
        <li wire:key="limit-{{ $quota->value }}" @class(['text-slate-500 line-through' => $limit === 0])>
            {{ $quota->label() }}: {{ $limit === null ? 'Unlimited' : number_format($limit) }}
        </li>
    @endforeach
    <li>Storage: {{ $limits->max_storage_mb === null ? 'Unlimited' : ($limits->max_storage_mb >= 1024 ? round($limits->max_storage_mb / 1024, 1).' GB' : $limits->max_storage_mb.' MB') }}</li>
</ul>
