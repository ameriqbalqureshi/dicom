<form wire:submit="login" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
    <div class="flex flex-col gap-1.5">
        <label for="email" class="text-sm text-slate-300">Email</label>
        <input wire:model="email" id="email" type="email" autocomplete="username" autofocus required
            class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1.5">
        <label for="password" class="text-sm text-slate-300">Password</label>
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
