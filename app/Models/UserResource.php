<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $gold
 * @property int $wood
 * @property int $stone
 * @property int $food
 * @property int $lifetime_gold
 * @property int $lifetime_wood
 * @property int $lifetime_stone
 * @property int $lifetime_food
 * @property int $manual_collects
 * @property int $prestiges
 * @property CarbonInterface|null $last_produced_at
 * @property CarbonInterface|null $last_collected_at
 * @property-read User|null $user
 */
class UserResource extends Model
{
    /** @use HasFactory<UserResourceFactory> */
    use HasFactory;

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
        'prestiges',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
