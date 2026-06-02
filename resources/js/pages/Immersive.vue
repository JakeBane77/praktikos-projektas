<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Award,
    Bell,
    CheckCircle2,
    Coins,
    Gamepad2,
    Hammer,
    Lock,
    Mountain,
    Pause,
    Play,
    RotateCcw,
    Sparkles,
    TreePine,
    Trophy,
    Wheat,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import FoodMinigame from '@/components/minigames/FoodMinigame.vue';
import GoldMinigame from '@/components/minigames/GoldMinigame.vue';
import StoneMinigame from '@/components/minigames/StoneMinigame.vue';
import WoodMinigame from '@/components/minigames/WoodMinigame.vue';
import { useImmersiveTestingPanel } from '@/composables/useImmersiveTestingPanel';
import type {
    AchievementUnlock,
    Building,
    DashboardGameData,
    Leaderboard,
    Minigame,
    ResourceKey,
} from '@/lib/game';
import { getUserSystemTime } from '@/lib/userSystemTime';
import type { UserSystemTime } from '@/lib/userSystemTime';
import {
    weatherConditionLabel,
    weatherConditionsForCode,
    weatherIconFor,
} from '@/lib/weather';
import { immersive } from '@/routes';

const backgroundAssets = import.meta.glob<string>(
    './assets/backgrounds/*.png',
    {
        eager: true,
        import: 'default',
        query: '?url',
    },
);
const cloudAssets = import.meta.glob<string>(
    './assets/backgrounds/clouds/*.png',
    {
        eager: true,
        import: 'default',
        query: '?url',
    },
);
const fallbackAssets = import.meta.glob<string>('./assets/*.png', {
    eager: true,
    import: 'default',
    query: '?url',
});
const emptyAsset =
    fallbackAssets['./assets/empty.png'] ??
    fallbackAssets['./assets/Empty.png'] ??
    '';

const celestialPath = {
    leftStart: -8,
    leftEnd: 108,
    topBase: 30,
    topArcHeight: 28,
    topMin: 8,
    topMax: 56,
};

const cloudsBox = {
    left: 0,
    top: 0,
    width: 100,
    height: 30,
};

const collectButtonPosition = {
    left: 44,
    top: 74,
};

const resourcesButtonPosition = {
    left: 41,
    top: 74,
};

const upgradesButtonPosition = {
    left: 14,
    top: 80,
};

const prestigeButtonPosition = {
    left: 42.5,
    top: 68,
};

const leaderboardButtonPosition = {
    right: 1,
    bottom: 5,
};

const achievementsButtonPosition = {
    right: 1,
    bottom: 9,
};

const actionButtonPosition = {
    right: 1,
    bottom: 1,
};

const minigameButtonPositions: Record<
    ResourceKey,
    {
        left: number;
        top: number;
    }
> = {
    wood: {
        left: 86,
        top: 76,
    },
    food: {
        left: 56,
        top: 80,
    },
    stone: {
        left: 84,
        top: 50,
    },
    gold: {
        left: 88,
        top: 50,
    },
};

const MAX_ROAD_BUILD_AMOUNT = 10_000_000;

const resourceRows = [
    { key: 'wood', label: 'Wood' },
    { key: 'food', label: 'Food' },
    { key: 'stone', label: 'Stone' },
    { key: 'gold', label: 'Gold' },
] as const;

type CloudPlacement = {
    id: string;
    file: string;
    left: number;
    top: number;
    width: number;
    drift: number;
    duration: number;
    delay: number;
};

type WeatherParticle = {
    id: number;
    left: number;
    delay: number;
    duration: number;
    opacity: number;
    size: number;
};

const dayClouds: CloudPlacement[] = [
    {
        id: 'day-top-left',
        file: 'day_cloud_01_small_top.png',
        left: 9,
        top: 20,
        width: 16,
        drift: 2.8,
        duration: 36,
        delay: -6,
    },
    {
        id: 'day-top-mid',
        file: 'day_cloud_01_small_top.png',
        left: 33,
        top: 13,
        width: 14,
        drift: 2.2,
        duration: 42,
        delay: -18,
    },
    {
        id: 'day-top-right',
        file: 'day_cloud_03_tiny_right.png',
        left: 82,
        top: 15,
        width: 12,
        drift: 2,
        duration: 34,
        delay: -11,
    },
    {
        id: 'day-mid-left',
        file: 'day_cloud_02_large_right.png',
        left: -2,
        top: 47,
        width: 26,
        drift: 3.4,
        duration: 52,
        delay: -22,
    },
    {
        id: 'day-mid-right',
        file: 'day_cloud_02_large_right.png',
        left: 61,
        top: 45,
        width: 30,
        drift: 3.1,
        duration: 58,
        delay: -31,
    },
    {
        id: 'day-low-mid',
        file: 'day_cloud_01_small_top.png',
        left: 39,
        top: 74,
        width: 17,
        drift: 2.4,
        duration: 44,
        delay: -9,
    },
    {
        id: 'day-low-right',
        file: 'day_cloud_03_tiny_right.png',
        left: 92,
        top: 70,
        width: 11,
        drift: 1.8,
        duration: 38,
        delay: -25,
    },
];

const nightClouds: CloudPlacement[] = [
    {
        id: 'night-top-left',
        file: 'night_cloud_01_small_right.png',
        left: 12,
        top: 18,
        width: 14,
        drift: 2,
        duration: 48,
        delay: -17,
    },
    {
        id: 'night-top-right',
        file: 'night_cloud_01_small_right.png',
        left: 77,
        top: 17,
        width: 15,
        drift: 2.1,
        duration: 44,
        delay: -8,
    },
    {
        id: 'night-mid-left',
        file: 'night_cloud_02_right_lower.png',
        left: 2,
        top: 55,
        width: 25,
        drift: 2.8,
        duration: 62,
        delay: -28,
    },
    {
        id: 'night-mid-right',
        file: 'night_cloud_02_right_lower.png',
        left: 58,
        top: 56,
        width: 28,
        drift: 2.6,
        duration: 66,
        delay: -36,
    },
    {
        id: 'night-low-center',
        file: 'night_cloud_01_small_right.png',
        left: 37,
        top: 77,
        width: 13,
        drift: 1.7,
        duration: 50,
        delay: -14,
    },
];

const rainDrops: WeatherParticle[] = Array.from({ length: 90 }, (_, index) => ({
    id: index,
    left: (index * 37) % 100,
    delay: -((index * 0.17) % 1.8),
    duration: 0.55 + ((index * 13) % 22) / 100,
    opacity: 0.35 + ((index * 7) % 45) / 100,
    size: 0.75 + ((index * 5) % 30) / 100,
}));

const snowFlakes: WeatherParticle[] = Array.from(
    { length: 70 },
    (_, index) => ({
        id: index,
        left: (index * 41) % 100,
        delay: -((index * 0.31) % 8),
        duration: 7 + ((index * 11) % 50) / 10,
        opacity: 0.35 + ((index * 9) % 45) / 100,
        size: 0.45 + ((index * 7) % 55) / 100,
    }),
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Immersive mode',
                href: immersive(),
            },
        ],
    },
});

const props = defineProps<DashboardGameData>();

