<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import {
    ArrowUp,
    Award,
    Building2,
    CheckCircle2,
    Coins,
    Gamepad2,
    LocateFixed,
    Lock,
    Mountain,
    PackagePlus,
    RotateCcw,
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
    type Leaderboard,
    type Minigame,
    type ResourceKey,
} from '@/lib/game';
import {
    weatherConditionLabel as getWeatherConditionLabel,
    weatherIconFor,
} from '@/lib/weather';
import { dashboard } from '@/routes';
import FoodMinigame from '@/components/minigames/FoodMinigame.vue';
import GoldMinigame from '@/components/minigames/GoldMinigame.vue';
import StoneMinigame from '@/components/minigames/StoneMinigame.vue';
import WoodMinigame from '@/components/minigames/WoodMinigame.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard mode',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<DashboardGameData>();

const isBuildingsOpen = ref(false);
const isCollecting = ref(false);
const isPrestiging = ref(false);
const isPrestigeConfirmOpen = ref(false);
const activeGameModal = ref<'leaderboard' | 'minigame' | null>(null);
const upgradingBuildingId = ref<number | null>(null);
const activeMinigameResource = ref<ResourceKey | null>(null);
const selectedMinigameResource = ref<ResourceKey | null>(null);
const hasWonMinigame = ref(false);
const selectedLeaderboardKey = ref(props.leaderboards.defaultKey);
const roadBuildAmounts = ref<Record<number, number>>({});
const hideCompletedAchievements = ref(true);
const achievementUnlockQueue = ref<AchievementUnlock[]>([]);
const activeAchievementUnlockIndex = ref(0);
const serverTimeMilliseconds = ref(Date.now());
const isUpdatingWeatherLocation = ref(false);
const weatherLocationStatus = ref<string | null>(null);
const buildings = computed<Building[]>(() => props.buildings);
const minigames = computed<Minigame[]>(() => props.minigames);
const leaderboards = computed<Leaderboard[]>(() => props.leaderboards.boards);
const selectedMinigame = computed<Minigame | null>(() =>
    selectedMinigameResource.value
        ? (minigames.value.find(
              (minigame) =>
                  minigame.resource === selectedMinigameResource.value,
          ) ?? null)
        : null,
);
const achievements = computed(() => props.achievements);
const achievementBonuses = computed(() => props.achievementBonuses);
const currentAchievementUnlock = computed(
    () => achievementUnlockQueue.value[activeAchievementUnlockIndex.value],
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
const MAX_ROAD_BUILD_AMOUNT = 10_000_000;

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

const resourceDisplayOrder: ResourceKey[] = ['wood', 'food', 'stone', 'gold'];
const minigameComponents = {
    gold: GoldMinigame,
    wood: WoodMinigame,
    stone: StoneMinigame,
    food: FoodMinigame,
};

const resourceCards = computed(() => [
    ...resourceDisplayOrder.map((key) => ({
        key,
        label: resourceLabels[key],
        amount: props.resources[key],
        rate: formatRate(props.resourceRates[key]),
        icon: resourceIcons[key],
        class: resourceStyles[key],
    })),
]);

const lifetimeResourceCards = computed(() => [
    ...resourceDisplayOrder.map((key) => ({
        key,
        label: resourceLabels[key],
        amount: props.lifetimeResources[key],
        icon: resourceIcons[key],
    })),
]);

const lifetimeTotalResources = computed(() =>
    getTotalResources(props.lifetimeResources),
);
const selectedMinigameResourceAmount = computed(() =>
    selectedMinigame.value
        ? props.resources[selectedMinigame.value.resource]
        : 0,
);
const selectedMinigameComponent = computed(() =>
    selectedMinigame.value
        ? minigameComponents[selectedMinigame.value.resource]
        : null,
);
const defaultLeaderboard = computed<Leaderboard | null>(
    () =>
        leaderboards.value.find(
            (leaderboard) =>
                leaderboard.key === props.leaderboards.defaultKey,
        ) ??
        leaderboards.value[0] ??
        null,
);
const selectedLeaderboard = computed<Leaderboard | null>(
    () =>
        leaderboards.value.find(
            (leaderboard) => leaderboard.key === selectedLeaderboardKey.value,
        ) ??
        defaultLeaderboard.value ??
        null,
);
const isMinigameOpen = computed(
    () =>
        activeGameModal.value === 'minigame' &&
        selectedMinigame.value !== null,
);
const isLeaderboardModalOpen = computed(
    () =>
        activeGameModal.value === 'leaderboard' &&
        selectedLeaderboard.value !== null,
);
const prestigeRankLabel = computed(
    () => `#${props.prestigeStats.rank.toLocaleString()}`,
);
const defaultLeaderboardRankLabel = computed(() =>
    defaultLeaderboard.value
        ? `#${defaultLeaderboard.value.currentRank.toLocaleString()}`
        : '#-',
);
const prestigeRequirementLabel = computed(() =>
    props.prestigeStats.requirement.toLocaleString(),
);
const prestigeProgressPercent = computed(() =>
    props.prestigeStats.requirement > 0
        ? Math.min(
              100,
              Math.floor(
                  (props.roadStats.length / props.prestigeStats.requirement) *
                      100,
              ),
          )
        : 0,
);
const collectDisabled = computed(() => isCollecting.value || !props.canCollect);
const collectButtonLabel = computed(() => {
    if (isCollecting.value) {
        return 'Collecting...';
    }

    return props.canCollect ? 'Collect' : 'Collected today';
});
const serverDateTimeLabel = computed(() =>
    formatServerDateTime(serverTimeMilliseconds.value),
);
const serverTimezoneLabel = computed(() =>
    props.serverTime.timezone.replaceAll('_', ' '),
);
const weatherIcon = computed(() =>
    weatherIconFor(props.weather.weatherCode, props.weather.conditions),
);
const weatherCoordinatesLabel = computed(
    () => `${props.weather.latitude}, ${props.weather.longitude}`,
);
const weatherLocationLabel = computed(() =>
    props.weather.isUsingGeolocation ? 'Browser location' : 'Default location',
);
const weatherLocationUpdatedLabel = computed(() =>
    props.weather.locationUpdatedAt
        ? `Updated ${props.weather.locationUpdatedAt}`
        : null,
);
const weatherConditionLabel = computed(() =>
    getWeatherConditionLabel(props.weather.conditions),
);

type OpenMeteoCurrentWeatherResponse = {
    current?: {
        time?: string;
        weather_code?: number;
    };
};

let serverTimeBaseMilliseconds = Date.now();
let serverTimeClientStartedAt = Date.now();
let serverTimeInterval: number | undefined;
let clientWeatherRefreshTimeout: number | undefined;
let lastRequestedWeatherRefreshSlotMilliseconds: number | undefined;

const WEATHER_REFRESH_MINUTES = [3, 18, 33, 48];
const WEATHER_REFRESH_HOUR_MILLISECONDS = 60 * 60_000;

function syncServerTime() {
    const parsedServerTime = Date.parse(props.serverTime.iso);

    serverTimeBaseMilliseconds = Number.isNaN(parsedServerTime)
        ? Date.now()
        : parsedServerTime;
    serverTimeClientStartedAt = Date.now();
    serverTimeMilliseconds.value = serverTimeBaseMilliseconds;
}

function formatServerDateTime(milliseconds: number): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: props.serverTime.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(new Date(milliseconds));
    const partValue = (type: string) =>
        parts.find((part) => part.type === type)?.value ?? '00';

    return `${partValue('year')}-${partValue('month')}-${partValue('day')} ${partValue('hour')}:${partValue('minute')}:${partValue('second')}`;
}

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

