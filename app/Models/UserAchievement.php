<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $achievement_id
 * @property int $progress
 * @property CarbonInterface|null $unlocked_at
 * @property CarbonInterface|null $notification_seen_at
 * @property-read Achievement|null $achievement
 * @property-read User|null $user
 */
class UserAchievement extends Model
{
    protected $fillable = [
        'user_id',
        'achievement_id',
        'progress',
        'unlocked_at',
        'notification_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'notification_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Achievement, $this>
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
