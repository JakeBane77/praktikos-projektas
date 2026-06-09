<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $leader_id
 * @property int $member_limit
 * @property bool $is_open
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $leader
 * @property-read Collection<int, AllianceMembership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, AllianceGoal> $goals
 */
class Alliance extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'leader_id',
        'member_limit',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'member_limit' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * @return HasMany<AllianceMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    /**
     * @return HasManyThrough<User, AllianceMembership, $this>
     */
    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            AllianceMembership::class,
            'alliance_id',
            'id',
            'id',
            'user_id',
        );
    }

    /**
     * @return HasMany<AllianceGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(AllianceGoal::class);
    }
}
