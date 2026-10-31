<div class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6 text-sm">
    <h2 class="text-lg font-semibold">Check your email</h2>
    <p class="text-slate-300">
        We sent a link to <span class="font-medium text-white">{{ auth()->user()->email }}</span>. Open it to confirm your address and start your free trial.
    </p>

    @if (auth()->user()->isPhysician() && ! auth()->user()->isVerifiedPhysician())
        <p class="text-slate-400">We will also check your medical registration, so colleagues can share studies with you and you can sign reports.</p>
    @endif

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-emerald-300">{{ $status }}</p>
    @endif

    @error('resend')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-red-300">{{ $message }}</p>
    @enderror

    <button type="button" wire:click="resend" class="rounded-md border border-sky-600 px-4 py-2 text-sky-300 hover:bg-sky-950">
        <span wire:loading.remove wire:target="resend">Send the link again</span>
        <span wire:loading wire:target="resend">Sending…</span>
    </button>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-slate-400 hover:text-white">Sign out</button>
    </form>
</div>
