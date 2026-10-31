<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\Package;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Notifications\PhysicianVerified;
use App\Services\Billing\SubscriptionManager;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class UserIndex extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = Role::Physician->value;

    public ?string $status = null;

    public function mount(): void
    {
        $this->authorize('manage-users');
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

        $user->notify(new AccountCreated(auth()->user()));

        $this->reset('name', 'email', 'password', 'role');
        $this->status = 'User created.';
    }

    /**
     * Change a user's role. Only physicians can receive shared studies, so anyone who stops being one loses them.
     */
    public function updateRole(int $userId, string $role, StudySharing $sharing): void
    {
        $this->authorize('manage-users');

        Validator::make(['role' => $role], ['role' => ['required', Rule::enum(Role::class)]])->validate();

        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->addError('users', 'You cannot change your own role.');

            return;
        }

        $user->update(['role' => $role]);

        if (! $user->isPhysician()) {
            $sharing->revokeReceivedBy($user);
        } elseif ($user->physician_verified_at === null) {
            $user->markPhysicianVerified();
        }
        $this->status = "{$user->name} is now a ".Role::from($role)->label().'.';
    }

    public function verifyPhysician(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if (! $user->isPhysician()) {
            $this->addError('users', "{$user->name} is not a physician.");

            return;
        }

        $user->markPhysicianVerified();
        $user->notify(new PhysicianVerified);
        $this->status = "{$user->name} is verified. They can now receive shared studies and sign reports.";
    }

    /**
     * Start a package for a user without payment, replacing their current one.
     */
    public function assignPackage(int $userId, string $packageId, SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        if ($packageId === '') {
            return;
        }

        $user = User::findOrFail($userId);
        $package = Package::query()->where('role', $user->role)->findOrFail((int) $packageId);

        $subscriptions->assign($user, $package, auth()->user());
        $this->status = "{$user->name} now has the {$package->name} package.";
    }

    public function deleteUser(int $userId, StudySharing $sharing): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->addError('users', 'You cannot delete your own account.');

            return;
        }

        $sharing->revokeInvolving($user);
        $user->delete();
        $this->status = "{$user->name} was deleted.";
    }

    public function render(): View
    {
        return view('livewire.users.user-index', [
            'users' => User::query()
                ->with(['subscriptions' => fn ($query) => $query->current()->with('package')->latest('starts_at')])
                ->orderByRaw('role = ? and physician_verified_at is null desc', [Role::Physician->value])
                ->orderBy('name')
                ->get(),
            'packagesByRole' => Package::query()->orderByDesc('is_trial')->orderBy('price')->get()->groupBy(fn (Package $package) => $package->role->value),
            'roles' => Role::cases(),
        ]);
    }
}
