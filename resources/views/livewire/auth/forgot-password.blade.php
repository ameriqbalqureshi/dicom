<div class="flex flex-col gap-4">
    <form wire:submit="sendLink" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <p class="text-sm text-slate-300">Enter your email address and we will send you a link to choose a new password.</p>

        @if ($status)
            <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
        @endif

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-sm text-slate-300">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
            <span wire:loading.remove wire:target="sendLink">Email me a reset link</span>
            <span wire:loading wire:target="sendLink">Sending…</span>
        </button>
    </form>

    <p class="text-center text-sm text-slate-400">
        <a href="{{ route('login') }}" class="text-sky-400 hover:text-sky-300">Back to sign in</a>
    </p>
</div>
