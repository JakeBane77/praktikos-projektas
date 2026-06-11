<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
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
    UsersRound,
    Wheat,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AchievementUnlockModal from '@/components/game-modals/AchievementUnlockModal.vue';
import AllianceModal from '@/components/game-modals/AllianceModal.vue';
import BuildingsModal from '@/components/game-modals/BuildingsModal.vue';
import LeaderboardModal from '@/components/game-modals/LeaderboardModal.vue';
import MinigameModal from '@/components/game-modals/MinigameModal.vue';
import OfflineProgressModal from '@/components/game-modals/OfflineProgressModal.vue';
import PrestigeConfirmModal from '@/components/game-modals/PrestigeConfirmModal.vue';
import FoodMinigame from '@/components/minigames/FoodMinigame.vue';
import GoldMinigame from '@/components/minigames/GoldMinigame.vue';
import StoneMinigame from '@/components/minigames/StoneMinigame.vue';
import WoodMinigame from '@/components/minigames/WoodMinigame.vue';
import {
    affordableRoadAmount,
    formatExactNumber,
    formatGameNumber,
    formatHoursDuration,
    formatRate,
    minigameStaminaHoverLabel,
    getTotalResources,
    upgradeAvailabilityFor,
} from '@/lib/game';
import type {
    AchievementUnlock,
    Building,
    DashboardGameData,
    Leaderboard,
    Minigame,
    ResourceKey,
} from '@/lib/game';
import {
    weatherConditionLabel as getWeatherConditionLabel,
    weatherIconFor,
} from '@/lib/weather';
import { dashboard } from '@/routes';

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
const isSubmittingAlliance = ref(false);
let allianceSearchReloadTimeout: ReturnType<typeof setTimeout> | null = null;
const activeGameModal = ref<
    'alliance' | 'leaderboard' | 'minigame' | 'prestige-confirm' | null
