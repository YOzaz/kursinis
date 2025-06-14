<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'is_active',
        'last_used_at',
        'usage_stats',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'usage_stats' => 'array',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMaskedApiKeyAttribute(): string
    {
        if (empty($this->api_key)) {
            return '';
        }
        
        $key = $this->api_key;
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }

    public function updateUsageStats(array $stats): void
    {
        $currentStats = $this->usage_stats ?? [];
        $this->update([
            'usage_stats' => array_merge($currentStats, $stats),
            'last_used_at' => now(),
        ]);
    }
}
