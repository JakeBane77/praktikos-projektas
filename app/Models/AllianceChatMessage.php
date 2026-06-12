<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AllianceChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $alliance_id
 * @property int $user_id
 * @property string $message
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Alliance|null $alliance
 * @property-read User|null $user
 */
class AllianceChatMessage extends Model
{
    /** @use HasFactory<AllianceChatMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'alliance_id',
        'user_id',
        'message',
    ];

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
