<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'language',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin']);
    }

    public function getApiKey(string $provider): ?string
    {
        $apiKey = $this->apiKeys()->where('provider', $provider)->where('is_active', true)->first();
        return $apiKey?->getDecryptedApiKey();
    }

    public function hasApiKey(string $provider): bool
    {
        return $this->apiKeys()->where('provider', $provider)->where('is_active', true)->exists();
    }

    public function getLanguage(): string
    {
        return $this->language ?? 'lt';
    }

    public function setLanguage(string $language): void
    {
        $supportedLanguages = ['lt', 'en'];
        if (in_array($language, $supportedLanguages)) {
            $this->language = $language;
            $this->save();
        }
    }
}
