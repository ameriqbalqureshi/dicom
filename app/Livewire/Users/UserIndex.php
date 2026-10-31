<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
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

    public function createUser(): void
    {
        $this->authorize('manage-users');

        User::create($this->validate());

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
        }
        $this->status = "{$user->name} is now a ".Role::from($role)->label().'.';
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
            'users' => User::query()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
