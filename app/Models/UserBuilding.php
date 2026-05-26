<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBuilding extends Model
{
    protected $fillable = [
        'user_id',
        'building_type_id',
        'level',
        'built_at',
    ];

    protected function casts(): array
    {
        return [
            'built_at' => 'datetime',
        ];
    }

    public function buildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
