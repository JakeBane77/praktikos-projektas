import type { WeatherSnapshot } from '@/lib/weather';

export type ResourceKey = 'gold' | 'wood' | 'stone' | 'food';

export type Resources = Record<ResourceKey, number>;

export type ResourceRates = Record<ResourceKey, number>;

export type ResourceCosts = Partial<Record<ResourceKey, number>>;

export type Building = {
    id: number;
    name: string;
    level: number;
    levelLabel: string;
    description: string;
    production: string;
    upgradeCost: string;
    upgradeCosts: ResourceCosts;
    baseCosts: ResourceCosts;
    upgradeCostMultiplier: number;
    maxLevel: number | null;
    isRoad: boolean;
    isMaxLevel: boolean;
    canUpgrade: boolean;
};

export type UpgradeAvailability = {
    hours: number | null;
    label: string;
};

export type Achievement = {
    id: number;
    name: string;
    description: string | null;
    progress: number;
    target: number;
    progressLabel: string;
    progressPercent: number;
    isUnlocked: boolean;
    unlockedAt: string | null;
    rewardLabel: string;
};

export type AchievementBonus = {
    id: string;
    label: string;
    bonusPercent: number;
    bonusLabel: string;
};

export type AchievementUnlock = {
    id: number;
    name: string;
    description: string | null;
    unlockedAt: string | null;
    rewardLabel: string;
};

export type OfflineProgress = {
    elapsedHours: number;
    resources: Resources;
    total: number;
};

export type Minigame = {
    resource: ResourceKey;
    label: string;
    currentProduction: number;
    reward: number;
    rewardLabel: string;
    completions: number;
    resourcesGained: number;
    stamina: {
        current: number;
        max: number;
        used: number;
        isAvailable: boolean;
        availableInSeconds: number;
        label: string;
    };
};

export type LeaderboardEntry = {
    rank: number;
    userId: number;
    userName: string;
    value: number;
    valueLabel: string;
    isCurrentUser: boolean;
};

export type Leaderboard = {
    key: string;
    label: string;
    metricLabel: string;
    currentRank: number;
    currentValue: number;
    currentValueLabel: string;
    entries: LeaderboardEntry[];
};

export type AllianceMember = {
    id: number;
    userId: number;
    name: string;
    role: 'leader' | 'officer' | 'member';
    totalContributed: number;
    joinedAt: string | null;
    isCurrentUser: boolean;
    canKick: boolean;
    canPromote: boolean;
    canDemote: boolean;
    canTransferLeadership: boolean;
};

export type AllianceApplication = {
    id: number;
    userId: number;
    userName: string;
    appliedAt: string | null;
};

export type AllianceContribution = {
    id: number;
    userId: number;
    userName: string;
    goalName: string;
    resourceType: ResourceKey;
    resourceLabel: string;
    amount: number;
    amountLabel: string;
    contributedAt: string | null;
};

export type AllianceChatMessage = {
    id: number;
    userId: number;
    userName: string;
    message: string;
    sentAt: string | null;
    isCurrentUser: boolean;
};

export type AllianceGoalStage = {
    percentage: number;
    percentageLabel: string;
    amount: number;
    amountLabel: string;
    requiredDonors: number;
    requiredDonorsLabel: string;
    hasAmount: boolean;
    hasDonors: boolean;
    isReached: boolean;
};

export type AllianceGoal = {
    id: number;
    name: string;
    resourceType: ResourceKey | null;
    resourceLabel: string;
    targetAmount: number;
    targetAmountLabel: string;
    currentAmount: number;
    currentAmountLabel: string;
    progressPercent: number;
    uniqueDonorCount: number;
    uniqueDonorCountLabel: string;
    contributionCap: number;
    contributionCapLabel: string;
    contributionCapPercent: number;
    stageCount: number;
    reachedStageCount: number;
    bonusPerStagePercent: number;
    potentialBonusPercent: number;
    earnedNextWeekBonusPercent: number;
    weekStartsAt: string;
    weekEndsAt: string;
    status: 'active' | 'completed' | 'expired';
    stages: AllianceGoalStage[];
};

