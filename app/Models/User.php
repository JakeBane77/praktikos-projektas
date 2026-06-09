<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property-read UserResource|null $resources
 * @property-read Collection<int, UserBuilding> $buildings
 * @property-read Collection<int, UserAchievement> $achievements
 * @property-read Collection<int, Minigame> $minigames
 * @property-read Alliance|null $ledAlliance
 * @property-read Alliance|null $alliance
 * @property-read AllianceMembership|null $allianceMembership
 * @property-read Collection<int, AllianceCreationLog> $allianceCreationLogs
 * @property-read Collection<int, AllianceGoalContribution> $allianceGoalContributions
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<UserResource, $this>
     */
    public function resources(): HasOne
    {
        return $this->hasOne(UserResource::class);
    }

    /**
     * @return HasMany<UserBuilding, $this>
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(UserBuilding::class);
    }

    /**
     * @return HasMany<UserAchievement, $this>
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * @return HasMany<Minigame, $this>
     */
    public function minigames(): HasMany
    {
        return $this->hasMany(Minigame::class);
    }

    /**
     * @return HasOne<Alliance, $this>
     */
    public function ledAlliance(): HasOne
    {
        return $this->hasOne(Alliance::class, 'leader_id');
    }

    /**
     * @return HasOne<AllianceMembership, $this>
     */
    public function allianceMembership(): HasOne
    {
        return $this->hasOne(AllianceMembership::class);
    }

    /**
     * @return HasOneThrough<Alliance, AllianceMembership, $this>
     */
    public function alliance(): HasOneThrough
    {
        return $this->hasOneThrough(
            Alliance::class,
            AllianceMembership::class,
            'user_id',
            'id',
            'id',
            'alliance_id',
        );
    }

    /**
     * @return HasMany<AllianceCreationLog, $this>
     */
    public function allianceCreationLogs(): HasMany
    {
        return $this->hasMany(AllianceCreationLog::class);
    }

    /**
     * @return HasMany<AllianceGoalContribution, $this>
     */
    public function allianceGoalContributions(): HasMany
    {
        return $this->hasMany(AllianceGoalContribution::class);
    }
}