const userTime = ref<UserSystemTime>(getUserSystemTime());
const timeOverride = ref('');
const settlementStageOverride = ref('');
const weatherCodeOverride = ref('');
const isActionMenuOpen = ref(false);
const isResourcesMenuOpen = ref(false);
const isUpgradesMenuOpen = ref(false);
const isPrestigeMenuOpen = ref(false);
const activeGameModal = ref<'achievements' | 'leaderboard' | 'minigame' | null>(
    null,
);
const showOnlyAvailableUpgrades = ref(true);
const hideCompletedAchievements = ref(true);
const achievementUnlockQueue = ref<AchievementUnlock[]>([]);
const activeAchievementUnlockIndex = ref(0);
const isCollecting = ref(false);
const isPrestiging = ref(false);
const upgradingBuildingId = ref<number | null>(null);
const roadBuildAmounts = ref<Record<number, number>>({});
const activeMinigameResource = ref<ResourceKey | null>(null);
const selectedMinigameResource = ref<ResourceKey | null>(null);
const hasWonMinigame = ref(false);
const selectedLeaderboardKey = ref(props.leaderboards.defaultKey);
const isTimeLooping = ref(false);
const timeLoopSpeedMs = ref(500);
let userTimeInterval: number | undefined;
let timeLoopInterval: number | undefined;
const { isImmersiveTestingPanelOpen } = useImmersiveTestingPanel();

const displayedWeatherCode = computed(() => {
    if (weatherCodeOverride.value === '') {
        return props.weather.weatherCode;
    }

    const weatherCode = Number(weatherCodeOverride.value);

    return Number.isInteger(weatherCode) ? weatherCode : null;
});
const displayedWeatherConditions = computed(() =>
    weatherCodeOverride.value === ''
        ? props.weather.conditions
        : weatherConditionsForCode(displayedWeatherCode.value),
);
const weatherLabel = computed(() =>
    weatherConditionLabel(displayedWeatherConditions.value),
);
const WeatherIcon = computed(() =>
    weatherIconFor(
        displayedWeatherCode.value,
        displayedWeatherConditions.value,
    ),
);

const displayedTime = computed(() =>
    timeOverride.value
        ? getUserSystemTime(timeFromInputValue(timeOverride.value))
        : userTime.value,
);

const buildingLevels = computed(() => {
    const levelByName = new Map<string, number>();

    props.buildings.forEach((building) => {
        levelByName.set(building.name.toLowerCase(), building.level);
    });

    return {
        lumbercamp: levelByName.get('lumbercamp') ?? 0,
        farm: levelByName.get('farm') ?? 0,
        quarry: levelByName.get('quarry') ?? 0,
        mine: levelByName.get('mine') ?? 0,
        road: props.roadStats.length,
    };
});

const settlementStage = computed(() =>
    settlementStageForLevel(
        Math.max(
            buildingLevels.value.lumbercamp,
            buildingLevels.value.farm,
            buildingLevels.value.quarry,
            buildingLevels.value.mine,
        ),
    ),
);

const displayedSettlementStage = computed(() => {
    if (settlementStageOverride.value === '') {
        return settlementStage.value;
    }

    return clamp(Number(settlementStageOverride.value) || 0, 0, 6);
});

const upgradeReadyBuildings = computed(() =>
    props.buildings.filter((building) => building.canUpgrade),
);

const actionUpgradeReadyBuildings = computed(() =>
    upgradeReadyBuildings.value.filter((building) => !building.isRoad),
);

const hasUpgradeReady = computed(() => upgradeReadyBuildings.value.length > 0);
const hasActionUpgradeReady = computed(
    () => actionUpgradeReadyBuildings.value.length > 0,
);

const visibleUpgradeBuildings = computed(() =>
    showOnlyAvailableUpgrades.value
        ? upgradeReadyBuildings.value
        : props.buildings.filter((building) => !building.isMaxLevel),
);

const hasVisibleUpgradeBuildings = computed(
    () => visibleUpgradeBuildings.value.length > 0,
);

const minigames = computed<Minigame[]>(() => props.minigames);
const leaderboards = computed<Leaderboard[]>(() => props.leaderboards.boards);
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

const resourceIcons = {
    gold: Coins,
    wood: TreePine,
    stone: Mountain,
    food: Wheat,
};

const minigameComponents = {
    gold: GoldMinigame,
    wood: WoodMinigame,
    stone: StoneMinigame,
    food: FoodMinigame,
};

