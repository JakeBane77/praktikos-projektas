import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    affordableRoadAmount,
    formatHoursDuration,
    upgradeAvailabilityFor,
} from '@/lib/game';
import type { Building, ResourceRates, Resources } from '@/lib/game';

export function useGameBuildings(options: {
    resources: () => Resources;
    resourceRates: () => ResourceRates;
    predictionBaseMilliseconds: () => number;
    currentMilliseconds: () => number;
    maxRoadBuildAmount: number;
    floorRoadAmount?: boolean;
    canSubmitUpgrade?: (building: Building, upgradingBuildingId: number | null) => boolean;
}) {
    const upgradingBuildingId = ref<number | null>(null);
    const roadBuildAmounts = ref<Record<number, number>>({});

    function formatUpgradeAvailableAt(milliseconds: number): string {
        return new Intl.DateTimeFormat('en-GB', {
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(milliseconds));
    }

    function upgradeAvailabilityLabelFor(building: Building): string | null {
        const availability = upgradeAvailabilityFor(
            building,
            options.resources(),
            options.resourceRates(),
        );

        if (availability === null) {
            return null;
        }

        if (availability.hours === null || availability.hours <= 0) {
            return availability.label;
        }

        const targetMilliseconds =
            options.predictionBaseMilliseconds() +
            availability.hours * 60 * 60_000;
        const remainingHours = Math.ceil(
            Math.max(0, targetMilliseconds - options.currentMilliseconds()) /
                (60 * 60_000),
        );

        if (remainingHours <= 0) {
            return 'Available now';
        }

        return `Available in ${formatHoursDuration(remainingHours)} (${formatUpgradeAvailableAt(targetMilliseconds)})`;
    }

    function roadBuildableAmountFor(building: Building): number {
        return affordableRoadAmount(
            building,
            options.resources(),
            options.maxRoadBuildAmount,
        );
    }

    function updateRoadBuildAmount(payload: {
        buildingId: number;
        amount: number;
    }): void {
        roadBuildAmounts.value[payload.buildingId] = payload.amount;
    }

    function roadBuildAmount(building: Building): number {
        const amount = Number(roadBuildAmounts.value[building.id] ?? 1);

        if (!Number.isFinite(amount)) {
            return 1;
        }

        const normalizedAmount =
            options.floorRoadAmount === false ? amount : Math.floor(amount);

        return Math.min(
            options.maxRoadBuildAmount,
            Math.max(1, normalizedAmount),
        );
    }

    function upgradeBuilding(building: Building): void {
        if (
            options.canSubmitUpgrade &&
            !options.canSubmitUpgrade(building, upgradingBuildingId.value)
        ) {
            return;
        }

        router.post(
            `/dashboard/buildings/${building.id}/upgrade`,
            {
                amount: building.isRoad ? roadBuildAmount(building) : 1,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    upgradingBuildingId.value = building.id;
                },
                onFinish: () => {
                    upgradingBuildingId.value = null;
                },
            },
        );
    }

    return {
        upgradingBuildingId,
        roadBuildAmounts,
        upgradeAvailabilityLabelFor,
        roadBuildableAmountFor,
        updateRoadBuildAmount,
        roadBuildAmount,
        upgradeBuilding,
    };
}
