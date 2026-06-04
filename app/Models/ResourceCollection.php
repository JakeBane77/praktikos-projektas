<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $gold
 * @property int $wood
 * @property int $stone
 * @property int $food
 * @property string $source
 * @property \Carbon\CarbonInterface|null $collected_at
 * @property-read User|null $user
 */
class ResourceCollection extends Model
{
    protected $fillable = [
        'user_id',
        'gold',
        'wood',
        'stone',
        'food',
        'source',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
