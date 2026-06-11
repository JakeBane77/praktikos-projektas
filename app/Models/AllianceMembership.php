<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AllianceMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $alliance_id
 * @property int $user_id
 * @property string $role
 * @property int $total_contributed
 * @property CarbonInterface|null $joined_at
 * @property-read Alliance|null $alliance
 * @property-read User|null $user
 */
class AllianceMembership extends Model
{
    /** @use HasFactory<AllianceMembershipFactory> */
    use HasFactory;

    protected $table = 'alliance_user';

    public $timestamps = false;

    protected $fillable = [
        'alliance_id',
        'user_id',
        'role',
        'total_contributed',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'total_contributed' => 'integer',
            'joined_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
