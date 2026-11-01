<div class="flex flex-col gap-4">
    <form wire:submit="register" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <div class="flex flex-col gap-1.5">
            <span class="text-sm text-slate-300">I am a</span>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($roles as $roleOption)
                    <label wire:key="role-{{ $roleOption->value }}"
                        class="cursor-pointer rounded-md border px-2 py-2 text-center text-sm {{ $role === $roleOption->value ? 'border-sky-500 bg-sky-950 text-white' : 'border-slate-700 text-slate-300 hover:border-slate-500' }}">
                        <input wire:model.live="role" type="radio" value="{{ $roleOption->value }}" class="sr-only">
                        {{ $roleOption->label() }}
                    </label>
                @endforeach
            </div>
            @error('role') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm text-slate-300">Full name</label>
            <input wire:model="name" id="name" type="text" autocomplete="name" required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('name') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-sm text-slate-300">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        @if ($role === App\Enums\Role::Physician->value)
            <div class="flex flex-col gap-1.5">
                <label for="registrationNumber" class="text-sm text-slate-300">Medical registration number</label>
                <input wire:model="registrationNumber" id="registrationNumber" type="text" required
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('registrationNumber') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="qualifications" class="text-sm text-slate-300">Qualifications <span class="text-slate-500">(optional)</span></label>
                <input wire:model="qualifications" id="qualifications" type="text" placeholder="e.g. MBBS, FCPS (Cardiology)"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                @error('qualifications') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-sm text-slate-300">Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password" required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            @error('password') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password_confirmation" class="text-sm text-slate-300">Confirm password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" required
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>

        <p class="text-xs text-slate-400">
            You start with a free trial. Choose a package any time to do more.
            @if ($role === App\Enums\Role::Physician->value)
                We verify physicians' registration before they can receive shared studies or sign reports.
            @endif
            By creating an account you agree to our <a href="{{ route('terms') }}" class="text-sky-400 hover:text-sky-300" target="_blank">terms</a>
            and <a href="{{ route('privacy') }}" class="text-sky-400 hover:text-sky-300" target="_blank">privacy policy</a>.
        </p>

        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
            <span wire:loading.remove wire:target="register">Create account</span>
            <span wire:loading wire:target="register">Creating account…</span>
        </button>
    </form>

    <p class="text-center text-sm text-slate-400">
        Already have an account? <a href="{{ route('login') }}" class="text-sky-400 hover:text-sky-300">Sign in</a>
    </p>
</div>
