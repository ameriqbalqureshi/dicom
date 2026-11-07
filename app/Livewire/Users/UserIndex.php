<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Livewire\Users\Concerns\ManagesUsers;
use App\Models\Package;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Services\BestEffortNotifier;
use App\Services\Billing\SubscriptionManager;
use App\Services\UserDeleter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Users')]
class UserIndex extends Component
{
    use ManagesUsers, WithPagination;

    /**
     * The choices for how long someone must have been away to count as inactive, in days.
     */
    public const INACTIVITY_PERIODS = [30, 60, 90, 180, 365];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    /**
     * Narrows the list to users needing attention: "email-unverified", "physician-unverified" or "inactive".
     */
    #[Url(except: '')]
    public string $show = '';

    #[Url(except: 90)]
    public int $inactiveDays = 90;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = Role::Physician->value;

    public function mount(): void
    {
        $this->authorize('manage-users');

        $this->status = session('status');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'show', 'inactiveDays'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * Create an account and email the person about it. The administrator vouches for the email address, and for
     * physicians' registration, and everyone except administrators starts on their role's trial package.
     */
    public function createUser(SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-users');

        $user = User::create($this->validate());
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($user->isPhysician()) {
            $user->markPhysicianVerified();
        }

        if (! $user->isAdmin()) {
            $subscriptions->startTrial($user);
        }

        BestEffortNotifier::send($user, new AccountCreated(auth()->user()));

        $this->reset('name', 'email', 'password', 'role');
        $this->status = 'User created.';
    }

    public function deleteUser(int $userId, UserDeleter $deleter): void
    {
        $this->removeUser($userId, $deleter);
    }

    /**
     * Download the users currently listed as a CSV file, e.g. to contact inactive users.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('manage-users');

        $users = $this->filteredUsers()->withCount('uploadedStudies')->orderBy('name')->lazy();

        return response()->streamDownload(function () use ($users): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Role', 'Email verified', 'Signed up', 'Last active', 'Studies uploaded']);

            foreach ($users as $user) {
                fputcsv($output, [
                    $user->name,
                    $user->email,
                    $user->role->label(),
                    $user->hasVerifiedEmail() ? 'Yes' : 'No',
                    $user->created_at?->toDateString(),
                    $user->last_seen_at?->toDateTimeString() ?? 'Not recorded',
                    $user->uploaded_studies_count,
                ]);
            }

            fclose($output);
        }, 'users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<User>
     */
    private function filteredUsers(): Builder
    {
        $inactiveDays = in_array($this->inactiveDays, self::INACTIVITY_PERIODS, true) ? $this->inactiveDays : 90;

        return User::query()
            ->when(trim($this->search) !== '', function (Builder $query) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when(Role::tryFrom($this->roleFilter), fn (Builder $query, Role $role) => $query->where('role', $role))
            ->when($this->show === 'email-unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($this->show === 'physician-unverified', fn (Builder $query) => $query->where('role', Role::Physician)->whereNull('physician_verified_at'))
            ->when($this->show === 'inactive', fn (Builder $query) => $query->inactiveSince(now()->subDays($inactiveDays)));
    }

    public function render(): View
    {
        return view('livewire.users.user-index', [
            'users' => $this->filteredUsers()
                ->with(['subscriptions' => fn ($query) => $query->current()->with('package')->latest('starts_at')])
                ->withCount('uploadedStudies')
                ->orderByRaw('role = ? and physician_verified_at is null desc', [Role::Physician->value])
                ->orderBy('name')
                ->paginate(25),
            'packagesByRole' => Package::query()->orderByDesc('is_trial')->orderBy('price')->get()->groupBy(fn (Package $package) => $package->role->value),
            'roles' => Role::cases(),
            'inactivityPeriods' => self::INACTIVITY_PERIODS,
            'counts' => [
                'email-unverified' => User::query()->whereNull('email_verified_at')->count(),
                'physician-unverified' => User::query()->where('role', Role::Physician)->whereNull('physician_verified_at')->count(),
            ],
        ]);
    }
}
