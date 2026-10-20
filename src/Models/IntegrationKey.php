<?php

namespace SergeyBruhin\NovaSupportRays\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Nova user's personal SupportRays integration key: the bearer token every
 * tool request is made with, so each action is that person's, under their name.
 *
 * @property int $user_id
 * @property string $email
 * @property int|null $key_id
 * @property string $token
 * @property Carbon|null $expires_at
 */
class IntegrationKey extends Model
{
    protected $table = 'nova_supportrays_keys';

    protected $fillable = ['user_id', 'email', 'key_id', 'token', 'expires_at'];

    protected $hidden = ['token'];

    protected $casts = [
        'token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public static function forUser(int|string $userId): ?self
    {
        return static::query()->where('user_id', $userId)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
