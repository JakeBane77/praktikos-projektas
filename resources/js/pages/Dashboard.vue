<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowUp,
    Award,
    Building2,
    CheckCircle2,
    Coins,
    Lock,
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
    type AchievementUnlock,
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
const hideCompletedAchievements = ref(true);
const achievementUnlockQueue = ref<AchievementUnlock[]>([]);
const activeAchievementUnlockIndex = ref(0);
const buildings = computed<Building[]>(() => props.buildings);
const achievements = computed(() => props.achievements);
const achievementBonuses = computed(() => props.achievementBonuses);
const currentAchievementUnlock = computed(
    () => achievementUnlockQueue.value[activeAchievementUnlockIndex.value],
);
const isPageOverlayOpen = computed(
    () => Boolean(currentAchievementUnlock.value) || isBuildingsOpen.value,
);
const achievementUnlockCount = computed(
    () => achievementUnlockQueue.value.length,
);
const achievementUnlockPosition = computed(() =>
    Math.min(
        activeAchievementUnlockIndex.value + 1,
        achievementUnlockCount.value,
    ),
);
const achievementUnlockButtonLabel = computed(() =>
    activeAchievementUnlockIndex.value + 1 < achievementUnlockCount.value
        ? 'Next'
        : 'Done',
);
const visibleAchievements = computed(() =>
    hideCompletedAchievements.value
        ? achievements.value.filter((achievement) => !achievement.isUnlocked)
        : achievements.value,
);
const unlockedAchievementCount = computed(
    () =>
        achievements.value.filter((achievement) => achievement.isUnlocked)
            .length,
);
const MAX_ROAD_BUILD_AMOUNT = 1_000_000;

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

watch(
    () => props.achievementUnlocks,
    (achievementUnlocks) => {
        if (
            achievementUnlocks.length === 0 ||
            achievementUnlockQueue.value.length > 0
        ) {
            return;
        }

        achievementUnlockQueue.value = [...achievementUnlocks];
        activeAchievementUnlockIndex.value = 0;
    },
    { immediate: true },
);

watch(currentAchievementUnlock, (achievementUnlock) => {
    if (achievementUnlock) {
        isBuildingsOpen.value = false;
    }
});

let lockedScrollY = 0;
let isScrollLocked = false;

