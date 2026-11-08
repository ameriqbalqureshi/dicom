<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\ManagesUsers;
use App\Models\Package;
use App\Models\User;
use App\Services\Billing\PackageLimits;
use App\Services\Marketing\MarketingOfficers;
use App\Services\UserDeleter;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * An administrator's view of one user: their account, activity and package, the studies they uploaded, and the actions
 * an administrator can take on them.
 */
class UserShow extends Component
{
    use ManagesUsers, WithPagination;

    public User $user;

    public function mount(User $user): void
    {
        $this->authorize('manage-users');
    }

    public function deleteUser(int $userId, UserDeleter $deleter): void
    {
        if ($this->removeUser($userId, $deleter) !== null) {
            session()->flash('status', $this->status);
            $this->redirectRoute('users.index');
        }
    }

    /**
     * Change which marketing officer this user belongs to as a client. An empty value means none.
     */
    public function assignReferrer(string $officerId, MarketingOfficers $officers): void
    {
        $this->authorize('manage-marketing');

        $officer = $officerId === '' ? null : User::query()->marketingOfficers()->findOrFail((int) $officerId);

        try {
            $officers->assignClient($this->user->refresh(), $officer, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('users', $exception->getMessage());

            return;
        }

        $this->status = $officer === null
            ? "{$this->user->name} no longer belongs to a marketing officer."
            : "{$this->user->name} now belongs to {$officer->name}. Their future payments earn this officer commission.";
    }

    public function render(PackageLimits $limits): View
    {
        $user = $this->user->refresh();

        return view('livewire.users.user-show', [
            'studies' => $user->uploadedStudies()
                ->withCount(['series', 'instances', 'shares', 'reports'])
                ->orderByDesc('created_at')
                ->paginate(10),
            'stats' => [
                'Studies uploaded' => number_format($user->uploadedStudies()->count()),
                'Storage used' => number_format($limits->storageUsed($user) / 1024 / 1024, 1).' MB',
                'Reports written' => number_format($user->reports()->count()),
                'Shares made' => number_format($user->sharesMade()->count()),
                'Studies shared with them' => number_format($user->receivedShares()->count()),
                'Patient records' => number_format($user->patientRecords()->count()),
            ],
            'subscriptions' => $user->subscriptions()->with('package')->latest()->get(),
            'packages' => Package::query()->where('role', $user->role)->orderByDesc('is_trial')->orderBy('price')->get(),
            'currency' => config('billing.currency'),
            'officers' => $user->canBeReferred() ? User::query()->marketingOfficers()->orderBy('name')->get() : collect(),
            'referralChanges' => $user->referralChanges()->with('changedBy')->latest()->latest('id')->get(),
        ])->title($user->name);
    }
}
