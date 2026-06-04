<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ResourceCollectionFactory;
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
 * @property string $source
 * @property CarbonInterface|null $collected_at
 * @property-read User|null $user
 */
class ResourceCollection extends Model
{
    /** @use HasFactory<ResourceCollectionFactory> */
    use HasFactory;

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
