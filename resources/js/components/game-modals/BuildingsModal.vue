<script setup lang="ts">
import { ArrowUp, Hammer, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatExactNumber } from '@/lib/game';
import type { Building } from '@/lib/game';

const props = withDefaults(
    defineProps<{
        buildings: Building[];
        upgradingBuildingId: number | null;
        roadBuildAmounts: Record<number, number>;
        maxRoadBuildAmount: number;
        title?: string;
        subtitle?: string;
        description?: string;
        maxWidthClass?: string;
        showFilter?: boolean;
        showOnlyAvailable?: boolean;
        emptyMessage?: string;
        upgradeAvailabilityLabelFor: (building: Building) => string | null;
        roadBuildableAmountFor: (building: Building) => number;
    }>(),
    {
        title: 'Buildings',
        subtitle: 'Manage structures',
        description: '',
        maxWidthClass: 'max-w-5xl',
        showFilter: false,
        showOnlyAvailable: true,
        emptyMessage: 'No building upgrades are currently affordable.',
    },
);

const emit = defineEmits<{
    close: [];
    upgrade: [building: Building];
    'update:showOnlyAvailable': [value: boolean];
    'update-road-amount': [payload: { buildingId: number; amount: number }];
}>();

const modalClass = computed(
    () =>
        `max-h-[calc(100vh-3rem)] w-full ${props.maxWidthClass} overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]`,
);

function roadAmountFor(building: Building): number {
    return props.roadBuildAmounts[building.id] ?? 1;
}

function setRoadAmount(buildingId: number, event: Event): void {
    const target = event.target;
    const amount =
        target instanceof HTMLInputElement ? target.valueAsNumber : 1;

    emit('update-road-amount', { buildingId, amount });
}

function setShowOnlyAvailable(event: Event): void {
    const target = event.target;

    emit(
        'update:showOnlyAvailable',
        target instanceof HTMLInputElement ? target.checked : false,
    );
}

function upgradeButtonLabel(building: Building): string {
    if (props.upgradingBuildingId === building.id) {
        return building.isRoad ? 'Building...' : 'Upgrading...';
    }

    if (!building.canUpgrade) {
        return 'Unavailable';
    }

    if (building.isRoad) {
        return 'Build road';
    }

    return building.level === 0 ? 'Build' : 'Upgrade';
}
</script>

<template>
    <section :class="modalClass">
        <header class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="rounded-md bg-[#243627] p-2 text-white">
                    <Hammer class="h-5 w-5" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        {{ title }}
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">
                        {{ subtitle }}
                    </h2>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close buildings"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <div
            v-if="description || showFilter"
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p
                v-if="description"
                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
            >
                {{ description }}
            </p>
            <label
                v-if="showFilter"
                class="inline-flex items-center gap-2 text-sm font-semibold text-[#5d6356] dark:text-[#c6c0b3]"
            >
                <input
                    :checked="showOnlyAvailable"
                    type="checkbox"
                    class="h-4 w-4 rounded border-[#b7aa91] text-[#243627] focus:ring-[#47663b] dark:border-[#554f42]"
                    @change="setShowOnlyAvailable"
                />
                Show only available
            </label>
        </div>

        <div class="mt-5 grid gap-3">
            <div
                v-if="buildings.length === 0"
                class="rounded-md border border-[#e4dac7] p-4 text-sm text-[#5d6356] dark:border-[#35332c] dark:text-[#c6c0b3]"
            >
                {{ emptyMessage }}
            </div>

            <article
                v-for="building in buildings"
                v-else
                :key="building.id"
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold">
                                {{ building.name }}
                            </h3>
                            <span
                                class="rounded-sm bg-[#e9e1d3] px-2 py-1 text-xs font-semibold text-[#4e432f] dark:bg-[#24281d] dark:text-[#d8ccb8]"
                            >
                                {{ building.levelLabel }}
                            </span>
                        </div>
                        <p
                            v-if="building.description"
                            class="mt-2 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            {{ building.description }}
                        </p>
                        <p
                            v-if="building.production"
                            class="mt-2 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ building.production }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col gap-3 sm:items-end">
                        <label
                            v-if="building.isRoad && !building.isMaxLevel"
                            class="flex items-center gap-2 text-sm font-semibold text-[#696250] dark:text-[#b8c2b0]"
                        >
                            km
                            <input
                                :value="roadAmountFor(building)"
                                type="number"
                                min="1"
                                :max="maxRoadBuildAmount"
                                step="1"
                                class="h-10 w-36 rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 text-sm text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-[#4a4438] dark:bg-[#12140f] dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                                placeholder="1"
                                @input="setRoadAmount(building.id, $event)"
                            />
                        </label>

                        <div
                            v-if="building.isMaxLevel"
                            class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#cfc1a8] bg-[#e9e1d3] px-4 py-2 text-sm font-semibold text-[#4e432f] dark:border-[#4a4438] dark:bg-[#24281d] dark:text-[#d8ccb8]"
                        >
                            Max level
                        </div>
                        <button
                            v-else
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                upgradingBuildingId !== null ||
                                !building.canUpgrade
                            "
                            @click="emit('upgrade', building)"
                        >
                            <ArrowUp class="h-4 w-4" />
                            {{ upgradeButtonLabel(building) }}
                        </button>
                    </div>
                </div>

                <p
                    class="mt-3 text-xs font-medium text-[#7a705d] dark:text-[#aaa18f]"
                >
                    <template v-if="building.isMaxLevel">
                        No further upgrades available.
                    </template>
                    <template v-else>
                        {{ building.isRoad ? 'Next km cost' : 'Cost' }}:
                        {{ building.upgradeCost }}
                    </template>
                </p>
                <p
                    v-if="upgradeAvailabilityLabelFor(building)"
                    class="mt-2 text-xs font-semibold text-[#47663b] dark:text-[#9dcc84]"
                >
                    Next upgrade: {{ upgradeAvailabilityLabelFor(building) }}
                </p>
                <p
                    v-if="building.isRoad && !building.isMaxLevel"
                    class="mt-2 text-xs font-semibold text-[#7b633d] dark:text-[#caa66c]"
                >
                    Can build now:
                    {{ formatExactNumber(roadBuildableAmountFor(building)) }}
                    km
                </p>
            </article>
        </div>
    </section>
</template>
