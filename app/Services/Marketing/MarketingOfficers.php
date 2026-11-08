<?php

namespace App\Services\Marketing;

use App\Enums\MarketingApplicationStatus;
use App\Enums\Role;
use App\Models\MarketingApplication;
use App\Models\MarketingProfile;
use App\Models\User;
use App\Notifications\MarketingApplicationApproved;
use App\Notifications\MarketingApplicationRejected;
use App\Services\BestEffortNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Brings marketing officers on board and keeps track of which officer each client belongs to.
 */
class MarketingOfficers
{
    /**
     * Create the officer's account from an approved application and invite them to set a password. The invitation goes to
     * the address they applied with, so that address needs no separate verification.
     *
     * @throws InvalidArgumentException
     */
    public function approve(MarketingApplication $application, User $admin): User
    {
        $this->ensurePending($application);

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($application->email)])->exists()) {
            throw new InvalidArgumentException("An account already uses {$application->email}. Change that account's role on its user page, then reject this application.");
        }

        $officer = DB::transaction(function () use ($application, $admin): User {
            $officer = User::create([
                'name' => $application->name,
                'email' => $application->email,
                'role' => Role::MarketingOfficer,
                'password' => Str::password(32),
            ]);
            $officer->forceFill(['email_verified_at' => now()])->save();

            $this->ensureProfile($officer, $application->phone);

            $application->forceFill([
                'status' => MarketingApplicationStatus::Approved,
                'user_id' => $officer->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $officer;
        });

        BestEffortNotifier::send($officer, new MarketingApplicationApproved);

        return $officer;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function reject(MarketingApplication $application, User $admin, ?string $reason): void
    {
        $this->ensurePending($application);

        $application->forceFill([
            'status' => MarketingApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        rescue(fn () => Notification::route('mail', $application->email)->notify(new MarketingApplicationRejected($reason)));
    }

    /**
     * Give an officer a profile with a referral code, unless they already have one.
     */
    public function ensureProfile(User $officer, ?string $phone = null): MarketingProfile
    {
        return $officer->marketingProfile()->firstOrCreate([], [
            'referral_code' => $this->generateReferralCode($officer->name),
            'phone' => $phone,
        ]);
    }

    /**
     * The working officer a referral code belongs to, or null if nobody can use the code now.
     */
    public function findByReferralCode(string $referralCode): ?User
    {
        $profile = MarketingProfile::query()
            ->where('referral_code', Str::upper(trim($referralCode)))
            ->whereNull('deactivated_at')
            ->with('user')
            ->first();

        return $profile?->user?->isMarketingOfficer() ? $profile->user : null;
    }

    /**
     * Whether a new client's email address is the officer's own, written differently (e.g. with dots or a +tag), which
     * would let an officer earn commission on themselves.
     */
    public function isSameMailbox(User $officer, string $email): bool
    {
        return $this->normalizeEmail($officer->email) === $this->normalizeEmail($email);
    }

    /**
     * Change which officer a client belongs to, keeping a record of the change. Commissions already earned stay with the
     * officer who earned them.
     *
     * @throws InvalidArgumentException
     */
    public function assignClient(User $client, ?User $officer, User $admin): void
    {
        if (! $client->canBeReferred()) {
            throw new InvalidArgumentException("{$client->name} cannot be a marketing officer's client.");
        }

        if ($officer !== null && ! $officer->isMarketingOfficer()) {
            throw new InvalidArgumentException("{$officer->name} is not a marketing officer.");
        }

        if ($client->referred_by === $officer?->id) {
            return;
        }

        DB::transaction(function () use ($client, $officer, $admin): void {
            $client->referralChanges()->create([
                'from_officer_name' => $client->referrer?->name,
                'to_officer_name' => $officer?->name,
                'changed_by' => $admin->id,
            ]);

            $client->forceFill(['referred_by' => $officer?->id])->save();
        });
    }

    /**
     * A code from the officer's first name and four random digits, such as AHMED4821.
     */
    private function generateReferralCode(string $name): string
    {
        $prefix = Str::upper(substr((string) preg_replace('/[^A-Za-z]/', '', Str::ascii($name)), 0, 6)) ?: 'REF';

        do {
            $referralCode = $prefix.random_int(1000, 9999);
        } while (MarketingProfile::query()->where('referral_code', $referralCode)->exists());

        return $referralCode;
    }

    private function normalizeEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', mb_strtolower(trim($email)), 2), 2, '');

        return str_replace('.', '', explode('+', $localPart)[0]).'@'.$domain;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function ensurePending(MarketingApplication $application): void
    {
        if (! $application->isPending()) {
            throw new InvalidArgumentException('This application has already been reviewed.');
        }
    }
}
