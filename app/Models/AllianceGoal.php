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
 * @property string|null $resource_type
 * @property int $target_amount
 * @property int $current_amount
 * @property int $production_bonus_percent
 * @property int $bonus_duration_hours
 * @property array<int, float|int> $stage_percentages
 * @property array<int, int>|null $stage_donor_requirements
 * @property CarbonInterface $week_starts_at
 * @property CarbonInterface $week_ends_at
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
        'stage_percentages',
        'stage_donor_requirements',
        'week_starts_at',
        'week_ends_at',
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
            'stage_percentages' => 'array',
            'stage_donor_requirements' => 'array',
            'week_starts_at' => 'datetime',
            'week_ends_at' => 'datetime',
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
