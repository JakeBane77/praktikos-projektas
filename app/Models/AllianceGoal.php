<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $alliance_id
 * @property string $name
 * @property string $resource_type
 * @property int $target_amount
 * @property int $current_amount
 * @property int $production_bonus_percent
 * @property int $bonus_duration_hours
 * @property string $status
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Alliance|null $alliance
 * @property-read Collection<int, AllianceGoalContribution> $contributions
 */
class AllianceGoal extends Model
{
    protected $fillable = [
        'alliance_id',
        'name',
        'resource_type',
        'target_amount',
        'current_amount',
        'production_bonus_percent',
        'bonus_duration_hours',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'current_amount' => 'integer',
            'production_bonus_percent' => 'integer',
            'bonus_duration_hours' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Alliance, $this>
     */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /**
     * @return HasMany<AllianceGoalContribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(AllianceGoalContribution::class);
    }
}
