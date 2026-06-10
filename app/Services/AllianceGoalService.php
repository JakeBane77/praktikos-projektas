<?php

namespace App\Services;

use App\Models\Alliance;
use App\Models\AllianceGoal;
use App\Models\AllianceMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AllianceGoalService
{
    /** @var list<float|int> */
    public const DEFAULT_STAGE_PERCENTAGES = [0.01, 1, 10, 30, 60, 100];

    public const DEFAULT_TARGET_AMOUNT = 10_000_000;

    public const DEFAULT_PRODUCTION_BONUS_PERCENT = 2;

    public const BONUS_DURATION_HOURS = 168;

    public function ensureCurrentGoalFor(Alliance $alliance): AllianceGoal
    {
        $weekStartsAt = $this->currentWeekStartsAt();
        $weekEndsAt = $this->weekEndsAt($weekStartsAt);

        return DB::transaction(function () use ($alliance, $weekEndsAt, $weekStartsAt): AllianceGoal {
            AllianceGoal::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', 'active')
                ->where('week_ends_at', '<', now())
                ->update(['status' => 'expired']);

            $goal = AllianceGoal::query()
                ->where('alliance_id', $alliance->id)
                ->where('week_starts_at', $weekStartsAt)
                ->first();

            if ($goal instanceof AllianceGoal) {
                return $goal;
            }

            return AllianceGoal::create([
                'alliance_id' => $alliance->id,
                'name' => 'Weekly stockpile',
                'resource_type' => null,
                'target_amount' => self::DEFAULT_TARGET_AMOUNT,
                'current_amount' => 0,
                'production_bonus_percent' => self::DEFAULT_PRODUCTION_BONUS_PERCENT,
                'bonus_duration_hours' => self::BONUS_DURATION_HOURS,
                'stage_percentages' => self::DEFAULT_STAGE_PERCENTAGES,
                'week_starts_at' => $weekStartsAt,
                'week_ends_at' => $weekEndsAt,
                'status' => 'active',
            ]);
        });
    }

    public function activeBonusPercentFor(User $user): int
    {
        $alliance = $this->currentAllianceFor($user);

        if (! $alliance instanceof Alliance) {
            return 0;
        }

        $previousGoal = $this->previousWeekGoalFor($alliance);

        if (! $previousGoal instanceof AllianceGoal) {
            return 0;
        }

        return $this->reachedStageCountFor($previousGoal) * (int) $previousGoal->production_bonus_percent;
    }

    public function currentGoalCardFor(Alliance $alliance): array
    {
        $goal = $this->ensureCurrentGoalFor($alliance);

        return $this->goalCardFor($goal);
    }

    public function previousBonusCardFor(Alliance $alliance): array
    {
        $previousGoal = $this->previousWeekGoalFor($alliance);

        if (! $previousGoal instanceof AllianceGoal) {
            return [
                'bonusPercent' => 0,
                'stageCount' => 0,
                'sourceGoalName' => null,
                'label' => 'No alliance production bonus active',
            ];
        }

        $stageCount = $this->reachedStageCountFor($previousGoal);
        $bonusPercent = $stageCount * (int) $previousGoal->production_bonus_percent;

        return [
            'bonusPercent' => $bonusPercent,
            'stageCount' => $stageCount,
            'sourceGoalName' => $previousGoal->name,
            'label' => $bonusPercent > 0
                ? '+'.number_format($bonusPercent).'% production from last week'
                : 'No alliance production bonus active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function goalCardFor(AllianceGoal $goal): array
    {
        $target = max(1, (int) $goal->target_amount);
        $current = (int) $goal->current_amount;
        $stageCards = collect($this->stagePercentagesFor($goal))
            ->map(function (float $percentage) use ($current, $goal): array {
                $amount = $this->stageAmountFor($goal, $percentage);

                return [
                    'percentage' => $percentage,
                    'percentageLabel' => $this->formatStagePercentage($percentage),
                    'amount' => $amount,
                    'amountLabel' => number_format($amount),
                    'isReached' => $current >= $amount,
                ];
            })
            ->values()
            ->all();

        $reachedStageCount = count(array_filter($stageCards, fn (array $stage): bool => (bool) $stage['isReached']));

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'resourceType' => $goal->resource_type,
            'resourceLabel' => $goal->resource_type === null ? 'Any resource' : ucfirst($goal->resource_type),
            'targetAmount' => $target,
            'targetAmountLabel' => number_format($target),
            'currentAmount' => $current,
            'currentAmountLabel' => number_format($current),
            'progressPercent' => min(100, (int) floor(($current / $target) * 100)),
            'stageCount' => count($stageCards),
            'reachedStageCount' => $reachedStageCount,
            'bonusPerStagePercent' => (int) $goal->production_bonus_percent,
            'potentialBonusPercent' => count($stageCards) * (int) $goal->production_bonus_percent,
            'earnedNextWeekBonusPercent' => $reachedStageCount * (int) $goal->production_bonus_percent,
            'weekStartsAt' => $goal->week_starts_at->format('Y-m-d H:i'),
            'weekEndsAt' => $goal->week_ends_at->format('Y-m-d H:i'),
            'status' => $goal->status,
            'stages' => $stageCards,
        ];
    }

    public function refreshGoalStatus(AllianceGoal $goal): AllianceGoal
    {
        if ($goal->current_amount >= $goal->target_amount && $goal->status === 'active') {
            $goal->status = 'completed';
            $goal->completed_at = now();
            $goal->save();
        }

        return $goal;
    }

    public function currentAllianceFor(User $user): ?Alliance
    {
        $membership = $user->allianceMembership()
            ->with('alliance')
            ->first();

        if ($membership instanceof AllianceMembership && $membership->alliance instanceof Alliance) {
            return $membership->alliance;
        }

        return $user->ledAlliance()->first();
    }

    private function previousWeekGoalFor(Alliance $alliance): ?AllianceGoal
    {
        $previousWeekStartsAt = $this->currentWeekStartsAt()->subWeek();

        return AllianceGoal::query()
            ->where('alliance_id', $alliance->id)
            ->where('week_starts_at', $previousWeekStartsAt)
            ->first();
    }

    private function reachedStageCountFor(AllianceGoal $goal): int
    {
        $current = (int) $goal->current_amount;

        return collect($this->stagePercentagesFor($goal))
            ->filter(fn (float $percentage): bool => $current >= $this->stageAmountFor($goal, $percentage))
            ->count();
    }

    /**
     * @return list<float>
     */
    private function stagePercentagesFor(AllianceGoal $goal): array
    {
        $percentages = $goal->stage_percentages;

        if ($percentages === []) {
            return self::DEFAULT_STAGE_PERCENTAGES;
        }

        return collect($percentages)
            ->map(fn (mixed $percentage): float => (float) $percentage)
            ->filter(fn (float $percentage): bool => $percentage > 0 && $percentage <= 100)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function stageAmountFor(AllianceGoal $goal, float $percentage): int
    {
        return max(1, (int) ceil(((int) $goal->target_amount * $percentage) / 100));
    }

    private function formatStagePercentage(float $percentage): string
    {
        $formatted = rtrim(rtrim(number_format($percentage, 2), '0'), '.');

        return $formatted.'%';
    }

    private function currentWeekStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->startOfWeek()
            ->startOfDay();
    }

    private function weekEndsAt(CarbonImmutable $weekStartsAt): CarbonImmutable
    {
        return $weekStartsAt->addWeek()->subSecond();
    }
}