export type AllianceGoalBonus = {
    bonusPercent: number;
    stageCount: number;
    sourceGoalName: string | null;
    label: string;
};

export type AllianceSummary = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    leaderName: string;
    memberLimit: number;
    memberCount: number;
    isOpen: boolean;
    canJoin: boolean;
    canApply: boolean;
    hasPendingApplication: boolean;
    members: AllianceMember[];
};

export type CurrentAlliance = AllianceSummary & {
    currentUserRole: 'leader' | 'officer' | 'member';
    canUpdate: boolean;
    canUpdateVisibility: boolean;
    canLeave: boolean;
    canDisband: boolean;
    members: AllianceMember[];
    applications: AllianceApplication[];
    contributions: AllianceContribution[];
    chatMessages: AllianceChatMessage[];
    goal: AllianceGoal;
    activeGoalBonus: AllianceGoalBonus;
};

export type AllianceState = {
    current: CurrentAlliance | null;
    available: AllianceSummary[];
    canCreate: boolean;
    creationCooldownEndsAt: string | null;
};

export type DashboardGameData = {
    serverTime: {
        iso: string;
        timezone: string;
    };
    weather: WeatherSnapshot;
    resources: Resources;
    lifetimeResources: Resources;
    resourceRates: ResourceRates;
    lastCollectedAt: string;
    canCollect: boolean;
    offlineProgress: OfflineProgress | null;
    roadStats: {
        length: number;
    };
    prestigeStats: {
        count: number;
        rank: number;
        canPrestige: boolean;
        requirement: number;
    };
    achievementBonuses: AchievementBonus[];
    achievementUnlocks: AchievementUnlock[];
    leaderboards: {
        defaultKey: string;
        boards: Leaderboard[];
    };
    alliances: AllianceState;
    minigames: Minigame[];
    buildings: Building[];
    achievements: Achievement[];
};

export function getTotalResources(resources: Resources): number {
    return Object.values(resources).reduce(
        (total, amount) => total + amount,
        0,
    );
}

const exactNumberFormatter = new Intl.NumberFormat('en');
const compactNumberFormatter = new Intl.NumberFormat('en', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

export function formatExactNumber(value: number): string {
    return exactNumberFormatter.format(value);
}

export function formatGameNumber(value: number): string {
    if (Math.abs(value) < 100_000) {
        return formatExactNumber(value);
    }

    const absoluteValue = Math.abs(value);
    const compactScale =
        absoluteValue >= 1_000_000_000_000
            ? 1_000_000_000_000
            : absoluteValue >= 1_000_000_000
              ? 1_000_000_000
              : absoluteValue >= 1_000_000
                ? 1_000_000
                : 1_000;
    const truncatedValue = Math.trunc((value / compactScale) * 10) / 10;

    return compactNumberFormatter.format(truncatedValue * compactScale);
}

export function formatRate(rate: number): string {
    return `+${formatGameNumber(rate)}/hour`;
}

export function formatHoursDuration(totalHours: number): string {
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;
    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
    }

    if (hours > 0 || parts.length === 0) {
        parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
    }

    return parts.join(', ');
}

