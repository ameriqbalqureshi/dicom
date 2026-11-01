<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isPhysician(): bool
    {
        return $this->role === Role::Physician;
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
     * Shares this user has made, of their own studies or forwarded ones.
     *
     * @return HasMany<StudyShare, $this>
     */
    public function sharesMade(): HasMany
    {
        return $this->hasMany(StudyShare::class, 'shared_by');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'author_id');
    }
}