const selectedMinigame = computed<Minigame | null>(() =>
    selectedMinigameResource.value
        ? (minigames.value.find(
              (minigame) =>
                  minigame.resource === selectedMinigameResource.value,
          ) ?? null)
        : null,
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

const isMinigameOpen = computed(
    () =>
        activeGameModal.value === 'minigame' && selectedMinigame.value !== null,
);

const isLeaderboardModalOpen = computed(
    () =>
        activeGameModal.value === 'leaderboard' &&
        selectedLeaderboard.value !== null,
);

const isAchievementsModalOpen = computed(
    () => activeGameModal.value === 'achievements',
);
const isAchievementUnlockModalOpen = computed(
    () =>
        Boolean(currentAchievementUnlock.value) &&
        !isAchievementsModalOpen.value &&
        !isLeaderboardModalOpen.value &&
        !isMinigameOpen.value,
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

const prestigeButtonLabel = computed(() =>
    props.prestigeStats.canPrestige
        ? 'Prestige ready'
        : `${prestigeProgressPercent.value}% toward prestige`,
);

const leaderboardButtonLabel = computed(() =>
    defaultLeaderboard.value
        ? `Leaderboard rank #${defaultLeaderboard.value.currentRank.toLocaleString()}`
        : 'Leaderboard',
);

const iconButtonReadyClass =
    'border-[#e0b461]/70 bg-[#3a2a10]/90 text-[#fff0c8] shadow-[0_0_30px_rgb(224_180_97/0.34)] hover:bg-[#4b3613]/90';
const iconButtonIdleClass =
    'border-white/15 bg-black/45 text-[#d8dccf] shadow-[0_0_18px_rgb(0_0_0/0.24)] hover:bg-black/60';
const iconButtonMenuClass =
    'border-[#8fd8ff]/55 bg-[#12313d]/85 text-[#d9f5ff] shadow-[0_0_24px_rgb(83_186_219/0.24)] hover:bg-[#173f4e]/90';
const iconButtonMinigameClass =
    'border-[#ff9a9a]/55 bg-[#4a1717]/82 text-[#ffe1e1] shadow-[0_0_24px_rgb(255_112_112/0.24)] hover:bg-[#5b2020]/90';

const actionButtonClass = computed(() => {
    if (props.prestigeStats.canPrestige) {
        return iconButtonReadyClass;
    }

    if (props.canCollect && hasActionUpgradeReady.value) {
        return iconButtonReadyClass;
    }

    if (props.canCollect) {
        return iconButtonReadyClass;
    }

    if (hasActionUpgradeReady.value) {
        return iconButtonReadyClass;
    }

    return iconButtonIdleClass;
});

const actionButtonLabel = computed(() => {
    if (props.prestigeStats.canPrestige) {
        return props.canCollect || hasActionUpgradeReady.value
            ? 'Prestige and actions ready'
            : 'Prestige ready';
    }

    if (props.canCollect && hasActionUpgradeReady.value) {
        return 'Collection and upgrades ready';
    }

    if (props.canCollect) {
        return 'Collection ready';
    }

    if (hasActionUpgradeReady.value) {
        return 'Building upgrades ready';
    }

    return 'Kingdom actions';
});

const collectButtonClass = computed(() => {
    if (props.canCollect) {
        return iconButtonReadyClass;
    }

    return iconButtonIdleClass;
});

const collectButtonStyle = computed(() => ({
    left: `${collectButtonPosition.left}%`,
    top: `${collectButtonPosition.top}%`,
}));

const resourcesButtonStyle = computed(() => ({
    left: `${resourcesButtonPosition.left}%`,
    top: `${resourcesButtonPosition.top}%`,
}));

const upgradesButtonStyle = computed(() => ({
    left: `${upgradesButtonPosition.left}%`,
    top: `${upgradesButtonPosition.top}%`,
}));

const prestigeButtonStyle = computed(() => ({
    left: `${prestigeButtonPosition.left}%`,
    top: `${prestigeButtonPosition.top}%`,
}));

const leaderboardButtonStyle = computed(() => ({
    right: `${leaderboardButtonPosition.right}rem`,
    bottom: `${leaderboardButtonPosition.bottom}rem`,
}));

const achievementsButtonStyle = computed(() => ({
    right: `${achievementsButtonPosition.right}rem`,
    bottom: `${achievementsButtonPosition.bottom}rem`,
}));

const actionButtonStyle = computed(() => ({
    right: `${actionButtonPosition.right}rem`,
    bottom: `${actionButtonPosition.bottom}rem`,
}));

const actionMenuStyle = computed(() => ({
    right: `${actionButtonPosition.right}rem`,
    bottom: `calc(${actionButtonPosition.bottom}rem + 3.5rem)`,
}));

const upgradesButtonClass = computed(() => {
    if (hasUpgradeReady.value) {
        return iconButtonReadyClass;
    }

    return iconButtonIdleClass;
});

const prestigeButtonClass = computed(() => {
    if (props.prestigeStats.canPrestige) {
        return iconButtonReadyClass;
    }

    return iconButtonIdleClass;
});

const leaderboardButtonClass = iconButtonMenuClass;
const achievementsButtonClass = iconButtonMenuClass;

const achievementsButtonLabel = computed(
    () =>
        `${unlockedAchievementCount.value.toLocaleString()} of ${achievements.value.length.toLocaleString()} achievements unlocked`,
);

const upgradesButtonLabel = computed(() =>
    hasUpgradeReady.value
        ? `${upgradeReadyBuildings.value.length} building upgrade${upgradeReadyBuildings.value.length === 1 ? '' : 's'} ready`
        : 'No building upgrades ready',
);

const collectButtonLabel = computed(() => {
    if (isCollecting.value) {
        return 'Collecting...';
    }

    if (props.canCollect) {
        return 'Ready to collect';
    }

    return `Collect on cooldown ${collectCooldownLabel.value}`;
});

const collectCooldownLabel = computed(() => {
    const nextMidnight = new Date(userTime.value.date);

    nextMidnight.setDate(nextMidnight.getDate() + 1);
    nextMidnight.setHours(0, 0, 0, 0);

    const remainingMilliseconds = Math.max(
        0,
        nextMidnight.getTime() - userTime.value.timestamp,
    );
    const totalMinutes = Math.ceil(remainingMilliseconds / 60_000);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
});

const roadLabel = computed(() => {
    const roads = buildingLevels.value.road;

    if (roads > 0) {
        return `${compactNumber(roads)} km built`;
    }

    return 'No roads built';
});

const minigameButtons = computed(() =>
    minigames.value.map((minigame) => ({
        ...minigame,
        icon: resourceIcons[minigame.resource],
        style: {
            left: `${minigameButtonPositions[minigame.resource].left}%`,
            top: `${minigameButtonPositions[minigame.resource].top}%`,
        },
    })),
);

const skyState = computed(() => {
    const hour = displayedTime.value.hourDecimal;

    if (hour >= 5 && hour < 8) {
        return 'dawn';
    }

    if (hour >= 8 && hour < 17) {
        return 'day';
    }

    if (hour >= 17 && hour < 20) {
        return 'dusk';
    }

    return 'night';
});

const isSunVisible = computed(
    () =>
        displayedTime.value.hourDecimal >= 6 &&
        displayedTime.value.hourDecimal < 18,
);

const activeBackgroundAsset = computed(() =>
    stagedBackgroundAsset(
        isSunVisible.value ? 'day' : 'night',
        displayedSettlementStage.value,
    ),
);

const isClearWeather = computed(() => displayedWeatherConditions.value.clear);
const isRainVisible = computed(
    () =>
        displayedWeatherConditions.value.raining ||
        displayedWeatherConditions.value.thunderstorm,
);
const isThunderstormVisible = computed(
    () => displayedWeatherConditions.value.thunderstorm,
);
const isSnowVisible = computed(() => displayedWeatherConditions.value.snowing);
const isFogVisible = computed(() => displayedWeatherConditions.value.foggy);

const activeClouds = computed(() =>
    isClearWeather.value ? [] : isSunVisible.value ? dayClouds : nightClouds,
);

const activeCelestialAsset = computed(() =>
    backgroundAsset(isSunVisible.value ? 'sun.png' : 'moon.png'),
);

const activeCelestialKey = computed(() =>
    isSunVisible.value ? 'sun' : 'moon',
);

const celestialPosition = computed(() => {
    const hour = displayedTime.value.hourDecimal;
    const progress = isSunVisible.value
        ? (hour - 6) / 12
        : hour >= 18
          ? (hour - 18) / 12
          : (hour + 6) / 12;

    return {
        left: `${clamp(
            celestialPath.leftStart +
                progress * (celestialPath.leftEnd - celestialPath.leftStart),
            celestialPath.leftStart,
            celestialPath.leftEnd,
        )}%`,
        top: `${clamp(
            celestialPath.topBase -
                Math.sin(progress * Math.PI) * celestialPath.topArcHeight,
            celestialPath.topMin,
            celestialPath.topMax,
        )}%`,
    };
});

const celestialPathGuideStyle = computed(() => ({
    left: `${celestialPath.leftStart}%`,
    top: `${celestialPath.topBase}%`,
    width: `${celestialPath.leftEnd - celestialPath.leftStart}%`,
}));

const cloudsBoxStyle = computed(() => ({
    left: `${cloudsBox.left}%`,
    top: `${cloudsBox.top}%`,
    width: `${cloudsBox.width}%`,
    height: `${cloudsBox.height}%`,
}));

const sceneClass = computed(() => ({
    'immersive-scene-dawn': skyState.value === 'dawn',
    'immersive-scene-dusk': skyState.value === 'dusk',
    'immersive-scene-night': skyState.value === 'night',
    'immersive-weather-clear': displayedWeatherConditions.value.clear,
    'immersive-weather-cloudy': displayedWeatherConditions.value.cloudy,
    'immersive-weather-raining': displayedWeatherConditions.value.raining,
    'immersive-weather-foggy': displayedWeatherConditions.value.foggy,
    'immersive-weather-thunderstorm':
        displayedWeatherConditions.value.thunderstorm,
    'immersive-weather-snowing': displayedWeatherConditions.value.snowing,
}));

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

onMounted(() => {
    userTimeInterval = window.setInterval(() => {
        userTime.value = getUserSystemTime();
    }, 30_000);
});

onBeforeUnmount(() => {
    if (userTimeInterval !== undefined) {
        window.clearInterval(userTimeInterval);
    }

    stopTimeLoop();
});

watch(timeLoopSpeedMs, () => {
    if (isTimeLooping.value) {
        startTimeLoop();
    }
});

function clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(maximum, Math.max(minimum, value));
}

function compactNumber(value: number): string {
    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en').format(value);
}

function minigameButtonClass(): string {
    return iconButtonMinigameClass;
}

function settlementStageForLevel(level: number): number {
    if (level >= 25) {
        return 6;
    }

    if (level >= 20) {
        return 5;
    }

    if (level >= 15) {
        return 4;
    }

    if (level >= 10) {
        return 3;
    }

    if (level >= 5) {
        return 2;
    }

    if (level >= 1) {
        return 1;
    }

    return 0;
}

function backgroundAsset(filename: string): string {
    return backgroundAssets[`./assets/backgrounds/${filename}`] ?? emptyAsset;
}

function stagedBackgroundAsset(period: 'day' | 'night', stage: number): string {
    for (let currentStage = stage; currentStage >= 0; currentStage -= 1) {
        const filename = `${period}_background_${String(currentStage).padStart(2, '0')}.png`;
        const asset = backgroundAssets[`./assets/backgrounds/${filename}`];

        if (asset) {
            return asset;
        }
    }

    return emptyAsset;
}

function cloudAsset(filename: string): string {
    return cloudAssets[`./assets/backgrounds/clouds/${filename}`] ?? emptyAsset;
}

function cloudStyle(cloud: CloudPlacement): Record<string, string> {
    return {
        left: `${cloud.left}%`,
        top: `${cloud.top}%`,
        width: `${cloud.width * 0.3}%`,
        '--cloud-drift': `${cloud.drift}%`,
        '--cloud-duration': `${cloud.duration}s`,
        '--cloud-delay': `${cloud.delay}s`,
    };
}

function rainDropStyle(drop: WeatherParticle): Record<string, string> {
    return {
        left: `${drop.left}%`,
        opacity: String(drop.opacity),
        '--rain-scale': String(drop.size),
        '--rain-delay': `${drop.delay}s`,
        '--rain-duration': `${drop.duration}s`,
    };
}

function snowFlakeStyle(flake: WeatherParticle): Record<string, string> {
    return {
        left: `${flake.left}%`,
        opacity: String(flake.opacity),
        width: `${flake.size}rem`,
        height: `${flake.size}rem`,
        '--snow-delay': `${flake.delay}s`,
        '--snow-duration': `${flake.duration}s`,
        '--snow-drift': `${((flake.id * 17) % 28) - 14}vw`,
    };
}

function useEmptyAsset(event: Event): void {
    const image = event.target;

    if (!(image instanceof HTMLImageElement) || !emptyAsset) {
        return;
    }

    if (image.src !== emptyAsset) {
        image.src = emptyAsset;
    }
}

function collectResources(): void {
    if (!props.canCollect || isCollecting.value) {
        return;
    }

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

    if (!Number.isFinite(amount)) {
        return 1;
    }

    return Math.min(MAX_ROAD_BUILD_AMOUNT, Math.max(1, Math.floor(amount)));
}

function upgradeBuilding(building: Building): void {
    if (!building.canUpgrade || upgradingBuildingId.value !== null) {
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

function togglePrestigeMenu(): void {
    isPrestigeMenuOpen.value = !isPrestigeMenuOpen.value;
    activeGameModal.value = null;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    isUpgradesMenuOpen.value = false;
}

function openLeaderboard(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    if (defaultLeaderboard.value) {
        selectedLeaderboardKey.value = defaultLeaderboard.value.key;
    }

    isPrestigeMenuOpen.value = false;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    isUpgradesMenuOpen.value = false;
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'leaderboard';
}

function openAchievements(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isPrestigeMenuOpen.value = false;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    isUpgradesMenuOpen.value = false;
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'achievements';
}

function closeLeaderboard(): void {
    if (activeGameModal.value === 'leaderboard') {
        activeGameModal.value = null;
    }
}

function closeAchievements(): void {
    if (activeGameModal.value === 'achievements') {
        activeGameModal.value = null;
    }
}

function closeActiveGameModal(): void {
    if (activeGameModal.value === 'achievements') {
        closeAchievements();

        return;
    }

    if (activeGameModal.value === 'leaderboard') {
        closeLeaderboard();

        return;
    }

    closeMinigame();
}

function confirmPrestige(): void {
    if (!props.prestigeStats.canPrestige || isPrestiging.value) {
        return;
    }

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
                isPrestigeMenuOpen.value = false;
            },
        },
    );
}