watch(() => props.serverTime.iso, syncServerTime, { immediate: true });

watch(
    () => [
        props.weather.isUsingGeolocation,
        props.weather.latitude,
        props.weather.longitude,
        props.weather.updatedAtIso,
        props.serverTime.iso,
    ],
    () => {
        scheduleNextClientWeatherRefresh();
    },
);

onMounted(() => {
    serverTimeInterval = window.setInterval(() => {
        serverTimeMilliseconds.value =
            serverTimeBaseMilliseconds +
            (Date.now() - serverTimeClientStartedAt);
    }, 1000);

    scheduleNextClientWeatherRefresh();
});

onBeforeUnmount(() => {
    if (serverTimeInterval !== undefined) {
        window.clearInterval(serverTimeInterval);
    }

    clearClientWeatherRefreshTimeout();
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

function updateWeatherLocation() {
    if (!navigator.geolocation) {
        weatherLocationStatus.value = 'Geolocation is not supported here.';

        return;
    }

    isUpdatingWeatherLocation.value = true;
    weatherLocationStatus.value = 'Waiting for permission...';

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            try {
                weatherLocationStatus.value =
                    'Fetching weather from this device...';
                const currentWeather = await fetchCurrentWeather(
                    position.coords.latitude,
                    position.coords.longitude,
                );

                weatherLocationStatus.value = 'Saving weather...';

                router.post(
                    '/dashboard/weather-location',
                    {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        weather_code: currentWeather.weatherCode,
                        api_time: currentWeather.apiTime,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            weatherLocationStatus.value =
                                'Weather location updated.';
                        },
                        onError: () => {
                            weatherLocationStatus.value =
                                'Weather location could not be updated.';
                        },
                        onFinish: () => {
                            isUpdatingWeatherLocation.value = false;
                            scheduleNextClientWeatherRefresh();
                        },
                    },
                );
            } catch {
                isUpdatingWeatherLocation.value = false;
                weatherLocationStatus.value =
                    'Weather could not be fetched from this device.';
                scheduleNextClientWeatherRefresh();
            }
        },
        (error) => {
            isUpdatingWeatherLocation.value = false;
            weatherLocationStatus.value =
                error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied.'
                    : 'Current location could not be detected.';
            scheduleNextClientWeatherRefresh();
        },
        {
            enableHighAccuracy: false,
            maximumAge: 300000,
            timeout: 10000,
        },
    );
}

