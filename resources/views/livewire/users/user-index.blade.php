<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
        <button type="button" wire:click="export" class="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-300 hover:border-slate-500 hover:text-white">
            Export list as CSV
        </button>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('users')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    <div class="flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-400">Search</span>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Name or email"
                class="w-64 rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5 focus:border-sky-500 focus:outline-none">
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-400">Role</span>
            <select wire:model.live="roleFilter" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                <option value="">All roles</option>
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-400">Show</span>
            <select wire:model.live="show" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                <option value="">Everyone</option>
                <option value="email-unverified">Email not verified ({{ $counts['email-unverified'] }})</option>
                <option value="physician-unverified">Physicians to verify ({{ $counts['physician-unverified'] }})</option>
                <option value="inactive">Inactive users</option>
            </select>
        </label>

        @if ($show === 'inactive')
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-400">Not active for</span>
                <select wire:model.live="inactiveDays" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                    @foreach ($inactivityPeriods as $days)
                        <option value="{{ $days }}">{{ $days }} days or more</option>
                    @endforeach
                </select>
            </label>
        @endif

        <span class="ml-auto text-sm text-slate-400">{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }}</span>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Package</th>
                    <th class="px-4 py-3">Last active</th>
                    <th class="px-4 py-3 text-right">Studies</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $user) }}" class="font-medium text-white hover:text-sky-300">{{ $user->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ $user->email }}
                            @unless ($user->hasVerifiedEmail())
                                <div class="mt-1 text-xs">
                                    <span class="text-amber-300">Email not verified</span>
                                    <button type="button" wire:click="verifyEmail({{ $user->id }})"
                                        wire:confirm="Mark {{ $user->email }} as verified? Only do this if you are sure the address belongs to {{ $user->name }}."
                                        class="ml-1 text-sky-400 hover:text-sky-300">Verify</button>
                                </div>
                            @endunless
                        </td>
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

                            @if ($user->isPhysician())
                                <div class="mt-1 text-xs">
                                    @if ($user->isVerifiedPhysician())
                                        <span class="text-emerald-300">Verified</span>
                                    @else
                                        <span class="text-amber-300">Not verified</span>
                                        @if ($user->registration_number)
                                            <span class="text-slate-400">· reg. {{ $user->registration_number }}</span>
                                        @endif
                                        <button type="button" wire:click="verifyPhysician({{ $user->id }})"
                                            wire:confirm="Verify {{ $user->name }} (registration {{ $user->registration_number ?? 'not given' }})? They will be able to receive shared studies and sign reports."
                                            class="ml-1 text-sky-400 hover:text-sky-300">Verify</button>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @unless ($user->isAdmin())
                                @php($currentSubscription = $user->subscriptions->first())
                                <div class="text-slate-300">
                                    {{ $currentSubscription?->package->name ?? 'None' }}
                                    @if ($currentSubscription?->ends_at)
                                        <span class="text-xs text-slate-400">· until {{ $currentSubscription->ends_at->toFormattedDateString() }}</span>
                                    @endif
                                </div>
                                @if ($packagesByRole->has($user->role->value))
                                    <select wire:change="assignPackage({{ $user->id }}, $event.target.value)" aria-label="Assign a package to {{ $user->name }}"
                                        wire:confirm="Start this package for {{ $user->name }} now, without payment? It replaces their current package."
                                        class="mt-1 rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-300">
                                        <option value="">Assign package…</option>
                                        @foreach ($packagesByRole->get($user->role->value) as $package)
                                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-slate-300" title="{{ $user->last_seen_at?->toDayDateTimeString() }}">
                            {{ $user->last_seen_at?->diffForHumans() ?? 'Not recorded' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ number_format($user->uploaded_studies_count) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('users.show', $user) }}" class="text-sky-400 hover:text-sky-300">Manage</a>
                            @unless ($user->is(auth()->user()))
                                <button type="button" wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Delete {{ $user->name }}{{ $user->uploaded_studies_count > 0 ? ' and the '.$user->uploaded_studies_count.' '.Str::plural('study', $user->uploaded_studies_count).' they uploaded' : '' }}{{ $user->isPhysician() ? ', and any patient records they keep' : '' }}? This cannot be undone."
                                    class="ml-3 text-red-400 hover:text-red-300">
                                    Delete
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No users match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}

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
