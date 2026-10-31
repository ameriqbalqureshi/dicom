<dl class="flex flex-col gap-3">
    @if (filled($report->indication))
        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-400">Clinical indication</dt>
            <dd class="mt-1 whitespace-pre-line text-slate-200">{{ $report->indication }}</dd>
        </div>
    @endif
    <div>
        <dt class="text-xs uppercase tracking-wide text-slate-400">Findings</dt>
        <dd class="mt-1 whitespace-pre-line text-slate-200">{{ $report->findings }}</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-slate-400">Impression</dt>
        <dd class="mt-1 whitespace-pre-line font-medium text-white">{{ $report->impression }}</dd>
    </div>
</dl>

@foreach ($report->addenda as $addendum)
    <div wire:key="addendum-{{ $addendum->id }}" class="border-l-2 border-sky-700 pl-3">
        <div class="text-xs uppercase tracking-wide text-sky-300">Addendum · {{ $addendum->created_at->toDayDateTimeString() }}</div>
        <p class="mt-1 whitespace-pre-line text-slate-200">{{ $addendum->body }}</p>
    </div>
@endforeach
