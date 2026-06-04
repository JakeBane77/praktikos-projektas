<?php

namespace App\Models;

use Database\Factories\MinigameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $resource
 * @property int $completions
 * @property int $resources_gained
 * @property-read User|null $user
 */
class Minigame extends Model
{
    /** @use HasFactory<MinigameFactory> */
    use HasFactory;

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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
