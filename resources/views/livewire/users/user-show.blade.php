<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <a href="{{ route('users.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Users</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $user->name }}</h1>
            <p class="text-sm text-slate-400">
                <a href="mailto:{{ $user->email }}" class="hover:text-white">{{ $user->email }}</a>
                · <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">{{ $user->role->label() }}</span>
            </p>
        </div>

        @unless ($user->is(auth()->user()))
            @php($studyCount = $user->uploadedStudies()->count())
            <button type="button" wire:click="deleteUser({{ $user->id }})"
                wire:confirm="Delete {{ $user->name }}{{ $studyCount > 0 ? ' and the '.$studyCount.' '.Str::plural('study', $studyCount).' they uploaded, with their images and reports' : '' }}{{ $user->patientRecords()->exists() ? ', and their patient records' : '' }}? This cannot be undone."
                class="rounded-md border border-red-800 px-3 py-1.5 text-sm text-red-300 hover:bg-red-950">
                Delete user{{ $studyCount > 0 ? ' and '.$studyCount.' '.Str::plural('study', $studyCount) : '' }}
            </button>
        @endunless
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('users')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Account</h2>
            <dl class="flex flex-col gap-3 text-sm">
                <div class="flex flex-col gap-1">
                    <dt class="text-slate-400">Email address</dt>
                    <dd>
                        @if ($user->hasVerifiedEmail())
                            <span class="text-emerald-300">Verified</span>
                            <span class="text-slate-400">· {{ $user->email_verified_at->toFormattedDateString() }}</span>
                        @else
                            <span class="text-amber-300">Not verified</span>
                            <div class="mt-1 flex gap-3">
                                <button type="button" wire:click="verifyEmail({{ $user->id }})"
                                    wire:confirm="Mark {{ $user->email }} as verified? Only do this if you are sure the address belongs to {{ $user->name }}."
                                    class="text-sky-400 hover:text-sky-300">Mark as verified</button>
                                <button type="button" wire:click="resendVerificationEmail({{ $user->id }})" class="text-sky-400 hover:text-sky-300">Resend link</button>
                            </div>
                        @endif
                    </dd>
                </div>

                @if ($user->isPhysician())
                    <div class="flex flex-col gap-1">
                        <dt class="text-slate-400">Physician</dt>
                        <dd>
                            @if ($user->isVerifiedPhysician())
                                <span class="text-emerald-300">Verified</span>
                                <span class="text-slate-400">· {{ $user->physician_verified_at->toFormattedDateString() }}</span>
                            @else
                                <span class="text-amber-300">Not verified</span>
                                <button type="button" wire:click="verifyPhysician({{ $user->id }})"
                                    wire:confirm="Verify {{ $user->name }} (registration {{ $user->registration_number ?? 'not given' }})? They will be able to receive shared studies and sign reports."
                                    class="ml-1 text-sky-400 hover:text-sky-300">Verify</button>
                            @endif
                            <div class="text-slate-400">Registration: {{ $user->registration_number ?? 'not given' }}</div>
                            @if ($user->qualifications)
                                <div class="text-slate-400">{{ $user->qualifications }}</div>
                            @endif
                        </dd>
                    </div>
                @endif

                <div class="flex flex-col gap-1">
                    <dt class="text-slate-400">Role</dt>
                    <dd>
                        @if ($user->is(auth()->user()))
                            {{ $user->role->label() }}
                        @else
                            <select wire:change="updateRole({{ $user->id }}, $event.target.value)" aria-label="Role for {{ $user->name }}"
                                class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm">
                                @foreach (App\Enums\Role::cases() as $roleOption)
                                    <option value="{{ $roleOption->value }}" @selected($user->role === $roleOption)>{{ $roleOption->label() }}</option>
                                @endforeach
                            </select>
                        @endif
                    </dd>
                </div>

                <div class="flex flex-col gap-1">
                    <dt class="text-slate-400">Signed up</dt>
                    <dd>{{ $user->created_at->toFormattedDateString() }} <span class="text-slate-400">({{ $user->created_at->diffForHumans() }})</span></dd>
                </div>

                <div class="flex flex-col gap-1">
                    <dt class="text-slate-400">Last active</dt>
                    <dd>
                        @if ($user->last_seen_at)
                            {{ $user->last_seen_at->toDayDateTimeString() }} <span class="text-slate-400">({{ $user->last_seen_at->diffForHumans() }})</span>
                        @else
                            <span class="text-slate-400">Not recorded yet</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Usage</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                @foreach ($stats as $label => $value)
                    <div class="flex flex-col gap-1">
                        <dt class="text-slate-400">{{ $label }}</dt>
                        <dd class="text-lg font-semibold tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Package</h2>
            @if ($user->isAdmin())
                <p class="text-sm text-slate-400">Administrators do not need a package.</p>
            @else
                @php($current = $subscriptions->first(fn ($subscription) => $subscription->isCurrent()))
                <div class="text-sm">
                    @if ($current)
                        <div class="font-medium">{{ $current->package->name }}</div>
                        <div class="text-slate-400">{{ $current->ends_at ? 'Until '.$current->ends_at->toFormattedDateString() : 'Does not expire' }}</div>
                    @else
                        <span class="text-amber-300">No active package</span>
                    @endif
                </div>

                @if ($packages->isNotEmpty())
                    <select wire:change="assignPackage({{ $user->id }}, $event.target.value)" aria-label="Assign a package to {{ $user->name }}"
                        wire:confirm="Start this package for {{ $user->name }} now, without payment? It replaces their current package."
                        class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-slate-300">
                        <option value="">Assign package…</option>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($subscriptions->isNotEmpty())
                    <ul class="flex flex-col divide-y divide-slate-800 text-xs">
                        @foreach ($subscriptions as $subscription)
                            <li wire:key="subscription-{{ $subscription->id }}" class="flex justify-between gap-2 py-1.5">
                                <span>
                                    {{ $subscription->package->name }}
                                    @if ($subscription->amount > 0)
                                        <span class="text-slate-400">· {{ number_format($subscription->amount) }} {{ $currency }}</span>
                                    @endif
                                </span>
                                <span class="text-slate-400">{{ $subscription->statusLabel() }} · {{ $subscription->created_at->toFormattedDateString() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </section>
    </div>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">Studies uploaded</h2>

        <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Study date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Uploaded</th>
                        <th class="px-4 py-3 text-right">Series / Images</th>
                        <th class="px-4 py-3 text-right">Shares</th>
                        <th class="px-4 py-3 text-right">Reports</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($studies as $study)
                        <tr wire:key="study-{{ $study->id }}">
                            <td class="px-4 py-3">
                                @if ($study->isExpired())
                                    <span class="text-slate-400">{{ $study->patient_name ?? 'Unnamed' }}</span>
                                    <div class="text-xs text-amber-300">Expired, awaiting deletion</div>
                                @else
                                    <a href="{{ route('studies.show', $study) }}" class="font-medium text-sky-300 hover:text-sky-200">{{ $study->patient_name ?? 'Unnamed' }}</a>
                                    @if ($study->expires_at)
                                        <div class="text-xs text-amber-300">Deleted {{ $study->expires_at->diffForHumans() }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $study->study_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $study->study_description ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $study->created_at->toFormattedDateString() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $study->series_count }} / {{ $study->instances_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $study->shares_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $study->reports_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ $user->name }} has not uploaded any studies.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $studies->links() }}
    </section>
</div>
