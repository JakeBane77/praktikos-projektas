<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResource extends Model
{
    protected $fillable = [
        'user_id',
        'gold',
        'wood',
        'stone',
        'food',
        'lifetime_gold',
        'lifetime_wood',
        'lifetime_stone',
        'lifetime_food',
        'manual_collects',
        'last_produced_at',
        'last_collected_at',
    ];

    protected function casts(): array
    {
        return [
            'last_produced_at' => 'datetime',
            'last_collected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