export function formatSecondsDuration(totalSeconds: number): string {
    const seconds = Math.max(0, Math.ceil(totalSeconds));
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    if (minutes <= 0) {
        return `${remainingSeconds} ${remainingSeconds === 1 ? 'second' : 'seconds'}`;
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours <= 0) {
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}${remainingSeconds > 0 ? ` ${remainingSeconds} seconds` : ''}`;
    }

    return `${hours} ${hours === 1 ? 'hour' : 'hours'}${remainingMinutes > 0 ? ` ${remainingMinutes} minutes` : ''}`;
}

export function minigameStaminaHoverLabel(minigame: Minigame): string {
    if (!minigame.stamina.isAvailable) {
        if (minigame.stamina.availableInSeconds <= 0) {
            return 'Stamina refreshing...';
        }

        return `Stamina empty. Refreshes in ${formatSecondsDuration(
            minigame.stamina.availableInSeconds,
        )}`;
    }

    if (minigame.stamina.current >= minigame.stamina.max) {
        return `Stamina full ${minigame.stamina.label}`;
    }

    return `Stamina ${minigame.stamina.label}. Next refresh in ${formatSecondsDuration(
        minigame.stamina.availableInSeconds,
    )}`;
}

export function minigameWithLiveStamina(
    minigame: Minigame,
    elapsedSeconds: number,
): Minigame {
    const availableInSeconds = Math.max(
        0,
        minigame.stamina.availableInSeconds -
            Math.max(0, Math.floor(elapsedSeconds)),
    );

    if (availableInSeconds === minigame.stamina.availableInSeconds) {
        return minigame;
    }

    return {
        ...minigame,
        stamina: {
            ...minigame.stamina,
            availableInSeconds,
        },
    };
}

export function upgradeAvailabilityFor(
    building: Building,
    resources: Resources,
    resourceRates: ResourceRates,
): UpgradeAvailability | null {
    if (building.level <= 0 || building.isMaxLevel) {
        return null;
    }

    if (building.canUpgrade) {
        return {
            hours: 0,
            label: 'Available now',
        };
    }

    let requiredHours = 0;

    for (const [resource, rawCost] of Object.entries(building.upgradeCosts)) {
        const key = resource as ResourceKey;
        const cost = rawCost ?? 0;
        const missing = cost - resources[key];

        if (missing <= 0) {
            continue;
        }

        const rate = resourceRates[key];

        if (rate <= 0) {
            return {
                hours: null,
                label: `Waiting for ${resource}`,
            };
        }

        requiredHours = Math.max(requiredHours, Math.ceil(missing / rate));
    }

    return {
        hours: requiredHours,
        label:
            requiredHours <= 0
                ? 'Available now'
                : `Available in ${formatHoursDuration(requiredHours)}`,
    };
}

export function affordableRoadAmount(
    building: Building,
    resources: Resources,
    maxRequestAmount: number,
): number {
    if (!building.isRoad || building.isMaxLevel) {
        return 0;
    }

    const remainingLevels =
        building.maxLevel === null
            ? maxRequestAmount
            : Math.max(0, building.maxLevel - building.level);
    let low = 0;
    let high = Math.min(maxRequestAmount, remainingLevels);

    while (low < high) {
        const middle = Math.ceil((low + high) / 2);

        if (
            canAffordCosts(resources, upgradeCostsForAmount(building, middle))
        ) {
            low = middle;
        } else {
            high = middle - 1;
        }
    }

    return low;
}

function canAffordCosts(resources: Resources, costs: ResourceCosts): boolean {
    return Object.entries(costs).every(([resource, cost]) => {
        const key = resource as ResourceKey;

        return resources[key] >= (cost ?? 0);
    });
}

function upgradeCostsForAmount(
    building: Building,
    amount: number,
): ResourceCosts {
    const costs: ResourceCosts = {};

    for (const [resource, rawBaseCost] of Object.entries(building.baseCosts)) {
        const key = resource as ResourceKey;
        const baseCost = rawBaseCost ?? 0;

        costs[key] = upgradeCostForResource(
            baseCost,
            building.upgradeCostMultiplier,
            building.level,
            amount,
        );
    }

    return costs;
}

function upgradeCostForResource(
    baseCost: number,
    multiplier: number,
    level: number,
    amount: number,
): number {
    if (amount <= 0) {
        return 0;
    }

    if (amount === 1) {
        return Math.ceil(baseCost * multiplier ** level);
    }

    if (multiplier === 1) {
        return baseCost * amount;
    }

    const total =
        baseCost *
        multiplier ** level *
        ((multiplier ** amount - 1) / (multiplier - 1));

    if (!Number.isFinite(total) || total > Number.MAX_SAFE_INTEGER) {
        return Number.MAX_SAFE_INTEGER;
    }

    return Math.ceil(total);
}
