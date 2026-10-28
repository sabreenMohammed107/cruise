<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'first_name',
        'last_name',
        'email',
        'phone_dial',
        'phone',
        'date_of_birth',
        'nationality',
        'notification_prefs',
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
            'date_of_birth' => 'date',
            'role' => UserRole::class,
            'notification_prefs' => 'array',
        ];
    }

    /**
     * Keep Laravel's `name` column in step with first/last name, so built-in auth keeps working.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->first_name || $user->last_name) {
                $user->name = trim("{$user->first_name} {$user->last_name}");
            }
        });
    }

    /** "+966 501234567", or null without a number. */
    protected function fullPhone(): Attribute
    {
        return Attribute::get(fn () => $this->phone ? trim("{$this->phone_dial} {$this->phone}") : null);
    }

    /** Laravel's password broker calls this; our version is queued and in the site's style. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSupervisor(): bool
    {
        return $this->role === UserRole::Supervisor;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    /** Admins, supervisors and sellers: can sign in to the admin panel. */
    public function isStaff(): bool
    {
        return (bool) $this->role?->isStaff();
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', UserRole::Customer);
    }

    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', UserRole::staff());
    }

    public function scopeSellers(Builder $query): void
    {
        $query->where('role', UserRole::Seller);
    }

    /** Bookings this user made on the website. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Bookings assigned to this seller to follow up. */
    public function assignedBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'seller_id');
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Cruise::class, 'favorites')->withTimestamps();
    }
}
