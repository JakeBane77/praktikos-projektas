<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Minigame extends Model
{
    protected $fillable = [
        'user_id',
        'resource',
        'completions',
        'resources_gained',
    ];

    protected function casts(): array
    {
        return [
            'completions' => 'integer',
            'resources_gained' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
