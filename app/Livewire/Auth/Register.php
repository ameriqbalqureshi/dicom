<?php

namespace App\Livewire\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\PhysicianSignedUp;
use App\Services\BestEffortNotifier;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets people create their own account as a physician, technician or patient. New accounts start on their role's trial
 * package; physicians can use it straight away but must be verified by an administrator before they can receive shared
 * studies or sign reports.
 */
#[Layout('layouts::guest')]
#[Title('Create an account')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = Role::Physician->value;

    public string $registrationNumber = '';

    public string $qualifications = '';

    /**
     * The roles people may choose when signing up.
     *
     * @return list<Role>
     */
    public static function selfServiceRoles(): array
    {
        return [Role::Physician, Role::Technician, Role::Patient];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, self::selfServiceRoles()))],
            'registrationNumber' => [Rule::requiredIf($this->role === Role::Physician->value), 'nullable', 'string', 'max:100'],
            'qualifications' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'registrationNumber.required' => 'Physicians need to give their medical registration number so we can verify them.',
        ];
    }

    public function register(SubscriptionManager $subscriptions): void
    {
        $validated = $this->validate();
        $isPhysician = $validated['role'] === Role::Physician->value;

        $user = DB::transaction(function () use ($validated, $isPhysician, $subscriptions): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'registration_number' => $isPhysician ? trim($validated['registrationNumber']) : null,
                'qualifications' => $isPhysician && filled($validated['qualifications']) ? trim($validated['qualifications']) : null,
            ]);

            $subscriptions->startTrial($user);

            return $user;
        });

        rescue(fn () => event(new Registered($user)));

        if ($isPhysician) {
            BestEffortNotifier::send(User::admins()->get(), new PhysicianSignedUp($user));
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('verification.notice');
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'roles' => self::selfServiceRoles(),
        ]);
    }
}
