<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-semibold tracking-tight">Users</h1>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('users')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is(auth()->user()))
                                <span class="text-slate-300">{{ $user->role->label() }}</span>
                            @else
                                <select wire:change="updateRole({{ $user->id }}, $event.target.value)" aria-label="Role for {{ $user->name }}"
                                    class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm">
                                    @foreach ($roles as $roleOption)
                                        <option value="{{ $roleOption->value }}" @selected($user->role === $roleOption)>{{ $roleOption->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @unless ($user->is(auth()->user()))
                                <button type="button" wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                    class="text-red-400 hover:text-red-300">
                                    Delete
                                </button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form wire:submit="createUser" class="flex max-w-xl flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="font-semibold">Add a user</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-sm text-slate-300">Name</label>
                <input wire:model="name" id="name" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                @error('name') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-sm text-slate-300">Email</label>
                <input wire:model="email" id="email" type="email" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm text-slate-300">Password</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                @error('password') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="role" class="text-sm text-slate-300">Role</label>
                <select wire:model="role" id="role" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endforeach
                </select>
                @error('role') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Create user</button>
        </div>
    </form>
</div>