async function fetchCurrentWeather(latitude: number, longitude: number) {
    const parameters = new URLSearchParams({
        latitude: String(latitude),
        longitude: String(longitude),
        current: 'weather_code',
        timezone: 'UTC',
    });
    const response = await fetch(
        `https://api.open-meteo.com/v1/forecast?${parameters.toString()}`,
    );

    if (!response.ok) {
        throw new Error('Open-Meteo request failed.');
    }

    const data = (await response.json()) as OpenMeteoCurrentWeatherResponse;
    const weatherCode = Number(data.current?.weather_code);
    const apiTime = data.current?.time;

    if (!Number.isInteger(weatherCode) || !apiTime) {
        throw new Error('Open-Meteo response is missing current weather.');
    }

    return {
        weatherCode,
        apiTime: apiTime.endsWith('Z') ? apiTime : `${apiTime}Z`,
    };
}

async function refreshWeatherFromSavedBrowserLocation() {
    if (!props.weather.isUsingGeolocation || isUpdatingWeatherLocation.value) {
        scheduleNextClientWeatherRefresh();

        return;
    }

    lastRequestedWeatherRefreshSlotMilliseconds =
        latestWeatherRefreshSlotMilliseconds();
    isUpdatingWeatherLocation.value = true;

    try {
        const currentWeather = await fetchCurrentWeather(
            props.weather.latitude,
            props.weather.longitude,
        );

        router.post(
            '/dashboard/weather-location',
            {
                latitude: props.weather.latitude,
                longitude: props.weather.longitude,
                weather_code: currentWeather.weatherCode,
                api_time: currentWeather.apiTime,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    weatherLocationStatus.value =
                        'Automatic weather refresh failed.';
                },
                onFinish: () => {
                    isUpdatingWeatherLocation.value = false;
                    scheduleNextClientWeatherRefresh();
                },
            },
        );
    } catch {
        isUpdatingWeatherLocation.value = false;
        weatherLocationStatus.value = 'Automatic weather refresh failed.';
        scheduleNextClientWeatherRefresh();
    }
}

function scheduleNextClientWeatherRefresh() {
    clearClientWeatherRefreshTimeout();

    if (!props.weather.isUsingGeolocation) {
        return;
    }

    if (shouldRefreshClientWeatherNow()) {
        clientWeatherRefreshTimeout = window.setTimeout(
            refreshWeatherFromSavedBrowserLocation,
            0,
        );

        return;
    }

    clientWeatherRefreshTimeout = window.setTimeout(
        refreshWeatherFromSavedBrowserLocation,
        millisecondsUntilNextWeatherRefresh(),
    );
}

