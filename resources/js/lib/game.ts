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

export type DashboardGameData = {
    resources: Resources;
    lifetimeResources: Resources;
    resourceRates: ResourceRates;
    lastCollectedAt: string;
    canCollect: boolean;
    roadStats: {
        length: number;
        rank: number | null;
    };
    buildings: Building[];
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
