<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Award,
    Bell,
    CheckCircle2,
    Coins,
    Hammer,
    MessageCircle,
    Mountain,
    Pause,
    Play,
    RotateCcw,
    Sparkles,
    TreePine,
    Trophy,
    UsersRound,
    Wheat,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AchievementsModal from '@/components/game-modals/AchievementsModal.vue';
import AchievementUnlockModal from '@/components/game-modals/AchievementUnlockModal.vue';
import AllianceChatModal from '@/components/game-modals/AllianceChatModal.vue';
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
import { useAchievementUnlockQueue } from '@/composables/useAchievementUnlockQueue';
import { useExpandableResourceNumbers } from '@/composables/useExpandableResourceNumbers';
import { useGameAlliance } from '@/composables/useGameAlliance';
import { useGameBuildings } from '@/composables/useGameBuildings';
import { useGameLeaderboard } from '@/composables/useGameLeaderboard';
import { useGameMinigames } from '@/composables/useGameMinigames';
import { useImmersiveTestingPanel } from '@/composables/useImmersiveTestingPanel';
import { useOfflineProgress } from '@/composables/useOfflineProgress';
import {
    formatExactNumber,
    formatGameNumber,
    minigameStaminaHoverLabel,
} from '@/lib/game';
import type {
    DashboardGameData,
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
    left: 46,
    top: 74,
};

const resourcesButtonPosition = {
    left: 40,
    top: 74,
};

const upgradesButtonPosition = {
    left: 14,
    top: 80,
};

const prestigeButtonPosition = {
    left: 43,
    top: 62,
};

const leaderboardButtonPosition = {
    right: 1,
    bottom: 5,
};

const achievementsButtonPosition = {
    right: 1,
    bottom: 9,
};

const allianceButtonPosition = {
    right: 1,
    bottom: 13,
};

const allianceChatButtonPosition = {
    right: 1,
    bottom: 17,
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
        left: 82,
        top: 54,
    },
    gold: {
        left: 88,
        top: 54,
    },
};

const MAX_ROAD_BUILD_AMOUNT = 10_000_000;
const WEATHER_ANIMATION_FPS = 60;

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
const isPrestigeMenuOpen = ref(false);
type GameModal =
    | 'achievements'
    | 'alliance'
    | 'alliance-chat'
    | 'buildings'
    | 'leaderboard'
    | 'minigame'
    | 'prestige-confirm';

const activeGameModal = ref<GameModal | null>(null);
const showOnlyAvailableUpgrades = ref(true);
const hideCompletedAchievements = ref(true);
const isCollecting = ref(false);
const isPrestiging = ref(false);
const isTimeLooping = ref(false);
const timeLoopSpeedMs = ref(500);
const predictionBaseMilliseconds = Date.now();
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

const achievements = computed(() => props.achievements);
const achievementBonuses = computed(() => props.achievementBonuses);