function clearClientWeatherRefreshTimeout() {
    if (clientWeatherRefreshTimeout !== undefined) {
        window.clearTimeout(clientWeatherRefreshTimeout);
        clientWeatherRefreshTimeout = undefined;
    }
}

function shouldRefreshClientWeatherNow(): boolean {
    const latestRefreshSlotMilliseconds =
        latestWeatherRefreshSlotMilliseconds();

    if (
        lastRequestedWeatherRefreshSlotMilliseconds !== undefined &&
        lastRequestedWeatherRefreshSlotMilliseconds >=
            latestRefreshSlotMilliseconds
    ) {
        return false;
    }

    const updatedAtMilliseconds = props.weather.updatedAtIso
        ? Date.parse(props.weather.updatedAtIso)
        : Number.NaN;

    return (
        Number.isNaN(updatedAtMilliseconds) ||
        updatedAtMilliseconds < latestRefreshSlotMilliseconds
    );
}

function millisecondsUntilNextWeatherRefresh(): number {
    const currentServerMilliseconds = currentServerTimeMilliseconds();
    const { minute, second, millisecond } = serverTimeParts(
        currentServerMilliseconds,
    );
    const currentMinuteMilliseconds =
        minute * 60_000 + second * 1000 + millisecond;

    for (const refreshMinute of WEATHER_REFRESH_MINUTES) {
        const refreshMinuteMilliseconds = refreshMinute * 60_000;

        if (refreshMinuteMilliseconds > currentMinuteMilliseconds) {
            return refreshMinuteMilliseconds - currentMinuteMilliseconds;
        }
    }

    return (
        WEATHER_REFRESH_HOUR_MILLISECONDS -
        currentMinuteMilliseconds +
        WEATHER_REFRESH_MINUTES[0] * 60_000
    );
}

function latestWeatherRefreshSlotMilliseconds(): number {
    const currentServerMilliseconds = currentServerTimeMilliseconds();
    const { minute, second, millisecond } = serverTimeParts(
        currentServerMilliseconds,
    );
    const latestRefreshMinute =
        [...WEATHER_REFRESH_MINUTES]
            .reverse()
            .find((refreshMinute) => refreshMinute <= minute) ??
        WEATHER_REFRESH_MINUTES[WEATHER_REFRESH_MINUTES.length - 1];
    const elapsedSinceLatestRefresh =
        latestRefreshMinute <= minute
            ? (minute - latestRefreshMinute) * 60_000 +
              second * 1000 +
              millisecond
            : (minute + 60 - latestRefreshMinute) * 60_000 +
              second * 1000 +
              millisecond;

    return currentServerMilliseconds - elapsedSinceLatestRefresh;
}

function currentServerTimeMilliseconds(): number {
    return serverTimeBaseMilliseconds + (Date.now() - serverTimeClientStartedAt);
}

function serverTimeParts(milliseconds: number) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: props.serverTime.timezone,
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(new Date(milliseconds));
    const partValue = (type: string) =>
        Number(parts.find((part) => part.type === type)?.value ?? 0);

    return {
        minute: partValue('minute'),
        second: partValue('second'),
        millisecond: milliseconds % 1000,
    };
}

function useDefaultWeatherLocation() {
    isUpdatingWeatherLocation.value = true;
    weatherLocationStatus.value = 'Switching to default location...';

    router.post(
        '/dashboard/weather-location/default',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                weatherLocationStatus.value = 'Default location restored.';
                lastRequestedWeatherRefreshSlotMilliseconds = undefined;
                clearClientWeatherRefreshTimeout();
            },
            onError: () => {
                weatherLocationStatus.value =
                    'Default location could not be restored.';
            },
            onFinish: () => {
                isUpdatingWeatherLocation.value = false;
                scheduleNextClientWeatherRefresh();
            },
        },
    );
}

function completeMinigame(minigame: Minigame) {
    router.post(
        `/dashboard/minigames/${minigame.resource}/complete`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                activeMinigameResource.value = minigame.resource;
            },
            onSuccess: () => {
                hasWonMinigame.value = true;
            },
            onError: (errors) => {
                if (errors.minigame) {
                    toast.error(errors.minigame);
                }
            },
            onFinish: () => {
                activeMinigameResource.value = null;
            },
        },
    );
}

