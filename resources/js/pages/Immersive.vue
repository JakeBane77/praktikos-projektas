<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Pause, Play, RotateCcw } from 'lucide-vue-next';
import type { DashboardGameData } from '@/lib/game';
import { getUserSystemTime, type UserSystemTime } from '@/lib/userSystemTime';
import { weatherConditionLabel, weatherIconFor } from '@/lib/weather';
import { dashboard, immersive } from '@/routes';

const backgroundAssets = import.meta.glob<string>('./assets/backgrounds/*.png', {
    eager: true,
    import: 'default',
    query: '?url',
});
const cloudAssets = import.meta.glob<string>('./assets/backgrounds/clouds/*.png', {
    eager: true,
    import: 'default',
    query: '?url',
});
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
const isTimeLooping = ref(false);
const timeLoopSpeedMs = ref(500);
let userTimeInterval: number | undefined;
let timeLoopInterval: number | undefined;

const weatherLabel = computed(() =>
    weatherConditionLabel(props.weather.conditions),
);
const WeatherIcon = computed(() =>
    weatherIconFor(props.weather.weatherCode, props.weather.conditions),
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

const roadLabel = computed(() => {
    const roads = buildingLevels.value.road;

    if (roads > 0) {
        return `${compactNumber(roads)} km built`;
    }

    return 'No roads built';
});

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

const activeClouds = computed(() => (isSunVisible.value ? dayClouds : nightClouds));

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
}));

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
    return (
        cloudAssets[`./assets/backgrounds/clouds/${filename}`] ?? emptyAsset
    );
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

function useEmptyAsset(event: Event): void {
    const image = event.target;

    if (!(image instanceof HTMLImageElement) || !emptyAsset) {
        return;
    }

    if (image.src !== emptyAsset) {
        image.src = emptyAsset;
    }
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
                class="absolute top-4 right-4 left-4 z-20 flex flex-col gap-3 sm:left-auto sm:w-[22rem]"
            >
                <div
                    class="rounded-lg border border-white/15 bg-[#10140f]/82 p-4 shadow-xl backdrop-blur"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-[#caa66c] uppercase"
                            >
                                Immersive mode
                            </p>
                            <h1 class="mt-1 text-xl font-bold">
                                Sky kingdom view
                            </h1>
                        </div>
                        <Link
                            :href="dashboard()"
                            class="inline-flex items-center justify-center rounded-md border border-white/15 p-2 text-[#f3efe4] transition hover:bg-white/10"
                            aria-label="Dashboard mode"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </Link>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-[#b8c2b0]">Weather</p>
                            <p
                                class="mt-1 flex items-center gap-2 font-semibold"
                            >
                                <component :is="WeatherIcon" class="h-4 w-4" />
                                {{ weatherLabel }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[#b8c2b0]">Local time</p>
                            <p class="mt-1 font-semibold">
                                {{
                                    `${String(displayedTime.hour).padStart(2, '0')}:${String(displayedTime.minute).padStart(2, '0')}`
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[#b8c2b0]">Settlement</p>
                            <p class="mt-1 font-semibold">
                                Stage {{ displayedSettlementStage }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[#b8c2b0]">Roads</p>
                            <p class="mt-1 font-semibold">{{ roadLabel }}</p>
                        </div>
                    </div>

                    <div
                        class="mt-4 grid gap-2 border-t border-white/10 pt-4"
                    >
                        <label class="min-w-0 flex-1">
                            <span class="text-sm text-[#b8c2b0]"
                                >Test time</span
                            >
                            <input
                                v-model="timeOverride"
                                type="time"
                                class="mt-1 w-full rounded-md border border-white/15 bg-[#0a0f0b]/80 px-3 py-2 text-sm font-semibold text-[#f3efe4] outline-none transition focus:border-[#caa66c]"
                            />
                        </label>
                        <div class="grid grid-cols-[1fr_1fr_2.5rem] gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-white/15 px-3 py-2 text-sm font-semibold text-[#f3efe4] transition hover:bg-white/10"
                                @click="incrementTestTime(1)"
                            >
                                +1m
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-white/15 px-3 py-2 text-sm font-semibold text-[#f3efe4] transition hover:bg-white/10"
                                @click="incrementTestTime(60)"
                            >
                                +1h
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-white/15 text-[#f3efe4] transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-45"
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
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-white/15 px-3 py-2 text-sm font-semibold text-[#f3efe4] transition hover:bg-white/10"
                                @click="toggleTimeLoop"
                            >
                                <Pause
                                    v-if="isTimeLooping"
                                    class="h-4 w-4"
                                />
                                <Play v-else class="h-4 w-4" />
                                {{ isTimeLooping ? 'Stop loop' : 'Start loop' }}
                            </button>
                            <label>
                                <span class="sr-only">Loop speed in milliseconds</span>
                                <input
                                    v-model.number="timeLoopSpeedMs"
                                    type="number"
                                    min="50"
                                    max="60000"
                                    step="50"
                                    class="w-full rounded-md border border-white/15 bg-[#0a0f0b]/80 px-3 py-2 text-sm font-semibold text-[#f3efe4] outline-none transition focus:border-[#caa66c]"
                                    aria-label="Loop speed in milliseconds"
                                />
                            </label>
                        </div>
                        <div class="grid grid-cols-[1fr_2.5rem] gap-2">
                            <label>
                                <span class="text-sm text-[#b8c2b0]"
                                    >Test settlement stage</span
                                >
                                <input
                                    v-model="settlementStageOverride"
                                    type="number"
                                    min="0"
                                    max="6"
                                    step="1"
                                    class="mt-1 w-full rounded-md border border-white/15 bg-[#0a0f0b]/80 px-3 py-2 text-sm font-semibold text-[#f3efe4] outline-none transition focus:border-[#caa66c]"
                                />
                            </label>
                            <button
                                type="button"
                                class="mt-6 inline-flex h-10 w-10 items-center justify-center rounded-md border border-white/15 text-[#f3efe4] transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="!settlementStageOverride"
                                aria-label="Use calculated settlement stage"
                                @click="settlementStageOverride = ''"
                            >
                                <RotateCcw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
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

.immersive-scene-dawn .immersive-background {
    filter: brightness(0.95) saturate(0.96);
}

.immersive-scene-dusk .immersive-background {
    filter: brightness(0.82) saturate(0.88);
}

@keyframes cloud-drift {
    from {
        transform: translate(calc(var(--cloud-drift) * -1), -50%);
    }

    to {
        transform: translate(var(--cloud-drift), -50%);
    }
}

</style>
