<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowUp,
    Building2,
    Coins,
    Mountain,
    PackagePlus,
    Route,
    TreePine,
    Trophy,
    Wheat,
    X,
} from 'lucide-vue-next';
import {
    formatRate,
    getTotalResources,
    type Building,
    type DashboardGameData,
    type ResourceKey,
} from '@/lib/game';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Game',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<DashboardGameData>();

const isBuildingsOpen = ref(false);
const isCollecting = ref(false);
const upgradingBuildingId = ref<number | null>(null);
const roadBuildAmounts = ref<Record<number, number>>({});
const buildings = computed<Building[]>(() => props.buildings);

const resourceIcons = {
    gold: Coins,
    wood: TreePine,
    stone: Mountain,
    food: Wheat,
};

const resourceStyles = {
    gold: 'bg-[#f0d79a] text-[#2f2514]',
    wood: 'bg-[#b58b62] text-[#24160d]',
    stone: 'bg-[#aeb4b9] text-[#182027]',
    food: 'bg-[#9abf83] text-[#152412]',
};

const resourceLabels = {
    gold: 'Gold',
    wood: 'Wood',
    stone: 'Stone',
    food: 'Food',
};

const resourceCards = computed(() => [
    ...(['gold', 'wood', 'stone', 'food'] as ResourceKey[]).map((key) => ({
        key,
        label: resourceLabels[key],
        amount: props.resources[key],
        rate: formatRate(props.resourceRates[key]),
        icon: resourceIcons[key],
        class: resourceStyles[key],
    })),
]);

const lifetimeResourceCards = computed(() => [
    ...(['gold', 'wood', 'stone', 'food'] as ResourceKey[]).map((key) => ({
        key,
        label: resourceLabels[key],
        amount: props.lifetimeResources[key],
        icon: resourceIcons[key],
    })),
]);

const totalResources = computed(() => getTotalResources(props.resources));
const lifetimeTotalResources = computed(() =>
    getTotalResources(props.lifetimeResources),
);
const roadRankLabel = computed(() =>
    props.roadStats.rank === null ? 'Unranked' : `#${props.roadStats.rank}`,
);
const collectDisabled = computed(() => isCollecting.value || !props.canCollect);
const collectButtonLabel = computed(() => {
    if (isCollecting.value) {
        return 'Collecting...';
    }

    return props.canCollect ? 'Collect' : 'Collected today';
});

function collectResources() {
    router.post(
        '/dashboard/collect',
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isCollecting.value = true;
            },
            onFinish: () => {
                isCollecting.value = false;
            },
        },
    );
}

function roadBuildAmount(building: Building): number {
    const amount = Number(roadBuildAmounts.value[building.id] ?? 1);

    return Number.isFinite(amount) ? Math.max(1, amount) : 1;
}

function upgradeBuilding(building: Building) {
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
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="min-h-full bg-[#f6f3ec] text-[#1f241c] dark:bg-[#12140f] dark:text-[#f3efe4]"
    >
        <div
            class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <section
                class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Settlement dashboard
                    </p>
                    <h1
                        class="mt-2 text-3xl leading-tight font-bold sm:text-4xl"
                    >
                        Your kingdom is producing.
                    </h1>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                    >
                        Collect passive income, inspect your buildings, and
                        decide what to upgrade next.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="collectDisabled"
                        @click="collectResources"
                    >
                        <PackagePlus class="h-4 w-4" />
                        {{ collectButtonLabel }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-[#b7aa91] px-4 py-2.5 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                        @click="isBuildingsOpen = true"
                    >
                        <Building2 class="h-4 w-4" />
                        Buildings
                    </button>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="resource in resourceCards"
                    :key="resource.key"
                    :class="[
                        'rounded-lg p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                        resource.class,
                    ]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold">
                                {{ resource.label }}
                            </p>
                            <p class="mt-3 text-3xl font-bold">
                                {{ resource.amount.toLocaleString() }}
                            </p>
                        </div>
                        <component
                            :is="resource.icon"
                            class="h-6 w-6 shrink-0"
                        />
                    </div>
                    <p class="mt-4 text-sm font-semibold opacity-80">
                        {{ resource.rate }}
                    </p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-[1fr_0.75fr]">
                <div
                    class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Lifetime collected
                            </h2>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Last collected: {{ props.lastCollectedAt }}
                            </p>
                            <p
                                class="mt-1 text-xs text-[#7a705d] dark:text-[#aaa18f]"
                            >
                                Resources earned from daily collects and
                                passive production.
                            </p>
                        </div>
                        <p
                            class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ lifetimeTotalResources.toLocaleString() }}
                            lifetime resources
                        </p>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="resource in lifetimeResourceCards"
                            :key="resource.key"
                            class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="resource.icon"
                                        class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                                    />
                                    <p class="font-semibold">
                                        {{ resource.label }}
                                    </p>
                                </div>
                                <p
                                    class="text-right text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                                >
                                    {{ resource.amount.toLocaleString() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-md bg-[#243627] p-2 text-white">
                            <Route class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Road network</h2>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Longest road leaderboard
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div
                            class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Road length
                                </p>
                                <Route
                                    class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                                />
                            </div>
                            <p class="mt-3 text-3xl font-bold">
                                {{ props.roadStats.length.toLocaleString() }}
                                km
                            </p>
                        </div>

                        <div
                            class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Your spot
                                </p>
                                <Trophy
                                    class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                                />
                            </div>
                            <p class="mt-3 text-3xl font-bold">
                                {{ roadRankLabel }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                        @click="isBuildingsOpen = true"
                    >
                        <Route class="h-4 w-4" />
                        Build roads
                    </button>
                </div>
            </section>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="isBuildingsOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 px-4 py-6"
            @click.self="isBuildingsOpen = false"
        >
            <section
                class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Buildings
                        </p>
                        <h2 class="mt-1 text-2xl font-bold">
                            Manage structures
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                        aria-label="Close buildings"
                        @click="isBuildingsOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <div class="mt-5 grid gap-3">
                    <article
                        v-for="building in buildings"
                        :key="building.name"
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold">
                                        {{ building.name }}
                                    </h3>
                                    <span
                                        class="rounded-sm bg-[#e9e1d3] px-2 py-1 text-xs font-semibold text-[#4e432f] dark:bg-[#24281d] dark:text-[#d8ccb8]"
                                    >
                                        {{ building.levelLabel }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    {{ building.description }}
                                </p>
                                <p
                                    class="mt-2 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                                >
                                    {{ building.production }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-2 sm:items-end">
                                <label
                                    v-if="building.isRoad"
                                    class="flex items-center gap-2 text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    km
                                    <input
                                        v-model.number="
                                            roadBuildAmounts[building.id]
                                        "
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="h-10 w-24 rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#12140f] dark:text-[#f3efe4]"
                                        placeholder="1"
                                    />
                                </label>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="
                                        !building.canUpgrade ||
                                        upgradingBuildingId === building.id
                                    "
                                    @click="upgradeBuilding(building)"
                                >
                                    <ArrowUp class="h-4 w-4" />
                                    {{
                                        upgradingBuildingId === building.id
                                            ? 'Building...'
                                            : building.isRoad
                                              ? 'Build road'
                                              : building.level === 0
                                                ? 'Build'
                                                : 'Upgrade'
                                    }}
                                </button>
                            </div>
                        </div>

                        <p
                            class="mt-3 text-xs font-medium text-[#7a705d] dark:text-[#aaa18f]"
                        >
                            {{ building.isRoad ? 'Next km cost' : 'Cost' }}:
                            {{ building.upgradeCost }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </Teleport>
</template>
