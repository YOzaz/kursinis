<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
        
        try {
            $key = Crypt::decryptString($this->api_key);
        } catch (\Exception $e) {
            // If decryption fails, assume it's not encrypted
            $key = $this->api_key;
        }
        
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
    
    /**
     * Get decrypted API key.
     */
    public function getDecryptedApiKey(): string
    {
        if (empty($this->api_key)) {
            return '';
        }
        
        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Exception $e) {
            // If decryption fails, return as is (for backward compatibility)
            return $this->api_key;
        }
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
