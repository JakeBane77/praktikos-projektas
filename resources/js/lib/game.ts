export type ResourceKey = 'gold' | 'wood' | 'stone' | 'food';

export type Resources = Record<ResourceKey, number>;

export type ResourceRates = Record<ResourceKey, number>;

export type Building = {
    id: number;
    name: string;
    level: number;
    levelLabel: string;
    description: string;
    production: string;
    upgradeCost: string;
    isRoad: boolean;
    isMaxLevel: boolean;
    canUpgrade: boolean;
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

export type Minigame = {
    resource: ResourceKey;
    label: string;
    currentProduction: number;
    reward: number;
    rewardLabel: string;
    completions: number;
    resourcesGained: number;
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

export type DashboardGameData = {
    serverTime: {
        iso: string;
        timezone: string;
    };
    weather: {
        latitude: number;
        longitude: number;
        weatherCode: number | null;
        apiTime: string | null;
        updatedAt: string | null;
    };
    resources: Resources;
    lifetimeResources: Resources;
    resourceRates: ResourceRates;
    lastCollectedAt: string;
    canCollect: boolean;
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

export function formatRate(rate: number): string {
    return `+${rate}/hour`;
}
