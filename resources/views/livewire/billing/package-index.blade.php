<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Packages</h1>
            <p class="mt-1 text-sm text-slate-400">Each role's trial is given to new accounts. Changes apply to new purchases; current subscribers keep the limits they started with.</p>
        </div>
        @unless ($editing)
            <button type="button" wire:click="create" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">New package</button>
        @endunless
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('packages')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    @if ($editing)
        <form wire:submit="save" class="flex max-w-3xl flex-col gap-4 rounded-xl border border-sky-800 bg-slate-900 p-6">
            <h2 class="font-semibold">{{ $packageId ? 'Edit package' : 'New package' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Name</span>
                    <input wire:model="name" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('name') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">For</span>
                    <select wire:model="role" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->value }}">{{ Str::plural($roleOption->label()) }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                    <span class="text-slate-300">Description <span class="text-slate-500">(optional)</span></span>
                    <input wire:model="description" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('description') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Price ({{ $currency }})</span>
                    <input wire:model="price" type="number" min="0" @disabled($isTrial) class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 disabled:opacity-50">
                    @error('price') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Duration in days <span class="text-slate-500">(blank: never ends)</span></span>
                    <input wire:model="durationDays" type="number" min="1" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('durationDays') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="text-sm text-slate-300">Limits <span class="text-slate-500">(blank: unlimited, 0: not included)</span></legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['maxUploads' => 'Study uploads', 'maxStorageMb' => 'Storage (MB)', 'maxReports' => 'Reports signed', 'maxShares' => 'Shares', 'maxGuestLinks' => 'Guest links'] as $field => $label)
                        <label wire:key="field-{{ $field }}" class="flex flex-col gap-1 text-sm">
                            <span class="text-slate-400">{{ $label }}</span>
                            <input wire:model="{{ $field }}" type="number" min="0" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                            @error($field) <span class="text-red-400">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex flex-wrap gap-6 text-sm text-slate-300">
                <label class="flex items-center gap-2">
                    <input wire:model.live="isTrial" type="checkbox" class="rounded border-slate-600 bg-slate-950 accent-sky-500">
                    Trial for new accounts (free; replaces this role's current trial)
                </label>
                <label class="flex items-center gap-2">
                    <input wire:model="isActive" type="checkbox" class="rounded border-slate-600 bg-slate-950 accent-sky-500">
                    Offered
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save</button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-400 hover:text-white">Cancel</button>
            </div>
        </form>
    @endif

    @foreach ($roles as $roleOption)
        <section wire:key="role-{{ $roleOption->value }}" class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">{{ Str::plural($roleOption->label()) }}</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($packagesByRole->get($roleOption->value, collect()) as $package)
                    <div wire:key="package-{{ $package->id }}" @class(['flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-5', 'opacity-60' => ! $package->is_active])>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">{{ $package->name }}</h3>
                                @if ($package->is_trial)
                                    <span class="rounded bg-sky-900 px-1.5 py-0.5 text-xs text-sky-200">Trial</span>
                                @endif
                                @unless ($package->is_active)
                                    <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">Not offered</span>
                                @endunless
                            </div>
                            <div class="text-sm text-slate-400">
                                {{ $package->isFree() ? 'Free' : number_format($package->price).' '.$currency }}
                                · {{ $package->duration_days ? "{$package->duration_days} days" : 'never ends' }}
                                · {{ $package->subscriber_count }} current {{ Str::plural('subscriber', $package->subscriber_count) }}
                            </div>
                        </div>

                        @include('billing.partials.limits', ['limits' => $package])

                        <div class="mt-auto flex items-center gap-4 text-sm">
                            <button type="button" wire:click="edit({{ $package->id }})" class="text-slate-300 hover:text-white">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $package->id }})" class="text-slate-300 hover:text-white">{{ $package->is_active ? 'Withdraw' : 'Offer again' }}</button>
                            <button type="button" wire:click="delete({{ $package->id }})" wire:confirm="Delete {{ $package->name }}?" class="text-red-400 hover:text-red-300">Delete</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No packages yet.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
