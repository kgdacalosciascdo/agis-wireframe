<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'office_id',
        'employee_id',
        'username',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'name',
        'initials',
        'position',
        'employment_type',
        'contact_number',
        'birth_date',
        'is_office_head',
        'password',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'lock_version',
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
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'is_office_head' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function iapCapacities(): HasMany
    {
        return $this->hasMany(IapAuditorCapacity::class);
    }

    public function iapUnavailability(): HasMany
    {
        return $this->hasMany(IapAuditorUnavailability::class);
    }

    public function iapSkills(): HasMany
    {
        return $this->hasMany(IapAuditorSkill::class);
    }

    public function changes(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->role !== null
            && (in_array($this->role->code, $roles, true) || in_array($this->role->name, $roles, true));
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active || ! $this->role?->is_active) {
            return false;
        }

        return $this->role->permissions->contains('code', $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission) => $this->hasPermission($permission));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return collect($permissions)->every(fn (string $permission) => $this->hasPermission($permission));
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
