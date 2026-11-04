<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\ManagesUsers;
use App\Models\Package;
use App\Models\User;
use App\Services\Billing\PackageLimits;
use App\Services\UserDeleter;
use Illuminate\Contracts\View\View;
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
        ])->title($user->name);
    }
}