const unlockedAchievementCount = computed(
    () =>
        achievements.value.filter((achievement) => achievement.isUnlocked)
            .length,
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

const {
    isOfflineProgressDismissed,
    offlineProgressDurationLabel,
    offlineProgressResourceRows,
    closeOfflineProgress,
} = useOfflineProgress({
    offlineProgress: () => props.offlineProgress,
    resources: resourceRows.map((resource) => ({
        ...resource,
        icon: resourceIcons[resource.key],
    })),
});
const {
    upgradingBuildingId,
    roadBuildAmounts,
    upgradeAvailabilityLabelFor,
    roadBuildableAmountFor,
    updateRoadBuildAmount,
    upgradeBuilding,
} = useGameBuildings({
    resources: () => props.resources,
    resourceRates: () => props.resourceRates,
    predictionBaseMilliseconds: () => predictionBaseMilliseconds,
    currentMilliseconds: () => userTime.value.timestamp,
    maxRoadBuildAmount: MAX_ROAD_BUILD_AMOUNT,
    canSubmitUpgrade: (building, currentUpgradingBuildingId) =>
        building.canUpgrade && currentUpgradingBuildingId === null,
});
const {
    toggleResourceNumber,
    resourceNumberLabel,
    resourceNumberTitle,
} = useExpandableResourceNumbers();
const {
    currentAchievementUnlock,
    achievementUnlockCount,
    achievementUnlockPosition,
    achievementUnlockButtonLabel,
    advanceAchievementUnlockPopup,
} = useAchievementUnlockQueue(() => props.achievementUnlocks);
const {
    activeMinigameResource,
    selectedMinigameResource,
    hasWonMinigame,
    minigames,
    selectedMinigame,
    selectedMinigameResourceAmount,
    isMinigameOpen,
    openMinigame,
    closeMinigame,
    continueMinigame,
    completeSelectedMinigame,
} = useGameMinigames({
    minigames: () => props.minigames,
    resources: () => props.resources,
    activeGameModal,
    beforeOpen: () => {
        isPrestigeMenuOpen.value = false;
        isActionMenuOpen.value = false;
        isResourcesMenuOpen.value = false;
        closeOfflineProgress();
    },
});
const {
    selectedLeaderboardKey,
    leaderboards,
    defaultLeaderboard,
    selectedLeaderboard,
    isLeaderboardModalOpen,
    openLeaderboard,
    closeLeaderboard,
} = useGameLeaderboard({
    leaderboards: () => props.leaderboards,
    activeGameModal,
    isBlocked: () => activeMinigameResource.value !== null,
    beforeOpen: () => {
        isPrestigeMenuOpen.value = false;
        isActionMenuOpen.value = false;
        isResourcesMenuOpen.value = false;
        closeOfflineProgress();
        selectedMinigameResource.value = null;
        hasWonMinigame.value = false;
    },
});
const {
    isSubmittingAlliance,
    allianceOnlineUsers,
    allianceOnlineUserIds,
    hasUnreadAllianceChatMessage,
    isAllianceModalOpen,
    isAllianceChatModalOpen,
    openAlliance,
    closeAlliance,
    openAllianceChat,
    closeAllianceChat,
    searchAlliances,
    createAlliance,
    joinAlliance,
    applyToAlliance,
    updateAlliance,
    acceptAllianceApplication,
    denyAllianceApplication,
    leaveAlliance,
    disbandAlliance,
    promoteAllianceMember,
    demoteAllianceMember,
    transferAllianceLeadership,
    kickAllianceMember,
    contributeAllianceGoal,
    sendAllianceChatMessage,
} = useGameAlliance({
    alliances: () => props.alliances,
    activeGameModal,
    isBlocked: () => activeMinigameResource.value !== null,
    beforeOpen: () => {
        isPrestigeMenuOpen.value = false;
        isActionMenuOpen.value = false;
        isResourcesMenuOpen.value = false;
        closeOfflineProgress();
        selectedMinigameResource.value = null;
        hasWonMinigame.value = false;
    },
    beforeOpenChat: () => {
        isActionMenuOpen.value = false;
        isResourcesMenuOpen.value = false;
        isPrestigeMenuOpen.value = false;
        closeOfflineProgress();
        selectedMinigameResource.value = null;
        hasWonMinigame.value = false;
    },
});

const selectedMinigameComponent = computed(() =>
    selectedMinigame.value
        ? minigameComponents[selectedMinigame.value.resource]
        : null,
);

const isBuildingsModalOpen = computed(
    () => activeGameModal.value === 'buildings',
);

const isAchievementsModalOpen = computed(
    () => activeGameModal.value === 'achievements',
);
const isPrestigeConfirmModalOpen = computed(
    () => activeGameModal.value === 'prestige-confirm',
);
const isOfflineProgressModalOpen = computed(
    () =>
        props.offlineProgress !== null &&
        !isOfflineProgressDismissed.value &&
        !isBuildingsModalOpen.value &&
        !isAchievementsModalOpen.value &&
        !isAllianceModalOpen.value &&
        !isAllianceChatModalOpen.value &&
        !isLeaderboardModalOpen.value &&
        !isMinigameOpen.value &&
        !isPrestigeConfirmModalOpen.value,
);
const isAchievementUnlockModalOpen = computed(
    () =>
        Boolean(currentAchievementUnlock.value) &&
        !isOfflineProgressModalOpen.value &&
        !isBuildingsModalOpen.value &&
        !isAchievementsModalOpen.value &&
        !isAllianceModalOpen.value &&
        !isAllianceChatModalOpen.value &&
        !isLeaderboardModalOpen.value &&
        !isMinigameOpen.value &&
        !isPrestigeConfirmModalOpen.value,
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

const prestigeButtonLabel = computed(() =>
    props.prestigeStats.canPrestige
        ? 'Prestige ready'
        : `${prestigeProgressPercent.value}% toward prestige`,
);

const leaderboardButtonLabel = computed(() =>
    defaultLeaderboard.value
        ? `Leaderboard rank #${formatExactNumber(defaultLeaderboard.value.currentRank)}`
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

const allianceButtonStyle = computed(() => ({
    right: `${allianceButtonPosition.right}rem`,
    bottom: `${allianceButtonPosition.bottom}rem`,
}));

const allianceChatButtonStyle = computed(() => ({
    right: `${allianceChatButtonPosition.right}rem`,
    bottom: `${allianceChatButtonPosition.bottom}rem`,
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
const allianceButtonClass = iconButtonMenuClass;
const allianceChatButtonClass = computed(() =>
    hasUnreadAllianceChatMessage.value
        ? iconButtonReadyClass
        : iconButtonMenuClass,
);

const achievementsButtonLabel = computed(
    () =>
        `${formatExactNumber(unlockedAchievementCount.value)} of ${formatExactNumber(achievements.value.length)} achievements unlocked`,
);
const allianceButtonLabel = computed(() =>
    props.alliances.current
        ? `${props.alliances.current.name} alliance`
        : 'Alliances',
);
const allianceChatButtonLabel = computed(() =>
    props.alliances.current
        ? hasUnreadAllianceChatMessage.value
            ? `New ${props.alliances.current.name} chat message`
            : `${props.alliances.current.name} chat`
        : 'Alliance chat unavailable',
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
        return `${formatGameNumber(roads)} km built`;
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

const sceneOverlayClass = computed(() => {
    const conditions = displayedWeatherConditions.value;

    if (conditions.thunderstorm) {
        return 'immersive-overlay-thunderstorm';
    }

    if (conditions.raining) {
        return 'immersive-overlay-raining';
    }

    if (conditions.foggy) {
        return 'immersive-overlay-foggy';
    }

    if (conditions.snowing) {
        return 'immersive-overlay-snowing';
    }

    if (conditions.cloudy) {
        return 'immersive-overlay-cloudy';
    }

    if (conditions.clear) {
        return 'immersive-overlay-clear';
    }

    if (skyState.value === 'dawn') {
        return 'immersive-overlay-dawn';
    }

    if (skyState.value === 'dusk') {
        return 'immersive-overlay-dusk';
    }

    return '';
});

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
        '--cloud-steps': String(
            Math.max(1, Math.round(cloud.duration * WEATHER_ANIMATION_FPS)),
        ),
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
        '--rain-steps': String(
            Math.max(1, Math.round(drop.duration * WEATHER_ANIMATION_FPS)),
        ),
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
        '--snow-steps': String(
            Math.max(1, Math.round(flake.duration * WEATHER_ANIMATION_FPS)),
        ),
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

function togglePrestigeMenu(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isPrestigeMenuOpen.value = !isPrestigeMenuOpen.value;
    activeGameModal.value = null;
    closeOfflineProgress();
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
}

function toggleResourcesMenu(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isResourcesMenuOpen.value = !isResourcesMenuOpen.value;
    activeGameModal.value = null;
    closeOfflineProgress();
    isActionMenuOpen.value = false;
    isPrestigeMenuOpen.value = false;
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
}

function closeResourcesMenu(): void {
    isResourcesMenuOpen.value = false;
}

function closePrestigeMenu(): void {
    isPrestigeMenuOpen.value = false;
}

function openBuildings(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isPrestigeMenuOpen.value = false;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    closeOfflineProgress();
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'buildings';
}

function openAchievements(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isPrestigeMenuOpen.value = false;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    closeOfflineProgress();
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'achievements';
}

function closeBuildings(): void {
    if (activeGameModal.value === 'buildings') {
        activeGameModal.value = null;
    }
}

function closeAchievements(): void {
    if (activeGameModal.value === 'achievements') {
        activeGameModal.value = null;
    }
}

function openPrestigeConfirm(): void {
    if (activeMinigameResource.value !== null) {
        return;
    }

    isPrestigeMenuOpen.value = false;
    isActionMenuOpen.value = false;
    isResourcesMenuOpen.value = false;
    closeOfflineProgress();
    selectedMinigameResource.value = null;
    hasWonMinigame.value = false;
    activeGameModal.value = 'prestige-confirm';
}

function closePrestigeConfirm(): void {
    if (activeGameModal.value === 'prestige-confirm') {
        activeGameModal.value = null;
    }
}

function closeActiveGameModal(): void {
    if (isResourcesMenuOpen.value) {
        closeResourcesMenu();

        return;
    }

    if (isPrestigeMenuOpen.value) {
        closePrestigeMenu();

        return;
    }

    if (isOfflineProgressModalOpen.value) {
        closeOfflineProgress();

        return;
    }

    if (activeGameModal.value === 'buildings') {
        closeBuildings();

        return;
    }

    if (activeGameModal.value === 'achievements') {
        closeAchievements();

        return;
    }

    if (activeGameModal.value === 'alliance') {
        closeAlliance();

        return;
    }

    if (activeGameModal.value === 'alliance-chat') {
        closeAllianceChat();

        return;
    }

    if (activeGameModal.value === 'prestige-confirm') {
        closePrestigeConfirm();

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
                isPrestigeMenuOpen.value = false;
            },
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

    <main class="immersive-scroll-shell min-h-full bg-[#071015] text-[#f3efe4]">
        <section
            class="immersive-scene relative min-h-[calc(100svh-3.5rem)] overflow-hidden sm:min-h-[calc(100svh-4rem)] sm:min-h-[calc(100vh-4rem)]"
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
                class="immersive-color-overlay"
                :class="sceneOverlayClass"
            ></div>

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
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border transition hover:scale-105 disabled:cursor-not-allowed disabled:hover:scale-100"
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
                class="immersive-menu-anchor absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="resourcesButtonStyle"
            >
                <button
                    type="button"
                    class="immersive-icon-button inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="iconButtonMenuClass"
                    aria-label="Show resources and production"
                    title="Resources and production"
                    @click="toggleResourcesMenu"
                >
                    <Coins class="h-5 w-5" />
                </button>
            </div>

            <div
                class="immersive-menu-anchor absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="upgradesButtonStyle"
            >
                <button
                    type="button"
                    class="immersive-icon-button inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="upgradesButtonClass"
                    :aria-label="upgradesButtonLabel"
                    :title="upgradesButtonLabel"
                    @click="openBuildings"
                >
                    <Hammer class="h-5 w-5" />
                </button>
            </div>

            <button
                v-for="minigame in minigameButtons"
                :key="minigame.resource"
                type="button"
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border transition hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                :class="minigameButtonClass()"
                :style="minigame.style"
                :disabled="
                    activeMinigameResource !== null ||
                    !minigame.stamina.isAvailable
                "
                :aria-label="`Play ${minigame.label}. ${minigameStaminaHoverLabel(minigame)}`"
                :title="minigameStaminaHoverLabel(minigame)"
                @click="openMinigame(minigame)"
            >
                <component :is="minigame.icon" class="h-5 w-5" />
            </button>

            <div
                class="immersive-menu-anchor absolute z-30 -translate-x-1/2 -translate-y-1/2"
                :style="prestigeButtonStyle"
            >
                <button
                    type="button"
                    class="immersive-icon-button inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                    :class="prestigeButtonClass"
                    :aria-label="prestigeButtonLabel"
                    :title="prestigeButtonLabel"
                    @click="togglePrestigeMenu"
                >
                    <RotateCcw class="h-5 w-5" />
                </button>
            </div>

            <button
                v-if="props.alliances.current"
                type="button"
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                :class="allianceChatButtonClass"
                :style="allianceChatButtonStyle"
                :aria-label="allianceChatButtonLabel"
                :title="allianceChatButtonLabel"
                @click="openAllianceChat"
            >
                <MessageCircle class="h-5 w-5" />
            </button>

            <button
                type="button"
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
                :class="allianceButtonClass"
                :style="allianceButtonStyle"
                :aria-label="allianceButtonLabel"
                :title="allianceButtonLabel"
                @click="openAlliance"
            >
                <UsersRound class="h-5 w-5" />
            </button>

            <button
                type="button"
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
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
                class="immersive-icon-button absolute z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
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
                class="immersive-testing-panel absolute top-4 left-4 z-40 w-[min(22rem,calc(100vw-2rem))] rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/92 dark:text-[#f3efe4]"
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
                class="immersive-action-popover immersive-popover immersive-popover-action absolute z-30 rounded-lg border border-[#ded2bd] bg-[#fffaf0]/95 p-4 text-sm text-[#1f241c] shadow-2xl backdrop-blur dark:border-white/15 dark:bg-[#10140f]/90 dark:text-[#f3efe4]"
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
                            @click="openPrestigeConfirm"
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

            <div
                class="immersive-icon-button-frame absolute z-30 h-12 w-12"
                :style="actionButtonStyle"
            >
                <button
                    type="button"
                    class="immersive-icon-button inline-flex h-12 w-12 items-center justify-center rounded-full border transition hover:scale-105"
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
                isResourcesMenuOpen ||
                isPrestigeMenuOpen ||
                isBuildingsModalOpen ||
                isAchievementsModalOpen ||
                isAllianceModalOpen ||
                isAllianceChatModalOpen ||
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
            <section
                v-if="isResourcesMenuOpen"
                class="w-full max-w-md rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Resources
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Current output</h2>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                        aria-label="Close resources menu"
                        @click="closeResourcesMenu"
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
                            <p class="font-semibold">{{ resource.label }}</p>
                            <p
                                class="mt-1 text-xs text-[#696250] dark:text-[#b8c2b0]"
                            >
                                +{{
                                    formatGameNumber(
                                        props.resourceRates[resource.key],
                                    )
                                }}/hour
                            </p>
                        </div>
                        <button
                            type="button"
                            class="max-w-full cursor-pointer self-center text-right text-base font-bold break-words"
                            :aria-label="`${resource.label}: ${formatExactNumber(props.resources[resource.key])}`"
                            :title="resourceNumberTitle(`current-${resource.key}`)"
                            @click="toggleResourceNumber(`current-${resource.key}`)"
                        >
                            {{
                                resourceNumberLabel(
                                    `current-${resource.key}`,
                                    props.resources[resource.key],
                                )
                            }}
                        </button>
                    </div>
                </div>
            </section>
            <section
                v-else-if="isPrestigeMenuOpen"
                class="w-full max-w-md rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Prestige
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Reset progress</h2>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#b7aa91] text-[#5d6356] transition hover:bg-[#ebe4d7] dark:border-white/15 dark:text-[#f3efe4] dark:hover:bg-white/10"
                        aria-label="Close prestige menu"
                        @click="closePrestigeMenu"
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
                                {{ formatGameNumber(props.roadStats.length) }} km
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
                            :style="{ width: `${prestigeProgressPercent}%` }"
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
                                {{ formatGameNumber(props.prestigeStats.count) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs text-[#696250] dark:text-[#b8c2b0]"
                            >
                                Rank
                            </p>
                            <p class="mt-1 font-bold">
                                #{{ formatExactNumber(props.prestigeStats.rank) }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-3 py-2 font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!props.prestigeStats.canPrestige || isPrestiging"
                        @click="openPrestigeConfirm"
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
            </section>
            <BuildingsModal
                v-else-if="isBuildingsModalOpen"
                :buildings="visibleUpgradeBuildings"
                :upgrading-building-id="upgradingBuildingId"
                :road-build-amounts="roadBuildAmounts"
                :max-road-build-amount="MAX_ROAD_BUILD_AMOUNT"
                title="Buildings"
                subtitle="Upgrade status"
                description="Upgrade buildings and expand roads from immersive mode."
                max-width-class="max-w-4xl"
                :show-filter="true"
                :show-only-available="showOnlyAvailableUpgrades"
                :empty-message="
                    showOnlyAvailableUpgrades
                        ? 'No building upgrades are currently affordable.'
                        : 'No further building upgrades are available.'
                "
                :upgrade-availability-label-for="upgradeAvailabilityLabelFor"
                :road-buildable-amount-for="roadBuildableAmountFor"
                @close="closeBuildings"
                @upgrade="upgradeBuilding"
                @update:show-only-available="showOnlyAvailableUpgrades = $event"
                @update-road-amount="updateRoadBuildAmount"
            />
            <AchievementsModal
                v-else-if="isAchievementsModalOpen"
                :achievements="achievements"
                :achievement-bonuses="achievementBonuses"
                :hide-completed="hideCompletedAchievements"
                @close="closeAchievements"
                @update:hide-completed="hideCompletedAchievements = $event"
            />
            <AllianceModal
                v-else-if="isAllianceModalOpen"
                :alliances="props.alliances"
                :is-submitting="isSubmittingAlliance"
                :online-user-ids="allianceOnlineUserIds"
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
            <AllianceChatModal
                v-else-if="isAllianceChatModalOpen && props.alliances.current"
                :alliance="props.alliances.current"
                :is-submitting="isSubmittingAlliance"
                :online-users="allianceOnlineUsers"
                @close="closeAllianceChat"
                @send="sendAllianceChatMessage"
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
                :resource-number-label="resourceNumberLabel"
                :resource-number-title="resourceNumberTitle"
                @close="closeMinigame"
                @complete="completeSelectedMinigame"
                @continue="continueMinigame"
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

<style scoped>
.immersive-scroll-shell {
    --immersive-shell-height: calc(100svh - 4rem);
    --immersive-scene-width-from-height: calc(177.7svh - 7.11rem);
    width: 100%;
    min-height: var(--immersive-shell-height);
    overflow-x: auto;
    overflow-y: visible;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}

.immersive-scene {
    width: max(100%, var(--immersive-scene-width-from-height));
    height: max(var(--immersive-shell-height), 56.25vw);
    min-height: max(var(--immersive-shell-height), 56.25vw);
    touch-action: pan-x pan-y pinch-zoom;
}

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

.immersive-color-overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: transparent;
}

.immersive-celestial {
    position: absolute;
    z-index: 2;
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

.immersive-popover,
.immersive-testing-panel {
    overscroll-behavior: contain;
}

.immersive-popover-resources {
    width: 20rem;
    max-width: calc(100vw - 2rem);
}

.immersive-popover-prestige,
.immersive-popover-action {
    width: 22rem;
    max-width: calc(100vw - 2rem);
}

.immersive-menu-anchor:has(.immersive-popover) {
    z-index: 70 !important;
}

.celestial-path-guide {
    display: none;
}

.immersive-cloud-box {
    position: absolute;
    z-index: 3;
    overflow: hidden;
    pointer-events: none;
}

.immersive-cloud {
    position: absolute;
    height: auto;
    max-width: none;
    pointer-events: none;
    user-select: none;
    animation: cloud-drift var(--cloud-duration) steps(var(--cloud-steps), end)
        infinite alternate;
    animation-delay: var(--cloud-delay);
    transform: translate(calc(var(--cloud-drift) * -1), -50%);
}

.immersive-cloud-box::after {
    display: none;
}

.immersive-weather-layer {
    position: absolute;
    inset: 0;
    z-index: 4;
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
    animation: rain-fall var(--rain-duration) steps(var(--rain-steps), end)
        infinite;
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
    animation: snow-fall var(--snow-duration) steps(var(--snow-steps), end)
        infinite;
    animation-delay: var(--snow-delay);
}

.immersive-fog-layer {
    z-index: 5;
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
    animation: fog-drift 38s steps(2280, end) infinite alternate;
}

.immersive-fog-a {
    top: 24%;
}

.immersive-fog-b {
    top: 56%;
    animation-duration: 52s;
    animation-timing-function: steps(3120, end);
    animation-delay: -18s;
}

.immersive-lightning-layer {
    z-index: 6;
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

.immersive-overlay-dawn {
    background: rgb(255 190 118 / 0.08);
}

.immersive-overlay-dusk {
    background: rgb(41 26 46 / 0.22);
}

.immersive-overlay-clear {
    background: rgb(255 240 184 / 0.04);
}

.immersive-overlay-cloudy {
    background: rgb(47 56 59 / 0.13);
}

.immersive-overlay-raining {
    background: rgb(13 29 38 / 0.32);
}

.immersive-overlay-thunderstorm {
    background: rgb(7 16 30 / 0.42);
}

.immersive-overlay-foggy {
    background: rgb(228 233 222 / 0.3);
}

.immersive-overlay-snowing {
    background: rgb(229 243 250 / 0.22);
}

@media (max-width: 640px) {
    .immersive-scroll-shell {
        --immersive-shell-height: calc(100svh - 3.5rem);
        --immersive-scene-width-from-height: calc(177.7svh - 6.22rem);
    }

    .immersive-celestial {
        width: clamp(5rem, 24vw, 7rem);
    }

    .immersive-icon-button,
    .immersive-icon-button-frame {
        width: 2.75rem !important;
        height: 2.75rem !important;
    }

    .immersive-icon-button :deep(svg) {
        width: 1.125rem;
        height: 1.125rem;
    }

    .immersive-menu-anchor {
        translate: none !important;
        transform: none !important;
    }

    .immersive-menu-anchor > .immersive-icon-button {
        translate: -50% -50%;
        transform: none;
    }

    .immersive-popover {
        position: fixed !important;
        top: auto !important;
        right: max(0.75rem, env(safe-area-inset-right)) !important;
        bottom: max(0.75rem, env(safe-area-inset-bottom)) !important;
        left: max(0.75rem, env(safe-area-inset-left)) !important;
        z-index: 55;
        width: auto !important;
        max-height: min(64svh, 30rem);
        overflow-y: auto;
        translate: none !important;
        transform: none !important;
    }

    .immersive-action-popover {
        bottom: calc(
            max(0.75rem, env(safe-area-inset-bottom)) + 3.25rem
        ) !important;
    }

    .immersive-testing-panel {
        position: fixed !important;
        top: calc(3.5rem + max(0.75rem, env(safe-area-inset-top))) !important;
        right: max(0.75rem, env(safe-area-inset-right)) !important;
        left: max(0.75rem, env(safe-area-inset-left)) !important;
        width: auto !important;
        max-height: calc(100svh - 5rem);
        overflow-y: auto;
    }
}

@media (max-height: 480px) and (max-width: 900px) {
    .immersive-celestial {
        width: clamp(4rem, 10vw, 5rem);
    }

    .immersive-icon-button,
    .immersive-icon-button-frame {
        width: 2.25rem !important;
        height: 2.25rem !important;
    }

    .immersive-icon-button :deep(svg) {
        width: 0.95rem;
        height: 0.95rem;
    }

    .immersive-menu-anchor {
        translate: none !important;
        transform: none !important;
    }

    .immersive-menu-anchor > .immersive-icon-button {
        translate: -50% -50%;
        transform: none;
    }

    .immersive-popover {
        position: fixed !important;
        top: auto !important;
        right: max(0.5rem, env(safe-area-inset-right)) !important;
        bottom: max(0.5rem, env(safe-area-inset-bottom)) !important;
        left: max(0.5rem, env(safe-area-inset-left)) !important;
        z-index: 55;
        width: auto !important;
        max-height: calc(100svh - 1rem);
        padding: 0.9rem;
        overflow-y: auto;
        translate: none !important;
        transform: none !important;
    }

    .immersive-action-popover {
        bottom: calc(
            max(0.5rem, env(safe-area-inset-bottom)) + 2.75rem
        ) !important;
    }

    .immersive-testing-panel {
        top: max(0.5rem, env(safe-area-inset-top)) !important;
        max-height: calc(100svh - 1rem);
        padding: 0.9rem;
    }
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
