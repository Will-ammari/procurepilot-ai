<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_REQUESTER = 'requester';
    public const ROLE_PROCUREMENT = 'procurement';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_FINANCE = 'finance';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'organization_id',
        'department_id',
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requester_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isProcurement(): bool
    {
        return $this->role === self::ROLE_PROCUREMENT;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isFinance(): bool
    {
        return $this->role === self::ROLE_FINANCE;
    }

    public function isRequester(): bool
    {
        return $this->role === self::ROLE_REQUESTER;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }
}
