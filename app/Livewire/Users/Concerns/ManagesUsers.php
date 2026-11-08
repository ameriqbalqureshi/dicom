<?php

namespace App\Livewire\Users\Concerns;

use App\Enums\Role;
use App\Models\Package;
use App\Models\User;
use App\Notifications\PhysicianVerified;
use App\Services\BestEffortNotifier;
use App\Services\Billing\SubscriptionManager;
use App\Services\Marketing\MarketingOfficers;
use App\Services\StudySharing;
use App\Services\UserDeleter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Administrator actions on a single user, shared by the user list and the user's own page. Each reports what it did in
 * the component's $status, or adds a "users" error.
 */
trait ManagesUsers
{
    public ?string $status = null;

    /**
     * Change a user's role. Only physicians can receive shared studies, so anyone who stops being one loses them. Someone
     * who becomes a marketing officer gets a referral code and stops being a client, and an officer must be settled up
     * with before they stop being one.
     */
    public function updateRole(int $userId, string $role, StudySharing $sharing, MarketingOfficers $officers): void
    {
        $this->authorize('manage-users');

        Validator::make(['role' => $role], ['role' => ['required', Rule::enum(Role::class)]])->validate();

        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->addError('users', 'You cannot change your own role.');

            return;
        }

        if ($role !== Role::MarketingOfficer->value && $user->hasUnsettledCommissions()) {
            $this->addError('users', "{$user->name} still has commission that has not been paid. Pay or reverse it first.");

            return;
        }

        $user->update(['role' => $role]);

        if ($user->isMarketingOfficer()) {
            $officers->ensureProfile($user);
            $user->forceFill(['referred_by' => null])->save();
        }

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
        BestEffortNotifier::send($user, new PhysicianVerified);
        $this->status = "{$user->name} is verified. They can now receive shared studies and sign reports.";
    }

    /**
     * Confirm a user's email address on their behalf, for people who cannot receive or follow the verification email.
     */
    public function verifyEmail(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if ($user->hasVerifiedEmail()) {
            $this->addError('users', "{$user->name}'s email address is already verified.");

            return;
        }

        $user->markEmailAsVerified();
        $this->status = "{$user->name}'s email address is now verified. They can use the service.";
    }

    public function resendVerificationEmail(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if ($user->hasVerifiedEmail()) {
            $this->addError('users', "{$user->name}'s email address is already verified.");

            return;
        }

        $user->sendEmailVerificationNotification();
        $this->status = "A new verification link was sent to {$user->email}.";
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

    /**
     * Delete a user and every study they uploaded. Returns the deleted user, or null if they may not be deleted.
     */
    protected function removeUser(int $userId, UserDeleter $deleter): ?User
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->addError('users', 'You cannot delete your own account.');

            return null;
        }

        if ($user->hasUnsettledCommissions()) {
            $this->addError('users', "{$user->name} still has commission that has not been paid. Pay or reverse it first.");

            return null;
        }

        $studyCount = $deleter->delete($user);
        $this->status = "{$user->name} was deleted".($studyCount > 0 ? ', with '.trans_choice('{1} their study|[2,*] their :count studies', $studyCount) : '').'.';

        return $user;
    }
}