function completeMinigame(minigame: Minigame): void {
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

function openMinigame(minigame: Minigame): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    isUpgradesMenuOpen.value = false;
    isPrestigeMenuOpen.value = false;
    selectedMinigameResource.value = minigame.resource;
    hasWonMinigame.value = false;
    activeGameModal.value = 'minigame';
}

function closeMinigame(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    if (activeGameModal.value === 'minigame') {
        activeGameModal.value = null;
    }
}

function continueMinigame(): void {
    hasWonMinigame.value = false;
}

function completeSelectedMinigame(): void {
    if (!selectedMinigame.value) {
        return;
    }

    completeMinigame(selectedMinigame.value);
}

function advanceAchievementUnlockPopup(): void {
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

function incrementTestTime(minutes: number): void {
    const baseDate = timeOverride.value
        ? timeFromInputValue(timeOverride.value)
        : displayedTime.value.date;
    const nextDate = new Date(baseDate);

    nextDate.setMinutes(nextDate.getMinutes() + minutes);
    timeOverride.value = `${String(nextDate.getHours()).padStart(2, '0')}:${String(nextDate.getMinutes()).padStart(2, '0')}`;
}

function toggleTimeLoop(): void {
    if (isTimeLooping.value) {
        stopTimeLoop();

        return;
    }

    isTimeLooping.value = true;
    startTimeLoop();
}

function startTimeLoop(): void {
    stopTimeLoop(false);

    timeLoopInterval = window.setInterval(() => {
        incrementTestTime(1);
    }, normalizedLoopSpeedMs());
}

function stopTimeLoop(updateState = true): void {
    if (timeLoopInterval !== undefined) {
        window.clearInterval(timeLoopInterval);
        timeLoopInterval = undefined;
    }

    if (updateState) {
        isTimeLooping.value = false;
    }
}

function normalizedLoopSpeedMs(): number {
    return clamp(Number(timeLoopSpeedMs.value) || 500, 50, 60_000);
}

function timeFromInputValue(value: string): Date {
    const [hours, minutes] = value.split(':').map(Number);
    const date = new Date(userTime.value.date);

    date.setHours(Number.isFinite(hours) ? hours : 0);
    date.setMinutes(Number.isFinite(minutes) ? minutes : 0);
    date.setSeconds(0);
    date.setMilliseconds(0);

    return date;
}
</script>

<template>
    <Head title="Immersive mode" />

    <main class="min-h-full bg-[#071015] text-[#f3efe4]">
        <section
            class="relative min-h-[calc(100vh-4rem)] overflow-hidden"
            :class="sceneClass"
        >
            <img
                :src="activeBackgroundAsset"
                alt=""
                class="immersive-background"
                draggable="false"
                @error="useEmptyAsset"
            />

            <div
                class="celestial-path-guide"
                :style="celestialPathGuideStyle"
            ></div>

            <img
                :key="activeCelestialKey"
                :src="activeCelestialAsset"
                alt=""
                class="immersive-celestial"
                :style="celestialPosition"
                draggable="false"
                @error="useEmptyAsset"
            />

            <div class="immersive-cloud-box" :style="cloudsBoxStyle">
                <img
                    v-for="cloud in activeClouds"
                    :key="cloud.id"
                    :src="cloudAsset(cloud.file)"
                    alt=""
                    class="immersive-cloud"
                    :style="cloudStyle(cloud)"
                    draggable="false"
                    @error="useEmptyAsset"
                />
            </div>

            <div
                v-if="isFogVisible"
                class="immersive-weather-layer immersive-fog-layer"
            >
                <div class="immersive-fog immersive-fog-a"></div>
                <div class="immersive-fog immersive-fog-b"></div>
            </div>

            <div
                v-if="isRainVisible"
                class="immersive-weather-layer immersive-rain-layer"
            >
                <span
                    v-for="drop in rainDrops"
                    :key="drop.id"
                    class="immersive-rain-drop"
                    :style="rainDropStyle(drop)"
                ></span>
            </div>

            <div
                v-if="isThunderstormVisible"
                class="immersive-weather-layer immersive-lightning-layer"
            >
                <div class="immersive-lightning immersive-lightning-a"></div>
                <div class="immersive-lightning immersive-lightning-b"></div>
            </div>

            <div
                v-if="isSnowVisible"
                class="immersive-weather-layer immersive-snow-layer"
            >
                <span
                    v-for="flake in snowFlakes"
                    :key="flake.id"
                    class="immersive-snow-flake"
                    :style="snowFlakeStyle(flake)"
                ></span>
            </div>

            <button
                type="button"
                class="absolute z-20 inline-flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border transition hover:scale-105 disabled:cursor-not-allowed disabled:hover:scale-100"
                :class="collectButtonClass"
                :style="collectButtonStyle"
                :disabled="!props.canCollect || isCollecting"
                :aria-label="collectButtonLabel"
                :title="collectButtonLabel"
                @click="collectResources"
            >
                <Sparkles class="h-5 w-5" />
            </button>

            <div
                class="absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="resourcesButtonStyle"
            >
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="iconButtonMenuClass"
                    aria-label="Show resources and production"
                    title="Resources and production"
                    @click="isResourcesMenuOpen = !isResourcesMenuOpen"
                >
                    <Coins class="h-5 w-5" />
                </button>

                <div
                    v-if="isResourcesMenuOpen"
                    class="absolute bottom-14 left-1/2 w-[min(20rem,calc(100vw-2rem))] -translate-x-1/2 rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/92 dark:text-[#f3efe4]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Resources
                            </p>
                            <h2 class="mt-1 text-lg font-bold">
                                Current output
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            aria-label="Close resources menu"
                            @click="isResourcesMenuOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <div
                            v-for="resource in resourceRows"
                            :key="resource.key"
                            class="grid grid-cols-[1fr_auto] gap-3 rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{ resource.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-[#696250] dark:text-[#b8c2b0]"
                                >
                                    +{{
                                        formatNumber(
                                            props.resourceRates[resource.key],
                                        )
                                    }}/hour
                                </p>
                            </div>
                            <p class="self-center text-base font-bold">
                                {{
                                    formatNumber(props.resources[resource.key])
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="upgradesButtonStyle"
            >
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="upgradesButtonClass"
                    :aria-label="upgradesButtonLabel"
                    :title="upgradesButtonLabel"
                    @click="isUpgradesMenuOpen = !isUpgradesMenuOpen"
                >
                    <Hammer class="h-5 w-5" />
                </button>

                <div
                    v-if="isUpgradesMenuOpen"
                    class="absolute bottom-14 left-1/2 w-[min(22rem,calc(100vw-2rem))] -translate-x-1/2 rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/92 dark:text-[#f3efe4]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Buildings
                            </p>
                            <h2 class="mt-1 text-lg font-bold">
                                Upgrade status
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            aria-label="Close upgrades menu"
                            @click="isUpgradesMenuOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <label
                        class="mt-4 flex items-center justify-between gap-3 rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 text-sm font-semibold dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <span>Show only available</span>
                        <input
                            v-model="showOnlyAvailableUpgrades"
                            type="checkbox"
                            class="h-4 w-4 accent-[#243627]"
                        />
                    </label>

                    <div class="mt-4 grid gap-2">
                        <div
                            v-if="!hasVisibleUpgradeBuildings"
                            class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 text-[#696250] dark:border-white/10 dark:bg-white/[0.04] dark:text-[#b8c2b0]"
                        >
                            {{
                                showOnlyAvailableUpgrades
                                    ? 'No building upgrades are currently affordable.'
                                    : 'No further building upgrades are available.'
                            }}
                        </div>

                        <div
                            v-for="building in visibleUpgradeBuildings"
                            v-else
                            :key="building.id"
                            class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">
                                        {{ building.name }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-[#696250] dark:text-[#b8c2b0]"
                                    >
                                        {{ building.levelLabel }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded border border-[#b99145]/35 bg-[#ead9b6] px-3 py-1.5 text-xs font-semibold text-[#5a4320] transition hover:bg-[#dfc996] disabled:cursor-not-allowed disabled:opacity-55 dark:border-[#e0b461]/25 dark:bg-[#e0b461]/10 dark:text-[#fff0c8] dark:hover:bg-[#e0b461]/20"
                                    :disabled="
                                        upgradingBuildingId !== null ||
                                        !building.canUpgrade
                                    "
                                    @click="upgradeBuilding(building)"
                                >
                                    {{
                                        upgradingBuildingId === building.id
                                            ? 'Upgrading...'
                                            : building.canUpgrade
                                              ? 'Upgrade'
                                              : 'Unavailable'
                                    }}
                                </button>
                            </div>
                            <p
                                class="mt-2 text-xs text-[#696250] dark:text-[#b8c2b0]"
                            >
                                {{ building.upgradeCost }}
                            </p>
                            <label
                                v-if="building.isRoad"
                                class="mt-3 flex items-center gap-2 text-xs font-semibold text-[#696250] dark:text-[#b8c2b0]"
                            >
                                Build km
                                <input
                                    v-model.number="
                                        roadBuildAmounts[building.id]
                                    "
                                    type="number"
                                    min="1"
                                    :max="MAX_ROAD_BUILD_AMOUNT"
                                    step="1"
                                    class="h-9 w-32 rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 text-sm text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-[#4a4438] dark:bg-[#12140f] dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                                    placeholder="1"
                                />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <button
                v-for="minigame in minigameButtons"
                :key="minigame.resource"
                type="button"
                class="absolute z-20 inline-flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border transition hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                :class="minigameButtonClass()"
                :style="minigame.style"
                :disabled="activeMinigameResource !== null"
                :aria-label="`Play ${minigame.label}`"
                :title="`Play ${minigame.label}`"
                @click="openMinigame(minigame)"
            >
                <component :is="minigame.icon" class="h-5 w-5" />
            </button>

            <div
                class="absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="prestigeButtonStyle"
            >
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="prestigeButtonClass"
                    :aria-label="prestigeButtonLabel"
                    :title="prestigeButtonLabel"
                    @click="togglePrestigeMenu"
                >
                    <RotateCcw class="h-5 w-5" />
                </button>

                <div
                    v-if="isPrestigeMenuOpen"
                    class="absolute bottom-14 left-1/2 w-[min(22rem,calc(100vw-2rem))] -translate-x-1/2 rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/92 dark:text-[#f3efe4]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Prestige
                            </p>
                            <h2 class="mt-1 text-lg font-bold">
                                Reset progress
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            aria-label="Close prestige menu"
                            @click="isPrestigeMenuOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div
                            class="grid grid-cols-2 gap-3 rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                        >
                            <div>
                                <p
                                    class="text-xs text-[#696250] dark:text-[#b8c2b0]"
                                >
                                    Current roads
                                </p>
                                <p class="mt-1 font-bold">
                                    {{ formatNumber(props.roadStats.length) }}
                                    km
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs text-[#696250] dark:text-[#b8c2b0]"
                                >
                                    Required
                                </p>
                                <p class="mt-1 font-bold">
                                    {{ prestigeRequirementLabel }} km
                                </p>
                            </div>
                        </div>

                        <div
                            class="overflow-hidden rounded-full border border-[#d7cbb8] bg-[#efe8d9] dark:border-white/15 dark:bg-white/10"
                        >
                            <div
                                class="h-2 rounded-full bg-[#243627] transition-[width] dark:bg-[#caa66c]"
                                :style="{
                                    width: `${prestigeProgressPercent}%`,
                                }"
                            ></div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-3 rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                        >
                            <div>
                                <p
                                    class="text-xs text-[#696250] dark:text-[#b8c2b0]"
                                >
                                    Prestiges
                                </p>
                                <p class="mt-1 font-bold">
                                    {{
                                        props.prestigeStats.count.toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs text-[#696250] dark:text-[#b8c2b0]"
                                >
                                    Rank
                                </p>
                                <p class="mt-1 font-bold">
                                    #{{
                                        props.prestigeStats.rank.toLocaleString()
                                    }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-3 py-2 font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                !props.prestigeStats.canPrestige || isPrestiging
                            "
                            @click="confirmPrestige"
                        >
                            <RotateCcw class="h-4 w-4" />
                            {{
                                isPrestiging
                                    ? 'Prestiging...'
                                    : props.prestigeStats.canPrestige
                                      ? 'Prestige'
                                      : 'Requirement not met'
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <button
                type="button"
                class="absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                :class="achievementsButtonClass"
                :style="achievementsButtonStyle"
                :aria-label="achievementsButtonLabel"
                :title="achievementsButtonLabel"
                @click="openAchievements"
            >
                <Award class="h-5 w-5" />
            </button>

            <button
                type="button"
                class="absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                :class="leaderboardButtonClass"
                :style="leaderboardButtonStyle"
                :aria-label="leaderboardButtonLabel"
                :title="leaderboardButtonLabel"
                @click="openLeaderboard"
            >
                <Trophy class="h-5 w-5" />
            </button>

            <div
                v-if="isImmersiveTestingPanelOpen"
                class="absolute top-4 left-4 z-40 w-[min(22rem,calc(100vw-2rem))] rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/92 dark:text-[#f3efe4]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Testing
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Scene controls</h2>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <p class="text-[#696250] dark:text-[#b8c2b0]">
                            Weather
                        </p>
                        <p class="mt-1 flex items-center gap-2 font-semibold">
                            <component :is="WeatherIcon" class="h-4 w-4" />
                            {{ weatherLabel }}
                        </p>
                        <p
                            class="mt-1 text-xs text-[#696250] dark:text-[#b8c2b0]"
                        >
                            Code {{ displayedWeatherCode ?? '-' }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <p class="text-[#696250] dark:text-[#b8c2b0]">
                            Local time
                        </p>
                        <p class="mt-1 font-semibold">
                            {{
                                `${String(displayedTime.hour).padStart(2, '0')}:${String(displayedTime.minute).padStart(2, '0')}`
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <p class="text-[#696250] dark:text-[#b8c2b0]">
                            Settlement
                        </p>
                        <p class="mt-1 font-semibold">
                            Stage {{ displayedSettlementStage }}
                        </p>
                    </div>
                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <p class="text-[#696250] dark:text-[#b8c2b0]">Roads</p>
                        <p class="mt-1 font-semibold">{{ roadLabel }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="text-sm text-[#696250] dark:text-[#b8c2b0]"
                            >Test time</span
                        >
                        <input
                            v-model="timeOverride"
                            type="time"
                            class="mt-1 w-full rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 py-2 text-sm font-semibold text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-white/15 dark:bg-[#0a0f0b]/80 dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                        />
                    </label>
                    <div class="grid grid-cols-[1fr_1fr_2.5rem] gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-[#b7aa91] px-3 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            @click="incrementTestTime(1)"
                        >
                            +1m
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-[#b7aa91] px-3 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            @click="incrementTestTime(60)"
                        >
                            +1h
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-[#b7aa91] text-[#243627] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            :disabled="!timeOverride"
                            aria-label="Use real local time"
                            @click="timeOverride = ''"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="grid grid-cols-[1fr_6rem] gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-[#b7aa91] px-3 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            @click="toggleTimeLoop"
                        >
                            <Pause v-if="isTimeLooping" class="h-4 w-4" />
                            <Play v-else class="h-4 w-4" />
                            {{ isTimeLooping ? 'Stop loop' : 'Start loop' }}
                        </button>
                        <label>
                            <span class="sr-only"
                                >Loop speed in milliseconds</span
                            >
                            <input
                                v-model.number="timeLoopSpeedMs"
                                type="number"
                                min="50"
                                max="60000"
                                step="50"
                                class="w-full rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 py-2 text-sm font-semibold text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-white/15 dark:bg-[#0a0f0b]/80 dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                                aria-label="Loop speed in milliseconds"
                            />
                        </label>
                    </div>
                    <div class="grid grid-cols-[1fr_2.5rem] gap-2">
                        <label>
                            <span
                                class="text-sm text-[#696250] dark:text-[#b8c2b0]"
                                >Test weather code</span
                            >
                            <input
                                v-model="weatherCodeOverride"
                                type="number"
                                min="0"
                                max="99"
                                step="1"
                                class="mt-1 w-full rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 py-2 text-sm font-semibold text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-white/15 dark:bg-[#0a0f0b]/80 dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                            />
                        </label>
                        <button
                            type="button"
                            class="mt-6 inline-flex h-10 w-10 items-center justify-center rounded-md border border-[#b7aa91] text-[#243627] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            :disabled="!weatherCodeOverride"
                            aria-label="Use live weather code"
                            @click="weatherCodeOverride = ''"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="grid grid-cols-[1fr_2.5rem] gap-2">
                        <label>
                            <span
                                class="text-sm text-[#696250] dark:text-[#b8c2b0]"
                                >Test settlement stage</span
                            >
                            <input
                                v-model="settlementStageOverride"
                                type="number"
                                min="0"
                                max="6"
                                step="1"
                                class="mt-1 w-full rounded-md border border-[#cfc1a8] bg-[#fffaf0] px-3 py-2 text-sm font-semibold text-[#1f241c] transition outline-none focus:border-[#9a7a46] dark:border-white/15 dark:bg-[#0a0f0b]/80 dark:text-[#f3efe4] dark:focus:border-[#caa66c]"
                            />
                        </label>
                        <button
                            type="button"
                            class="mt-6 inline-flex h-10 w-10 items-center justify-center rounded-md border border-[#b7aa91] text-[#243627] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                            :disabled="!settlementStageOverride"
                            aria-label="Use calculated settlement stage"
                            @click="settlementStageOverride = ''"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="isActionMenuOpen"
                class="absolute z-30 w-[min(22rem,calc(100vw-2rem))] rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/90 dark:text-[#f3efe4]"
                :style="actionMenuStyle"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Actions
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Kingdom status</h2>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                        aria-label="Close actions menu"
                        @click="isActionMenuOpen = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-4 grid gap-3">
                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <CheckCircle2
                                class="h-4 w-4"
                                :class="
                                    props.canCollect
                                        ? 'text-[#9fe58d]'
                                        : 'text-[#8d9487]'
                                "
                            />
                            Collection
                        </div>
                        <p class="mt-1 text-[#696250] dark:text-[#b8c2b0]">
                            {{
                                props.canCollect
                                    ? 'Daily collection is ready.'
                                    : 'Daily collection is not ready yet.'
                            }}
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md border px-3 py-2 font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                props.canCollect
                                    ? 'border-[#86ad6c] bg-[#edf6e8] text-[#2d5b28] hover:bg-[#e2f0da] dark:border-[#9fe58d]/45 dark:bg-[#15351c]/70 dark:text-[#e8ffe3] dark:hover:bg-[#1d4625]/85'
                                    : 'border-[#d6cab6] bg-[#f8efe1] text-[#696250] dark:border-white/15 dark:bg-white/[0.03] dark:text-[#b8c2b0]'
                            "
                            :disabled="!props.canCollect || isCollecting"
                            @click="collectResources"
                        >
                            <Sparkles class="h-4 w-4" />
                            {{
                                isCollecting
                                    ? 'Collecting...'
                                    : props.canCollect
                                      ? 'Collect resources'
                                      : 'Collected today'
                            }}
                        </button>
                    </div>

                    <div
                        v-if="props.prestigeStats.canPrestige"
                        class="rounded-md border border-[#c5dff1] bg-[#eef8ff]/80 p-3 dark:border-[#8fd8ff]/25 dark:bg-[#12313d]/55"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <RotateCcw class="h-4 w-4 text-[#2d7898]" />
                            Prestige
                        </div>
                        <p class="mt-1 text-[#696250] dark:text-[#b8c2b0]">
                            Your road network is ready for prestige.
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md border border-[#76b7d7] bg-[#dff2ff] px-3 py-2 font-semibold text-[#12313d] transition hover:bg-[#cfebfb] disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#8fd8ff]/35 dark:bg-[#8fd8ff]/12 dark:text-[#d9f5ff] dark:hover:bg-[#8fd8ff]/18"
                            :disabled="isPrestiging"
                            @click="confirmPrestige"
                        >
                            <RotateCcw class="h-4 w-4" />
                            {{ isPrestiging ? 'Prestiging...' : 'Prestige' }}
                        </button>
                    </div>

                    <div
                        class="rounded-md border border-[#e4dac7] bg-[#fff8eb]/70 p-3 dark:border-white/10 dark:bg-white/[0.04]"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <Hammer
                                class="h-4 w-4"
                                :class="
                                    hasActionUpgradeReady
                                        ? 'text-[#e0b461]'
                                        : 'text-[#8d9487]'
                                "
                            />
                            Building upgrades
                        </div>
                        <p class="mt-1 text-[#696250] dark:text-[#b8c2b0]">
                            {{
                                hasActionUpgradeReady
                                    ? `${actionUpgradeReadyBuildings.length} upgrade${actionUpgradeReadyBuildings.length === 1 ? '' : 's'} available.`
                                    : 'No building upgrades are currently affordable.'
                            }}
                        </p>
                        <div
                            v-if="hasActionUpgradeReady"
                            class="mt-2 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="building in actionUpgradeReadyBuildings"
                                :key="building.id"
                                class="rounded border border-[#b99145]/35 bg-[#ead9b6] px-2 py-1 text-xs font-semibold text-[#5a4320] dark:border-[#e0b461]/25 dark:bg-[#e0b461]/10 dark:text-[#fff0c8]"
                            >
                                {{ building.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="absolute z-30 h-12 w-12" :style="actionButtonStyle">
                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="actionButtonClass"
                    :aria-label="actionButtonLabel"
                    :title="actionButtonLabel"
                    @click="isActionMenuOpen = !isActionMenuOpen"
                >
                    <Bell class="h-6 w-6" />
                </button>
            </div>
        </section>
    </main>

    <Teleport to="body">
        <div
            v-if="
                isAchievementsModalOpen ||
                isLeaderboardModalOpen ||
                isMinigameOpen ||
                isAchievementUnlockModalOpen
            "
            data-game-modal-layer
            class="fixed inset-0 z-[58] flex items-center justify-center overflow-y-auto overscroll-contain bg-black/50 px-4 py-6"
            @click.self="closeActiveGameModal"
        >
            <section
                v-if="isAchievementsModalOpen"
                class="max-h-[calc(100vh-3rem)] w-full max-w-5xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <header class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-md bg-[#243627] p-2 text-white">
                            <Award class="h-5 w-5" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Achievements
                            </p>
                            <h2 class="mt-1 text-2xl font-bold">Milestones</h2>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                        aria-label="Close achievements"
                        @click="closeAchievements"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <div
                    class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                        Production bonuses unlocked by milestones
                    </p>
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
                    <h3 class="text-sm font-semibold">Current bonuses</h3>
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
                    <button
                        type="button"
                        class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                        aria-label="Close leaderboard"
                        @click="closeLeaderboard"
                    >
                        <X class="h-5 w-5" />
                    </button>
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
                            #{{
                                selectedLeaderboard.currentRank.toLocaleString()
                            }}
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

                <div
                    class="mt-5 overflow-hidden rounded-md border border-[#e4dac7] dark:border-[#35332c]"
                >
                    <div
                        class="grid grid-cols-[4rem_1fr_7rem] gap-3 border-b border-[#e4dac7] bg-[#f6f0e5] px-4 py-3 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:bg-[#151910] dark:text-[#b6ae9d]"
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

                    <div
                        v-else
                        class="divide-y divide-[#e4dac7] dark:divide-[#35332c]"
                    >
                        <div
                            v-for="entry in selectedLeaderboard.entries"
                            :key="`${selectedLeaderboard.key}-${entry.userId}`"
                            class="grid grid-cols-[4rem_1fr_7rem] items-center gap-3 px-4 py-3 text-sm"
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
                    <button
                        type="button"
                        class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                        aria-label="Close minigame"
                        :disabled="activeMinigameResource !== null"
                        @click="closeMinigame"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <div class="relative">
                    <component
                        :is="selectedMinigameComponent"
                        :key="selectedMinigame.resource"
                        :is-saving="
                            activeMinigameResource === selectedMinigame.resource
                        "
                        :is-completed="hasWonMinigame"
                        @complete="completeSelectedMinigame"
                    />
                    <button
                        v-if="hasWonMinigame && activeMinigameResource === null"
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
                            {{ formatNumber(selectedMinigameResourceAmount) }}
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
                            {{ formatNumber(selectedMinigame.completions) }}
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
                            {{ formatNumber(selectedMinigame.resourcesGained) }}
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
            <section
                v-else-if="
                    isAchievementUnlockModalOpen && currentAchievementUnlock
                "
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
</template>

<style scoped>
.immersive-background {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    max-width: none;
    object-fit: cover;
    object-position: center;
    user-select: none;
}

.immersive-celestial {
    position: absolute;
    z-index: 1;
    width: clamp(7rem, 12vw, 12rem);
    height: auto;
    max-width: none;
    pointer-events: none;
    user-select: none;
    transform: translate(-50%, -50%);
    transition:
        top 1000ms ease,
        left 1000ms ease;
}

.celestial-path-guide {
    display: none;
}

.immersive-cloud-box {
    position: absolute;
    z-index: 2;
    overflow: hidden;
    pointer-events: none;
}

.immersive-cloud {
    position: absolute;
    height: auto;
    max-width: none;
    pointer-events: none;
    user-select: none;
    animation: cloud-drift var(--cloud-duration) ease-in-out infinite alternate;
    animation-delay: var(--cloud-delay);
    transform: translate(calc(var(--cloud-drift) * -1), -50%);
}

.immersive-cloud-box::after {
    display: none;
}

.immersive-weather-layer {
    position: absolute;
    inset: 0;
    z-index: 3;
    overflow: hidden;
    pointer-events: none;
}

.immersive-rain-layer {
    background: linear-gradient(
        180deg,
        rgb(12 21 26 / 0.22),
        rgb(12 21 26 / 0.08)
    );
}

.immersive-rain-drop {
    position: absolute;
    top: -18vh;
    width: 0.12rem;
    height: 18vh;
    border-radius: 999px;
    background: linear-gradient(
        180deg,
        rgb(217 245 255 / 0),
        rgb(217 245 255 / 0.78)
    );
    animation: rain-fall var(--rain-duration) linear infinite;
    animation-delay: var(--rain-delay);
}

.immersive-snow-layer {
    background: linear-gradient(
        180deg,
        rgb(235 248 255 / 0.16),
        rgb(235 248 255 / 0.02)
    );
}

.immersive-snow-flake {
    position: absolute;
    top: -8vh;
    border-radius: 999px;
    background: rgb(245 251 255 / 0.92);
    box-shadow: 0 0 0.5rem rgb(245 251 255 / 0.45);
    animation: snow-fall var(--snow-duration) linear infinite;
    animation-delay: var(--snow-delay);
}

.immersive-fog-layer {
    z-index: 4;
    background:
        linear-gradient(180deg, rgb(226 232 222 / 0.24), transparent 38%),
        linear-gradient(0deg, rgb(226 232 222 / 0.2), transparent 42%);
}

.immersive-fog {
    position: absolute;
    left: -20%;
    width: 140%;
    height: 36%;
    border-radius: 999px;
    background: radial-gradient(
        ellipse at center,
        rgb(238 242 232 / 0.34),
        rgb(238 242 232 / 0)
    );
    filter: blur(1.5rem);
    animation: fog-drift 38s ease-in-out infinite alternate;
}

.immersive-fog-a {
    top: 24%;
}

.immersive-fog-b {
    top: 56%;
    animation-duration: 52s;
    animation-delay: -18s;
}

.immersive-lightning-layer {
    z-index: 5;
    background: rgb(217 245 255 / 0);
    animation: lightning-flash 7s step-end infinite;
}

.immersive-lightning {
    position: absolute;
    top: -4%;
    width: 0.25rem;
    height: 42%;
    opacity: 0;
    background: linear-gradient(
        180deg,
        rgb(237 250 255 / 0.95),
        rgb(141 216 255 / 0)
    );
    filter: drop-shadow(0 0 0.75rem rgb(141 216 255 / 0.9));
    clip-path: polygon(
        44% 0,
        70% 0,
        55% 36%,
        76% 36%,
        36% 100%,
        48% 48%,
        30% 48%
    );
    animation: lightning-bolt 7s step-end infinite;
}

.immersive-lightning-a {
    left: 22%;
    animation-delay: -1.8s;
}

.immersive-lightning-b {
    right: 28%;
    animation-delay: -4.9s;
}

.immersive-scene-dawn .immersive-background {
    filter: brightness(0.95) saturate(0.96);
}

.immersive-scene-dusk .immersive-background {
    filter: brightness(0.82) saturate(0.88);
}

.immersive-weather-clear .immersive-background {
    filter: brightness(1.04) saturate(1.05);
}

.immersive-weather-cloudy .immersive-background {
    filter: brightness(0.94) saturate(0.92);
}

.immersive-weather-raining .immersive-background,
.immersive-weather-thunderstorm .immersive-background {
    filter: brightness(0.72) saturate(0.75) contrast(1.04);
}

.immersive-weather-foggy .immersive-background {
    filter: brightness(0.9) saturate(0.68) contrast(0.86);
}

.immersive-weather-snowing .immersive-background {
    filter: brightness(0.96) saturate(0.72) contrast(0.92);
}

@keyframes cloud-drift {
    from {
        transform: translate(calc(var(--cloud-drift) * -1), -50%);
    }

    to {
        transform: translate(var(--cloud-drift), -50%);
    }
}

@keyframes rain-fall {
    from {
        transform: translate3d(-8vw, -20vh, 0) rotate(12deg)
            scale(var(--rain-scale));
    }

    to {
        transform: translate3d(8vw, 120vh, 0) rotate(12deg)
            scale(var(--rain-scale));
    }
}

@keyframes snow-fall {
    from {
        transform: translate3d(0, -10vh, 0);
    }

    to {
        transform: translate3d(var(--snow-drift), 112vh, 0);
    }
}

@keyframes fog-drift {
    from {
        transform: translateX(-4%);
    }

    to {
        transform: translateX(4%);
    }
}

@keyframes lightning-flash {
    0%,
    87%,
    91%,
    100% {
        background: rgb(217 245 255 / 0);
    }

    88%,
    90% {
        background: rgb(217 245 255 / 0.28);
    }
}

@keyframes lightning-bolt {
    0%,
    87%,
    91%,
    100% {
        opacity: 0;
    }

    88%,
    90% {
        opacity: 1;
    }
}
</style>
