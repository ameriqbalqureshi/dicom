<div class="flex flex-col gap-4">
    @if (session('status'))
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-sm text-slate-300">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
                <label for="password" class="text-sm text-slate-300">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs text-sky-400 hover:text-sky-300">Forgot your password?</a>
            </div>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('password') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-700 bg-slate-950">
            Remember me
        </label>

        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

    <p class="text-center text-sm text-slate-400">
        New here? <a href="{{ route('register') }}" class="text-sky-400 hover:text-sky-300">Create an account</a>
    </p>
</div>