function openMinigame(minigame: Minigame) {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isBuildingsOpen.value = false;
    isPrestigeConfirmOpen.value = false;
    selectedMinigameResource.value = minigame.resource;
    hasWonMinigame.value = false;
    activeGameModal.value = 'minigame';
}

function closeMinigame() {
    if (activeMinigameResource.value !== null) {
        return;
    }

    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    if (activeGameModal.value === 'minigame') {
        activeGameModal.value = null;
    }
}

function continueMinigame() {
    hasWonMinigame.value = false;
}

function completeSelectedMinigame() {
    if (!selectedMinigame.value) {
        return;
    }

    completeMinigame(selectedMinigame.value);
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

function openBuildings() {
    activeGameModal.value = null;
    isPrestigeConfirmOpen.value = false;
    isBuildingsOpen.value = true;
}

function closeBuildings() {
    isBuildingsOpen.value = false;
}

function openPrestigeConfirm() {
    isPrestigeConfirmOpen.value = true;
}

function closePrestigeConfirm() {
    isPrestigeConfirmOpen.value = false;
}

function openLeaderboard(leaderboardKey = defaultLeaderboard.value?.key) {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isBuildingsOpen.value = false;
    isPrestigeConfirmOpen.value = false;
    if (leaderboardKey) {
        selectedLeaderboardKey.value = leaderboardKey;
    }

    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'leaderboard';
}

function closeLeaderboard() {
    if (activeGameModal.value === 'leaderboard') {
        activeGameModal.value = null;
    }
}

function closeActiveGameModal() {
    if (isBuildingsOpen.value) {
        closeBuildings();

        return;
    }

    if (activeGameModal.value === 'leaderboard') {
        closeLeaderboard();

        return;
    }

    closeMinigame();
}

function prestige() {
    if (!props.prestigeStats.canPrestige) {
        return;
    }

    openPrestigeConfirm();
}

function confirmPrestige() {
    if (!props.prestigeStats.canPrestige) {
        return;
    }

    closePrestigeConfirm();

    router.post(
        '/dashboard/prestige',
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isPrestiging.value = true;
            },
            onFinish: () => {
                isPrestiging.value = false;
            },
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
    <Head title="Dashboard mode" />

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

                <div class="flex flex-col gap-3 sm:items-end">
                    <div class="text-left sm:text-right">
                        <p
                            class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Server time
                        </p>
                        <p class="mt-1 text-sm font-semibold">
                            {{ serverDateTimeLabel }}
                        </p>
                        <p class="text-xs text-[#7a705d] dark:text-[#aaa18f]">
                            {{ serverTimezoneLabel }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 sm:justify-end">
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
                            @click="openBuildings"
                        >
                            <Building2 class="h-4 w-4" />
                            Buildings
                        </button>
                    </div>
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

            <section
                class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-[#243627] p-3 text-white">
                            <component :is="weatherIcon" class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Local weather
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Coordinates {{ weatherCoordinatesLabel }}
                            </h2>
                            <p
                                class="mt-1 text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Saved Open-Meteo weather code for the tracked
                                location.
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md border border-[#b7aa91] px-3 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                                    :disabled="isUpdatingWeatherLocation"
                                    @click="updateWeatherLocation"
                                >
                                    <LocateFixed class="h-4 w-4" />
                                    {{
                                        isUpdatingWeatherLocation
                                            ? 'Locating...'
                                            : 'Use my location'
                                    }}
                                </button>
                                <button
                                    v-if="props.weather.isUsingGeolocation"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md border border-[#b7aa91] px-3 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                                    :disabled="isUpdatingWeatherLocation"
                                    @click="useDefaultWeatherLocation"
                                >
                                    <RotateCcw class="h-4 w-4" />
                                    Use default location
                                </button>
                                <span
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    {{ weatherLocationLabel }}
                                </span>
                                <span
                                    v-if="weatherLocationUpdatedLabel"
                                    class="text-sm font-semibold text-[#7a705d] dark:text-[#aaa18f]"
                                >
                                    {{ weatherLocationUpdatedLabel }}
                                </span>
                            </div>
                            <p
                                v-if="weatherLocationStatus"
                                class="mt-2 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                            >
                                {{ weatherLocationStatus }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <component
                            :is="weatherIcon"
                            class="h-10 w-10 text-[#7b633d] dark:text-[#caa66c]"
                        />
                        <div>
                            <p
                                class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Weather code
                            </p>
                            <p class="mt-1 text-3xl font-bold">
                                {{ props.weather.weatherCode ?? '-' }}
                            </p>
                            <p
                                class="mt-1 text-sm font-semibold text-[#7a705d] dark:text-[#aaa18f]"
                            >
                                {{ weatherConditionLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
            >
                <header
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Minigames
                        </p>
                        <h2 class="mt-1 text-xl font-bold">Resource runs</h2>
                    </div>
                    <p
                        class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                    >
                        1 + 2% hourly production
                    </p>
                </header>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="minigame in minigames"
                        :key="minigame.resource"
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">
                                    {{ minigame.label }}
                                </h3>
                                <p
                                    class="mt-2 text-sm text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    {{
                                        minigame.currentProduction.toLocaleString()
                                    }}
                                    /hour
                                </p>
                            </div>
                            <component
                                :is="resourceIcons[minigame.resource]"
                                class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                            />
                        </div>

                        <div class="mt-4 space-y-2 text-sm">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    class="text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Reward
                                </span>
                                <span class="font-semibold">
                                    {{ minigame.rewardLabel }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    class="text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Completed
                                </span>
                                <span class="font-semibold">
                                    {{ minigame.completions.toLocaleString() }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    class="text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Total gained
                                </span>
                                <span class="font-semibold">
                                    {{
                                        minigame.resourcesGained.toLocaleString()
                                    }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            data-game-modal-launcher="minigame"
                            :data-resource="minigame.resource"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                activeMinigameResource === minigame.resource
                            "
                            @click="openMinigame(minigame)"
                        >
                            <Gamepad2 class="h-4 w-4" />
                            {{
                                activeMinigameResource === minigame.resource
                                    ? 'Completing...'
                                    : 'Play'
                            }}
                        </button>
                    </article>
                </div>
            </section>

            <section>
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
            </section>

            <section>
                <button
                    type="button"
                    data-game-modal-launcher="leaderboard"
                    class="w-full rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#c9b995] hover:shadow-md dark:border-[#38362f] dark:bg-[#1a1d15] dark:hover:border-[#5a523f]"
                    @click="openLeaderboard()"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div class="rounded-md bg-[#243627] p-2 text-white">
                                <Trophy class="h-5 w-5" />
                            </div>
                            <div>
                                <p
                                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                                >
                                    Leaderboard
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    Your current spot
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Open the top 50 rankings and swap between
                                    prestige, manual collects, and minigame
                                    completions.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="defaultLeaderboard"
                            class="grid gap-3 sm:grid-cols-3 lg:min-w-[28rem]"
                        >
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Default board
                                </p>
                                <p class="mt-2 text-xl font-bold">
                                    {{ defaultLeaderboard.label }}
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Rank
                                </p>
                                <p class="mt-2 text-xl font-bold">
                                    {{ defaultLeaderboardRankLabel }}
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Score
                                </p>
                                <p class="mt-2 text-xl font-bold">
                                    {{
                                        defaultLeaderboard.currentValueLabel
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </button>
            </section>

            <section
                class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-[#5c3b25] p-2 text-white">
                            <RotateCcw class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Prestige</h2>
                            <p
                                class="text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Reset your active settlement after connecting
                                the planet, while keeping achievements and
                                lifetime records.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-[#5c3b25] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#472d1c] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="
                            !props.prestigeStats.canPrestige || isPrestiging
                        "
                        @click="prestige"
                    >
                        <RotateCcw class="h-4 w-4" />
                        {{ isPrestiging ? 'Prestiging...' : 'Prestige' }}
                    </button>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Prestiges
                        </p>
                        <p class="mt-3 text-3xl font-bold">
                            {{ props.prestigeStats.count.toLocaleString() }}
                        </p>
                    </div>

                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <p
                                class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Prestige rank
                            </p>
                            <Trophy
                                class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                            />
                        </div>
                        <p class="mt-3 text-3xl font-bold">
                            {{ prestigeRankLabel }}
                        </p>
                    </div>

                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Current roads
                        </p>
                        <p class="mt-3 text-3xl font-bold">
                            {{ props.roadStats.length.toLocaleString() }} km
                        </p>
                    </div>

                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Required roads
                        </p>
                        <p class="mt-3 text-3xl font-bold">
                            {{ prestigeRequirementLabel }} km
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div
                        class="flex items-center justify-between gap-4 text-xs font-semibold text-[#7a705d] dark:text-[#aaa18f]"
                    >
                        <span>Prestige progress</span>
                        <span>{{ prestigeProgressPercent }}%</span>
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-[#e9e1d3] dark:bg-[#24281d]"
                    >
                        <div
                            class="h-full rounded-full bg-[#5c3b25] dark:bg-[#caa66c]"
                            :style="{ width: `${prestigeProgressPercent}%` }"
                        />
                    </div>
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
            v-if="isPrestigeConfirmOpen"
            class="fixed inset-0 z-[55] flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 px-4 py-6"
            @click.self="closePrestigeConfirm"
        >
            <section
                class="max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <header class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="rounded-md bg-[#5c3b25] p-3 text-white">
                            <RotateCcw class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Prestige
                            </p>
                            <h2 class="mt-1 text-2xl font-bold">
                                Begin a new settlement
                            </h2>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                        aria-label="Close prestige confirmation"
                        @click="closePrestigeConfirm"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <p
                    class="mt-5 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                >
                    Your current resources, buildings, and roads will reset to
                    zero. Your unlocked achievements, achievement bonuses,
                    lifetime resources, and prestige count stay.
                </p>

                <div
                    class="mt-5 grid gap-3 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <div class="flex items-center justify-between gap-4">
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Required road length
                        </p>
                        <p class="text-sm font-bold">
                            {{ prestigeRequirementLabel }} km
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Current road length
                        </p>
                        <p
                            class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ props.roadStats.length.toLocaleString() }} km
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Prestiges after reset
                        </p>
                        <p
                            class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{
                                (props.prestigeStats.count + 1).toLocaleString()
                            }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-5 flex flex-col-reverse gap-3 border-t border-[#e4dac7] pt-4 sm:flex-row sm:justify-end dark:border-[#35332c]"
                >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-[#b7aa91] px-4 py-2.5 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                        @click="closePrestigeConfirm"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-[#5c3b25] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#472d1c] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isPrestiging"
                        @click="confirmPrestige"
                    >
                        <RotateCcw class="h-4 w-4" />
                        {{
                            isPrestiging ? 'Prestiging...' : 'Confirm prestige'
                        }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="isBuildingsOpen || isLeaderboardModalOpen || isMinigameOpen"
            data-game-modal-layer
            class="fixed inset-0 z-[58] flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 px-4 py-6"
            @click.self="closeActiveGameModal"
        >
            <section
                v-if="isBuildingsOpen"
                class="max-h-[calc(100vh-3rem)] w-full max-w-5xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
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
                        @click="closeBuildings"
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
            <section
                v-else-if="isLeaderboardModalOpen && selectedLeaderboard"
                class="max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Leaderboard
                        </p>
                        <h2 class="mt-1 text-2xl font-bold">Top 50 players</h2>
                    </div>
                    <div class="flex items-center justify-end">
                        <button
                            type="button"
                            class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                            aria-label="Close leaderboard"
                            @click="closeLeaderboard"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-for="leaderboard in leaderboards"
                        :key="leaderboard.key"
                        type="button"
                        class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                        :class="
                            selectedLeaderboard.key === leaderboard.key
                                ? 'border-[#243627] bg-[#243627] text-white'
                                : 'border-[#d7cbb8] text-[#4f574b] hover:bg-[#ebe4d7] dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]'
                        "
                        @click="selectedLeaderboardKey = leaderboard.key"
                    >
                        {{ leaderboard.label }}
                    </button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Your rank
                        </p>
                        <p class="mt-2 text-2xl font-bold">
                            #{{ selectedLeaderboard.currentRank.toLocaleString() }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Your score
                        </p>
                        <p class="mt-2 text-2xl font-bold">
                            {{ selectedLeaderboard.currentValueLabel }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Metric
                        </p>
                        <p class="mt-2 text-2xl font-bold">
                            {{ selectedLeaderboard.metricLabel }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-md border border-[#e4dac7] dark:border-[#35332c]">
                    <div
                        class="grid grid-cols-[4.5rem_1fr_8rem] gap-3 border-b border-[#e4dac7] bg-[#f6f0e5] px-4 py-3 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:bg-[#151910] dark:text-[#b6ae9d]"
                    >
                        <span>Rank</span>
                        <span>Player</span>
                        <span class="text-right">Score</span>
                    </div>

                    <div
                        v-if="selectedLeaderboard.entries.length === 0"
                        class="px-4 py-8 text-center text-sm font-medium text-[#696250] dark:text-[#b6ae9d]"
                    >
                        No players on this leaderboard yet.
                    </div>

                    <div v-else class="divide-y divide-[#e4dac7] dark:divide-[#35332c]">
                        <div
                            v-for="entry in selectedLeaderboard.entries"
                            :key="`${selectedLeaderboard.key}-${entry.userId}`"
                            class="grid grid-cols-[4.5rem_1fr_8rem] items-center gap-3 px-4 py-3 text-sm"
                            :class="
                                entry.isCurrentUser
                                    ? 'bg-[#edf6e8] text-[#243627] dark:bg-[#1d2a17] dark:text-[#d7edc5]'
                                    : ''
                            "
                        >
                            <span class="font-bold">
                                #{{ entry.rank.toLocaleString() }}
                            </span>
                            <span class="min-w-0 truncate font-semibold">
                                {{ entry.userName }}
                                <span
                                    v-if="entry.isCurrentUser"
                                    class="ml-2 rounded-sm bg-[#243627] px-2 py-0.5 text-xs text-white"
                                >
                                    You
                                </span>
                            </span>
                            <span class="text-right font-bold">
                                {{ entry.valueLabel }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
            <section
                v-else-if="isMinigameOpen && selectedMinigame"
                class="max-h-[calc(100vh-3rem)] w-full max-w-5xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Minigame
                        </p>
                        <h2 class="mt-1 text-2xl font-bold">
                            {{ selectedMinigame.label }}
                        </h2>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                            :disabled="activeMinigameResource !== null"
                            @click="openLeaderboard()"
                        >
                            <Trophy class="h-4 w-4" />
                            Leaderboard
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                            aria-label="Close minigame"
                            :disabled="activeMinigameResource !== null"
                            @click="closeMinigame"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div class="relative">
                    <component
                        :is="selectedMinigameComponent"
                        :is-saving="
                            activeMinigameResource === selectedMinigame.resource
                        "
                        :is-completed="hasWonMinigame"
                        @complete="completeSelectedMinigame"
                    />
                    <button
                        v-if="
                            hasWonMinigame && activeMinigameResource === null
                        "
                        type="button"
                        class="absolute inset-0 z-50 cursor-pointer rounded-md bg-transparent"
                        aria-label="Continue playing"
                        @click="continueMinigame"
                    ></button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Reward
                        </p>
                        <p class="mt-2 text-xl font-bold">
                            {{ selectedMinigame.rewardLabel }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Current resources
                        </p>
                        <p class="mt-2 text-xl font-bold">
                            {{
                                selectedMinigameResourceAmount.toLocaleString()
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Completions
                        </p>
                        <p class="mt-2 text-xl font-bold">
                            {{ selectedMinigame.completions.toLocaleString() }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                    >
                        <p
                            class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Total gained
                        </p>
                        <p class="mt-2 text-xl font-bold">
                            {{
                                selectedMinigame.resourcesGained.toLocaleString()
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="hasWonMinigame"
                    class="mt-5 flex flex-col gap-3 border-t border-[#e4dac7] pt-4 sm:flex-row sm:justify-end dark:border-[#35332c]"
                >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="continueMinigame"
                    >
                        <Gamepad2 class="h-4 w-4" />
                        Continue playing
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-[#b7aa91] px-4 py-2.5 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                        @click="closeMinigame"
                    >
                        Stop playing
                    </button>
                </div>
            </section>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="currentAchievementUnlock"
            class="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[calc(100%-2rem)] max-w-md items-end justify-end sm:right-6 sm:bottom-6"
        >
            <section
                class="pointer-events-auto max-h-[calc(100vh-3rem)] w-full overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
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
</template>