>(null);
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
const expandedResourceNumberKeys = ref<Set<string>>(new Set());
const isOfflineProgressDismissed = ref(false);
const predictionBaseMilliseconds = ref(Date.now());
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
            (leaderboard) => leaderboard.key === props.leaderboards.defaultKey,
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
        activeGameModal.value === 'minigame' && selectedMinigame.value !== null,
);
const isLeaderboardModalOpen = computed(
    () =>
        activeGameModal.value === 'leaderboard' &&
        selectedLeaderboard.value !== null,
);
const isAllianceModalOpen = computed(() => activeGameModal.value === 'alliance');
const isPrestigeConfirmModalOpen = computed(
    () => activeGameModal.value === 'prestige-confirm',
);
const isOfflineProgressModalOpen = computed(
    () =>
        props.offlineProgress !== null &&
        !isOfflineProgressDismissed.value &&
        !isBuildingsOpen.value &&
        !isAllianceModalOpen.value &&
        !isLeaderboardModalOpen.value &&
        !isMinigameOpen.value &&
        !isPrestigeConfirmModalOpen.value,
);
const isAchievementUnlockModalOpen = computed(
    () =>
        Boolean(currentAchievementUnlock.value) &&
        !isOfflineProgressModalOpen.value &&
        !isBuildingsOpen.value &&
        !isAllianceModalOpen.value &&
        !isLeaderboardModalOpen.value &&
        !isMinigameOpen.value &&
        !isPrestigeConfirmModalOpen.value,
);
const prestigeRankLabel = computed(
    () => `#${formatExactNumber(props.prestigeStats.rank)}`,
);
const defaultLeaderboardRankLabel = computed(() =>
    defaultLeaderboard.value
        ? `#${formatExactNumber(defaultLeaderboard.value.currentRank)}`
        : '#-',
);
const prestigeRequirementLabel = computed(() =>
    formatGameNumber(props.prestigeStats.requirement),
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
const offlineProgressDurationLabel = computed(() =>
    formatOfflineProgressDuration(props.offlineProgress?.elapsedHours ?? 0),
);
const offlineProgressResourceRows = computed(() =>
    resourceDisplayOrder
        .map((key) => ({
            key,
            label: resourceLabels[key],
            amount: props.offlineProgress?.resources[key] ?? 0,
            icon: resourceIcons[key],
        }))
        .filter((resource) => resource.amount > 0),
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
    predictionBaseMilliseconds.value = serverTimeBaseMilliseconds;
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

function isResourceNumberExpanded(key: string): boolean {
    return expandedResourceNumberKeys.value.has(key);
}

function toggleResourceNumber(key: string): void {
    const nextExpandedKeys = new Set(expandedResourceNumberKeys.value);

    if (nextExpandedKeys.has(key)) {
        nextExpandedKeys.delete(key);
    } else {
        nextExpandedKeys.add(key);
    }

    expandedResourceNumberKeys.value = nextExpandedKeys;
}

function resourceNumberLabel(key: string, value: number): string {
    return isResourceNumberExpanded(key)
        ? formatExactNumber(value)
        : formatGameNumber(value);
}

function resourceNumberTitle(key: string): string {
    return isResourceNumberExpanded(key)
        ? 'Show compact number'
        : 'Show full number';
}

function formatOfflineProgressDuration(elapsedHours: number): string {
    const days = Math.floor(elapsedHours / 24);
    const hours = elapsedHours % 24;
    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
    }

    if (hours > 0 || parts.length === 0) {
        parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
    }

    return parts.join(', ');
}

function upgradeAvailabilityLabelFor(building: Building): string | null {
    const availability = upgradeAvailabilityFor(
        building,
        props.resources,
        props.resourceRates,
    );

    if (availability === null) {
        return null;
    }

    if (availability.hours === null || availability.hours <= 0) {
        return availability.label;
    }

    const targetMilliseconds =
        predictionBaseMilliseconds.value + availability.hours * 60 * 60_000;
    const remainingHours = Math.ceil(
        Math.max(0, targetMilliseconds - serverTimeMilliseconds.value) /
            (60 * 60_000),
    );

    if (remainingHours <= 0) {
        return 'Available now';
    }

    return `Available in ${formatHoursDuration(remainingHours)} (${formatUpgradeAvailableAt(targetMilliseconds)})`;
}

function formatUpgradeAvailableAt(milliseconds: number): string {
    return new Intl.DateTimeFormat('en-GB', {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(milliseconds));
}

function roadBuildableAmountFor(building: Building): number {
    return affordableRoadAmount(
        building,
        props.resources,
        MAX_ROAD_BUILD_AMOUNT,
    );
}

function updateRoadBuildAmount(payload: {
    buildingId: number;
    amount: number;
}): void {
    roadBuildAmounts.value[payload.buildingId] = payload.amount;
}

function closeOfflineProgress() {
    isOfflineProgressDismissed.value = true;
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

    if (allianceSearchReloadTimeout !== null) {
        clearTimeout(allianceSearchReloadTimeout);
    }
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
    return (
        serverTimeBaseMilliseconds + (Date.now() - serverTimeClientStartedAt)
    );
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
    if (activeMinigameResource.value !== null || !minigame.stamina.isAvailable) {
        return;
    }

    isBuildingsOpen.value = false;
    closeOfflineProgress();
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
    closeOfflineProgress();
    isBuildingsOpen.value = true;
}

function closeBuildings() {
    isBuildingsOpen.value = false;
}

function openAlliance() {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isBuildingsOpen.value = false;
    closeOfflineProgress();
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'alliance';
    scheduleAllianceReload('');
}

function closeAlliance() {
    if (activeGameModal.value === 'alliance') {
        activeGameModal.value = null;
    }
}

function searchAlliances(query: string) {
    scheduleAllianceReload(query);
}

function scheduleAllianceReload(query: string) {
    if (allianceSearchReloadTimeout !== null) {
        clearTimeout(allianceSearchReloadTimeout);
    }

    allianceSearchReloadTimeout = setTimeout(() => {
        router.reload({
            data: {
                alliance_search: query.trim(),
            },
            only: ['alliances'],
        });
    }, 500);
}

function openPrestigeConfirm() {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isBuildingsOpen.value = false;
    closeOfflineProgress();
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'prestige-confirm';
}

function closePrestigeConfirm() {
    if (activeGameModal.value === 'prestige-confirm') {
        activeGameModal.value = null;
    }
}

function openLeaderboard(leaderboardKey = defaultLeaderboard.value?.key) {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isBuildingsOpen.value = false;
    closeOfflineProgress();

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
    if (isOfflineProgressModalOpen.value) {
        closeOfflineProgress();

        return;
    }

    if (isBuildingsOpen.value) {
        closeBuildings();

        return;
    }

    if (activeGameModal.value === 'prestige-confirm') {
        closePrestigeConfirm();

        return;
    }

    if (activeGameModal.value === 'alliance') {
        closeAlliance();

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

function createAlliance(payload: {
    name: string;
    description: string | null;
    is_open: boolean;
}) {
    router.post('/alliances', payload, {
        preserveScroll: true,
        onStart: () => {
            isSubmittingAlliance.value = true;
        },
        onError: (errors) => {
            if (errors.alliance) {
                toast.error(errors.alliance);
            }
        },
        onFinish: () => {
            isSubmittingAlliance.value = false;
        },
    });
}

function joinAlliance(allianceId: number) {
    router.post(
        `/alliances/${allianceId}/join`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function applyToAlliance(allianceId: number) {
    router.post(
        `/alliances/${allianceId}/apply`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function updateAlliance(payload: {
    allianceId: number;
    description?: string | null;
    is_open?: boolean;
}) {
    router.patch(`/alliances/${payload.allianceId}`, payload, {
        preserveScroll: true,
        onStart: () => {
            isSubmittingAlliance.value = true;
        },
        onError: (errors) => {
            if (errors.alliance) {
                toast.error(errors.alliance);
            }
        },
        onFinish: () => {
            isSubmittingAlliance.value = false;
        },
    });
}

function acceptAllianceApplication(payload: {
    allianceId: number;
    applicationId: number;
}) {
    router.patch(
        `/alliances/${payload.allianceId}/applications/${payload.applicationId}/accept`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function denyAllianceApplication(payload: {
    allianceId: number;
    applicationId: number;
}) {
    router.delete(
        `/alliances/${payload.allianceId}/applications/${payload.applicationId}`,
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function leaveAlliance(allianceId: number) {
    router.delete(`/alliances/${allianceId}/leave`, {
        preserveScroll: true,
        onStart: () => {
            isSubmittingAlliance.value = true;
        },
        onError: (errors) => {
            if (errors.alliance) {
                toast.error(errors.alliance);
            }
        },
        onFinish: () => {
            isSubmittingAlliance.value = false;
        },
    });
}

function disbandAlliance(allianceId: number) {
    router.delete(`/alliances/${allianceId}`, {
        preserveScroll: true,
        onStart: () => {
            isSubmittingAlliance.value = true;
        },
        onError: (errors) => {
            if (errors.alliance) {
                toast.error(errors.alliance);
            }
        },
        onFinish: () => {
            isSubmittingAlliance.value = false;
        },
    });
}

function promoteAllianceMember(payload: {
    allianceId: number;
    membershipId: number;
}) {
    router.patch(
        `/alliances/${payload.allianceId}/members/${payload.membershipId}/promote`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function demoteAllianceMember(payload: {
    allianceId: number;
    membershipId: number;
}) {
    router.patch(
        `/alliances/${payload.allianceId}/members/${payload.membershipId}/demote`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function transferAllianceLeadership(payload: {
    allianceId: number;
    membershipId: number;
}) {
    router.patch(
        `/alliances/${payload.allianceId}/members/${payload.membershipId}/transfer-leadership`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function kickAllianceMember(payload: {
    allianceId: number;
    membershipId: number;
}) {
    router.delete(
        `/alliances/${payload.allianceId}/members/${payload.membershipId}`,
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance) {
                    toast.error(errors.alliance);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        },
    );
}

function contributeAllianceGoal(payload: {
    goalId: number;
    resource_type: ResourceKey;
    amount: number;
}) {
    router.post(
        `/alliance-goals/${payload.goalId}/contribute`,
        {
            resource_type: payload.resource_type,
            amount: payload.amount,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors) => {
                if (errors.alliance_goal) {
                    toast.error(errors.alliance_goal);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
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
                            <button
                                type="button"
                                class="mt-3 block max-w-full cursor-pointer text-left text-3xl font-bold break-words"
                                :aria-label="`${resource.label}: ${formatExactNumber(resource.amount)}`"
                                :title="
                                    resourceNumberTitle(
                                        `current-${resource.key}`,
                                    )
                                "
                                @click="
                                    toggleResourceNumber(
                                        `current-${resource.key}`,
                                    )
                                "
                            >
                                {{
                                    resourceNumberLabel(
                                        `current-${resource.key}`,
                                        resource.amount,
                                    )
                                }}
                            </button>
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
                                        formatGameNumber(
                                            minigame.currentProduction,
                                        )
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
                                    Stamina
                                </span>
                                <span class="font-semibold">
                                    {{ minigame.stamina.label }}
                                </span>
                            </div>
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
                                    {{ formatGameNumber(minigame.completions) }}
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
                                        formatGameNumber(
                                            minigame.resourcesGained,
                                        )
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
                                activeMinigameResource === minigame.resource ||
                                !minigame.stamina.isAvailable
                            "
                            :aria-label="`Play ${minigame.label}. ${minigameStaminaHoverLabel(minigame)}`"
                            :title="minigameStaminaHoverLabel(minigame)"
                            @click="openMinigame(minigame)"
                        >
                            <Gamepad2 class="h-4 w-4" />
                            {{
                                activeMinigameResource === minigame.resource
                                    ? 'Completing...'
                                    : !minigame.stamina.isAvailable
                                      ? 'Stamina empty'
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
                            {{ formatGameNumber(lifetimeTotalResources) }}
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
                                <button
                                    type="button"
                                    class="max-w-full cursor-pointer text-right text-sm font-semibold break-words text-[#47663b] dark:text-[#9dcc84]"
                                    :aria-label="`${resource.label} lifetime collected: ${formatExactNumber(resource.amount)}`"
                                    :title="
                                        resourceNumberTitle(
                                            `lifetime-${resource.key}`,
                                        )
                                    "
                                    @click="
                                        toggleResourceNumber(
                                            `lifetime-${resource.key}`,
                                        )
                                    "
                                >
                                    {{
                                        resourceNumberLabel(
                                            `lifetime-${resource.key}`,
                                            resource.amount,
                                        )
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <button
                    type="button"
                    data-game-modal-launcher="alliance"
                    class="w-full rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#c9b995] hover:shadow-md dark:border-[#38362f] dark:bg-[#1a1d15] dark:hover:border-[#5a523f]"
                    @click="openAlliance"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div class="rounded-md bg-[#243627] p-2 text-white">
                                <UsersRound class="h-5 w-5" />
                            </div>
                            <div>
                                <p
                                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                                >
                                    Alliance
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    {{
                                        props.alliances.current
                                            ? props.alliances.current.name
                                            : 'Find your alliance'
                                    }}
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    {{
                                        props.alliances.current
                                            ? 'View members, roles, and alliance contribution rankings.'
                                            : 'Create a new alliance or join an open group.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[28rem]">
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Status
                                </p>
                                <p class="mt-2 text-xl font-bold">
                                    {{
                                        props.alliances.current
                                            ? props.alliances.current.isOpen
                                                ? 'Open'
                                                : 'Private'
                                            : 'No alliance'
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Members
                                </p>
                                <p class="mt-2 text-xl font-bold">
                                    {{
                                        props.alliances.current
                                            ? `${formatExactNumber(props.alliances.current.memberCount)} / ${formatExactNumber(props.alliances.current.memberLimit)}`
                                            : formatExactNumber(
                                                  props.alliances.available
                                                      .length,
                                              )
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                            >
                                <p
                                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    Role
                                </p>
                                <p class="mt-2 text-xl font-bold capitalize">
                                    {{
                                        props.alliances.current
                                            ? props.alliances.current
                                                  .currentUserRole
                                            : props.alliances.canCreate
                                              ? 'Founder'
                                              : 'Cooldown'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </button>
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
                                        formatGameNumber(
                                            defaultLeaderboard.currentValue,
                                        )
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
                            {{ formatGameNumber(props.prestigeStats.count) }}
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
                            {{ formatGameNumber(props.roadStats.length) }} km
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
            v-if="
                isBuildingsOpen ||
                isAllianceModalOpen ||
                isLeaderboardModalOpen ||
                isMinigameOpen ||
                isPrestigeConfirmModalOpen ||
                isOfflineProgressModalOpen ||
                isAchievementUnlockModalOpen
            "
            data-game-modal-layer
            class="fixed inset-0 z-[58] flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 px-4 py-6"
            @click.self="closeActiveGameModal"
        >
            <BuildingsModal
                v-if="isBuildingsOpen"
                :buildings="buildings"
                :upgrading-building-id="upgradingBuildingId"
                :road-build-amounts="roadBuildAmounts"
                :max-road-build-amount="MAX_ROAD_BUILD_AMOUNT"
                :upgrade-availability-label-for="upgradeAvailabilityLabelFor"
                :road-buildable-amount-for="roadBuildableAmountFor"
                @close="closeBuildings"
                @upgrade="upgradeBuilding"
                @update-road-amount="updateRoadBuildAmount"
            />
            <AllianceModal
                v-else-if="isAllianceModalOpen"
                :alliances="props.alliances"
                :is-submitting="isSubmittingAlliance"
                @close="closeAlliance"
                @search="searchAlliances"
                @create="createAlliance"
                @join="joinAlliance"
                @apply="applyToAlliance"
                @update-alliance="updateAlliance"
                @accept-application="acceptAllianceApplication"
                @deny-application="denyAllianceApplication"
                @leave="leaveAlliance"
                @disband="disbandAlliance"
                @kick="kickAllianceMember"
                @promote="promoteAllianceMember"
                @demote="demoteAllianceMember"
                @transfer-leadership="transferAllianceLeadership"
                @contribute-goal="contributeAllianceGoal"
            />
            <LeaderboardModal
                v-else-if="isLeaderboardModalOpen && selectedLeaderboard"
                :leaderboards="leaderboards"
                :selected-leaderboard="selectedLeaderboard"
                @close="closeLeaderboard"
                @select-leaderboard="selectedLeaderboardKey = $event"
            />
            <MinigameModal
                v-else-if="
                    isMinigameOpen &&
                    selectedMinigame &&
                    selectedMinigameComponent
                "
                :minigame="selectedMinigame"
                :minigame-component="selectedMinigameComponent"
                :active-minigame-resource="activeMinigameResource"
                :has-won="hasWonMinigame"
                :current-resource-amount="selectedMinigameResourceAmount"
                :show-leaderboard-button="true"
                :resource-number-label="resourceNumberLabel"
                :resource-number-title="resourceNumberTitle"
                @close="closeMinigame"
                @complete="completeSelectedMinigame"
                @continue="continueMinigame"
                @open-leaderboard="openLeaderboard()"
                @toggle-resource-number="toggleResourceNumber"
            />
            <PrestigeConfirmModal
                v-else-if="isPrestigeConfirmModalOpen"
                :requirement-label="prestigeRequirementLabel"
                :current-road-length="props.roadStats.length"
                :prestige-count-after-reset="props.prestigeStats.count + 1"
                :is-prestiging="isPrestiging"
                @close="closePrestigeConfirm"
                @confirm="confirmPrestige"
            />
            <OfflineProgressModal
                v-else-if="isOfflineProgressModalOpen && props.offlineProgress"
                :offline-progress="props.offlineProgress"
                :duration-label="offlineProgressDurationLabel"
                :resource-rows="offlineProgressResourceRows"
                @close="closeOfflineProgress"
            />
            <AchievementUnlockModal
                v-else-if="
                    isAchievementUnlockModalOpen && currentAchievementUnlock
                "
                :achievement-unlock="currentAchievementUnlock"
                :position="achievementUnlockPosition"
                :count="achievementUnlockCount"
                :button-label="achievementUnlockButtonLabel"
                @advance="advanceAchievementUnlockPopup"
            />
        </div>
    </Teleport>
</template>
