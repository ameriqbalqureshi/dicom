<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Enums\Role;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'qualifications',
        'registration_number',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'signature_path',
    ];

    /**
     * The disk physicians' signature images are kept on. It is private: signatures are only embedded in report PDFs.
     */
    public const SIGNATURE_DISK = 'signatures';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'physician_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', Role::Admin);
    }

    /**
     * Users who have not used the service since the given time: those last seen before it, and those never seen whose
     * account is older than it.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInactiveSince(Builder $query, CarbonInterface $since): void
    {
        $query->where(function (Builder $query) use ($since) {
            $query->where('last_seen_at', '<', $since)
                ->orWhere(fn (Builder $query) => $query->whereNull('last_seen_at')->where('created_at', '<', $since));
        });
    }

    /**
     * The non-administrator account with the given user ID or email address.
     */
    public static function findAccountHolder(string $idOrEmail): ?self
    {
        $idOrEmail = trim($idOrEmail);

        return static::query()
            ->where('role', '!=', Role::Admin)
            ->when(
                ctype_digit($idOrEmail),
                fn (Builder $query) => $query->whereKey((int) $idOrEmail),
                fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [mb_strtolower($idOrEmail)]),
            )
            ->first();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isPhysician(): bool
    {
        return $this->role === Role::Physician;
    }

    public function isMarketingOfficer(): bool
    {
        return $this->role === Role::MarketingOfficer;
    }

    /**
     * Whether the user works with studies and so has packages. Administrators and marketing officers do not.
     */
    public function usesPackages(): bool
    {
        return ! $this->isAdmin() && ! $this->isMarketingOfficer();
    }

    /**
     * Whether the user can be a marketing officer's client.
     */
    public function canBeReferred(): bool
    {
        return $this->usesPackages();
    }

    /**
     * The name marketing officers see for this user. Patients' surnames are shortened, because who uses the service as a
     * patient is health information.
     */
    public function nameForMarketing(): string
    {
        if ($this->role !== Role::Patient) {
            return $this->name;
        }

        $nameParts = preg_split('/\s+/u', trim($this->name));
        $firstName = array_shift($nameParts);

        return $nameParts === []
            ? $firstName
            : $firstName.' '.mb_strtoupper(mb_substr((string) end($nameParts), 0, 1)).'.';
    }

    /**
     * Whether this officer has commission that has not been paid or cancelled yet.
     */
    public function hasUnsettledCommissions(): bool
    {
        return $this->commissions()->where('status', CommissionStatus::Pending)->exists();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeMarketingOfficers(Builder $query): void
    {
        $query->where('role', Role::MarketingOfficer);
    }

    /**
     * @return HasOne<MarketingProfile, $this>
     */
    public function marketingProfile(): HasOne
    {
        return $this->hasOne(MarketingProfile::class);
    }

    /**
     * The marketing officer who brought this user in.
     *
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    /**
     * The clients this marketing officer brought in.
     *
     * @return HasMany<User, $this>
     */
    public function referredClients(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    /**
     * What this marketing officer has earned.
     *
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'officer_id');
    }

    /**
     * What this marketing officer has been paid.
     *
     * @return HasMany<CommissionPayout, $this>
     */
    public function commissionPayouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class, 'officer_id');
    }

    /**
     * Changes administrators have made to which officer this client belongs to.
     *
     * @return HasMany<ReferralChange, $this>
     */
    public function referralChanges(): HasMany
    {
        return $this->hasMany(ReferralChange::class, 'client_id');
    }

    /**
     * Whether the user is a physician an administrator has verified. Physicians who signed up themselves can try the
     * service, but cannot receive shared studies or sign reports until they are verified.
     */
    public function isVerifiedPhysician(): bool
    {
        return $this->isPhysician() && $this->physician_verified_at !== null;
    }

    public function markPhysicianVerified(): void
    {
        $this->forceFill(['physician_verified_at' => now()])->save();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeVerifiedPhysicians(Builder $query): void
    {
        $query->where('role', Role::Physician)->whereNotNull('physician_verified_at');
    }

    /**
     * Package purchases, including the trial, pending purchases and past packages.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The signature image as a data URI, for embedding in report PDFs, or null if the user has not uploaded one.
     */
    public function signatureDataUri(): ?string
    {
        $disk = Storage::disk(self::SIGNATURE_DISK);

        if ($this->signature_path === null || ! $disk->exists($this->signature_path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($this->signature_path).';base64,'.base64_encode($disk->get($this->signature_path));
    }

    /**
     * @return HasMany<Study, $this>
     */
    public function uploadedStudies(): HasMany
    {
        return $this->hasMany(Study::class, 'uploaded_by');
    }

    /**
     * Shares of other people's studies with this user, including those still awaiting acceptance.
     *
     * @return HasMany<StudyShare, $this>
     */
    public function receivedShares(): HasMany
    {
        return $this->hasMany(StudyShare::class);
    }

    /**
     * How many requests wait for this physician: studies patients want to share, and given-up studies they can keep.
     */
    public function openRequestCount(): int
    {
        return $this->receivedShares()->pending()->count() + Study::query()->sharedWith($this)->awaitingHandover()->count();
    }

    /**
     * Shares this user has made, of their own studies or forwarded ones.
     *
     * @return HasMany<StudyShare, $this>
     */
    public function sharesMade(): HasMany
    {
        return $this->hasMany(StudyShare::class, 'shared_by');
    }

    /**
     * The physician's own records of their patients.
     *
     * @return HasMany<PatientRecord, $this>
     */
    public function patientRecords(): HasMany
    {
        return $this->hasMany(PatientRecord::class, 'owner_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'author_id');
    }
}
