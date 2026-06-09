<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $alliance_goal_id
 * @property int $user_id
 * @property string $resource_type
 * @property int $amount
 * @property CarbonInterface|null $created_at
 * @property-read AllianceGoal|null $goal
 * @property-read User|null $user
 */
class AllianceGoalContribution extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'alliance_goal_id',
        'user_id',
        'resource_type',
        'amount',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AllianceGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(AllianceGoal::class, 'alliance_goal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
