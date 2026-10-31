<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
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
}