function lockPageScroll() {
    if (isScrollLocked || typeof window === 'undefined') {
        return;
    }

    lockedScrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${lockedScrollY}px`;
    document.body.style.width = '100%';
    isScrollLocked = true;
}

function unlockPageScroll() {
    if (!isScrollLocked || typeof window === 'undefined') {
        return;
    }

    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, lockedScrollY);
    isScrollLocked = false;
}

watch(isPageOverlayOpen, (isOpen) => {
    if (isOpen) {
        lockPageScroll();

        return;
    }

    unlockPageScroll();
});

onBeforeUnmount(() => {
    unlockPageScroll();
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

function advanceAchievementUnlockPopup() {
    if (activeAchievementUnlockIndex.value + 1 < achievementUnlockCount.value) {
        activeAchievementUnlockIndex.value += 1;

        return;
    }

    const seenAchievementUnlockIds = achievementUnlockQueue.value.map(
        (achievementUnlock) => achievementUnlock.id,
    );

    achievementUnlockQueue.value = [];
    activeAchievementUnlockIndex.value = 0;

    if (seenAchievementUnlockIds.length === 0) {
        return;
    }

    router.post(
        '/dashboard/achievements/unlocks/seen',
        {
            ids: seenAchievementUnlockIds,
        },
        {
            preserveScroll: true,
        },
    );
}

function roadBuildAmount(building: Building): number {
    const amount = Number(roadBuildAmounts.value[building.id] ?? 1);

    if (!Number.isFinite(amount)) {
        return 1;
    }

    return Math.min(MAX_ROAD_BUILD_AMOUNT, Math.max(1, amount));
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
                                Resources earned from daily collects and passive
                                production.
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

            <section
                class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-md bg-[#243627] p-2 text-white">
                            <Award class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Achievements</h2>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Production bonuses unlocked by milestones
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <label
                            class="inline-flex items-center gap-2 text-sm font-semibold text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            <input
                                v-model="hideCompletedAchievements"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[#b7aa91] text-[#243627] focus:ring-[#47663b] dark:border-[#554f42]"
                            />
                            Hide completed
                        </label>
                        <p
                            class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ unlockedAchievementCount }} /
                            {{ achievements.length }} unlocked
                        </p>
                    </div>
                </div>

                <div
                    class="mt-5 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-sm font-semibold">
                                Current bonuses
                            </h3>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Active production bonuses from unlocked
                                achievements
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="achievementBonuses.length > 0"
                        class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="bonus in achievementBonuses"
                            :key="bonus.id"
                            class="rounded-md border border-[#e4dac7] px-3 py-2 dark:border-[#35332c]"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="text-sm font-semibold">
                                    {{ bonus.label }}
                                </p>
                                <p
                                    class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]"
                                >
                                    {{ bonus.bonusLabel }}
                                </p>
                            </div>
                            <p
                                class="mt-1 text-xs font-medium text-[#7a705d] dark:text-[#aaa18f]"
                            >
                                base production
                            </p>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-3 text-sm text-[#5d6356] dark:text-[#c6c0b3]"
                    >
                        No active bonuses yet.
                    </p>
                </div>

                <div
                    v-if="visibleAchievements.length > 0"
                    class="mt-5 grid gap-3 lg:grid-cols-2"
                >
                    <article
                        v-for="achievement in visibleAchievements"
                        :key="achievement.id"
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold">
                                        {{ achievement.name }}
                                    </h3>
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-sm px-2 py-1 text-xs font-semibold',
                                            achievement.isUnlocked
                                                ? 'bg-[#dce9d1] text-[#263f20] dark:bg-[#273820] dark:text-[#bde4a5]'
                                                : 'bg-[#e9e1d3] text-[#4e432f] dark:bg-[#24281d] dark:text-[#d8ccb8]',
                                        ]"
                                    >
                                        <CheckCircle2
                                            v-if="achievement.isUnlocked"
                                            class="h-3.5 w-3.5"
                                        />
                                        <Lock v-else class="h-3.5 w-3.5" />
                                        {{
                                            achievement.isUnlocked
                                                ? 'Unlocked'
                                                : 'Locked'
                                        }}
                                    </span>
                                </div>
                                <p
                                    v-if="achievement.description"
                                    class="mt-2 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    {{ achievement.description }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div
                                class="flex items-center justify-between gap-4 text-xs font-semibold text-[#7a705d] dark:text-[#aaa18f]"
                            >
                                <span>Progress</span>
                                <span>{{ achievement.progressLabel }}</span>
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-[#e9e1d3] dark:bg-[#24281d]"
                            >
                                <div
                                    class="h-full rounded-full bg-[#47663b] dark:bg-[#9dcc84]"
                                    :style="{
                                        width: `${achievement.progressPercent}%`,
                                    }"
                                />
                            </div>
                        </div>

                        <p
                            class="mt-3 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ achievement.rewardLabel }}
                        </p>
                    </article>
                </div>

                <p
                    v-else
                    class="mt-5 rounded-md border border-[#e4dac7] p-4 text-sm text-[#5d6356] dark:border-[#35332c] dark:text-[#c6c0b3]"
                >
                    {{
                        achievements.length > 0
                            ? 'Completed achievements hidden.'
                            : 'No achievements configured yet.'
                    }}
                </p>
            </section>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="currentAchievementUnlock"
            class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 px-4 py-6"
        >
            <section
                class="max-h-[calc(100vh-3rem)] w-full max-w-md overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <div class="flex items-start gap-4">
                    <div class="rounded-md bg-[#243627] p-3 text-white">
                        <Award class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Achievement unlocked
                        </p>
                        <h2 class="mt-1 text-2xl font-bold">
                            {{ currentAchievementUnlock.name }}
                        </h2>
                        <p
                            v-if="currentAchievementUnlock.description"
                            class="mt-3 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            {{ currentAchievementUnlock.description }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-5 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2
                            class="h-5 w-5 text-[#47663b] dark:text-[#9dcc84]"
                        />
                        <p class="text-sm font-semibold">Bonus activated</p>
                    </div>
                    <p
                        class="mt-2 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                    >
                        {{ currentAchievementUnlock.rewardLabel }}
                    </p>
                </div>

                <div
                    class="mt-5 flex items-center justify-between gap-4 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
                >
                    <p
                        class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                    >
                        {{ achievementUnlockPosition }} /
                        {{ achievementUnlockCount }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                        @click="advanceAchievementUnlockPopup"
                    >
                        {{ achievementUnlockButtonLabel }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="isBuildingsOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain bg-black/45 px-4 py-6"
            @click.self="isBuildingsOpen = false"
        >
            <section
                class="max-h-[calc(100vh-3rem)] w-full max-w-3xl overflow-y-auto rounded-lg bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:bg-[#1a1d15] dark:text-[#f3efe4]"
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
                                    v-if="
                                        building.isRoad && !building.isMaxLevel
                                    "
                                    class="flex items-center gap-2 text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    km
                                    <input
                                        v-model.number="
                                            roadBuildAmounts[building.id]
                                        "
                                        type="number"
                                        min="1"
                                        :max="MAX_ROAD_BUILD_AMOUNT"
                                        class="h-10 w-32 rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#12140f] dark:text-[#f3efe4]"
                                        placeholder="1"
                                    />
                                </label>

                                <div
                                    v-if="building.isMaxLevel"
                                    class="inline-flex items-center justify-center rounded-md border border-[#cfc1a8] bg-[#e9e1d3] px-4 py-2.5 text-sm font-semibold text-[#4e432f] dark:border-[#4a4438] dark:bg-[#24281d] dark:text-[#d8ccb8]"
                                >
                                    Max level
                                </div>
                                <button
                                    v-else
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
                            <template v-if="building.isMaxLevel">
                                No further upgrades available.
                            </template>
                            <template v-else>
                                {{ building.isRoad ? 'Next km cost' : 'Cost' }}:
                                {{ building.upgradeCost }}
                            </template>
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </Teleport>
</template>
