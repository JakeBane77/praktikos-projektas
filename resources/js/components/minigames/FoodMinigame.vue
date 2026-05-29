<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Sprout } from 'lucide-vue-next';
import {
    foodCrops,
    wiltedCropImage,
    type FoodCropDefinition,
} from './foodCrops';

const props = defineProps<{
    isSaving: boolean;
    isCompleted: boolean;
}>();

const emit = defineEmits<{
    complete: [];
}>();

type HarvestResult = 'harvested' | 'early' | 'wilted' | null;

type CropPlot = {
    id: number;
    crop: FoodCropDefinition;
    stageIndex: number;
    isWilted: boolean;
    timeoutId?: number;
};

const MIN_PLOTS = 4;
const MAX_PLOTS = 6;
const WILT_DISPLAY_MILLISECONDS = 900;

const plots = ref<CropPlot[]>([]);
const harvests = ref(0);
const targetHarvests = ref(0);
const lastResult = ref<HarvestResult>(null);
const lastHarvestedCrop = ref<string | null>(null);

const harvestPercent = computed(() =>
    targetHarvests.value > 0
        ? Math.min(
              100,
              Math.floor((harvests.value / targetHarvests.value) * 100),
          )
        : 0,
);

const statusLabel = computed(() => {
    if (props.isSaving) {
        return 'Saving reward...';
    }

    if (lastResult.value === 'harvested') {
        return `${lastHarvestedCrop.value ?? 'Crop'} harvested`;
    }

    if (lastResult.value === 'early') {
        return 'Not ripe yet';
    }

    if (lastResult.value === 'wilted') {
        return 'That plot is being resown';
    }

    return 'Harvest ripe crops before they wilt';
});

watch(
    () => props.isCompleted,
    (isCompleted) => {
        if (isCompleted) {
            clearPlotTimers();

            return;
        }

        resetGame();
    },
);

onMounted(() => {
    resetGame();
});

onBeforeUnmount(() => {
    clearPlotTimers();
});

function randomInteger(minimum: number, maximum: number): number {
    return Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
}

function randomEvenInteger(minimum: number, maximum: number): number {
    const evenNumbers = [];

    for (let value = minimum; value <= maximum; value += 1) {
        if (value % 2 === 0) {
            evenNumbers.push(value);
        }
    }

    return evenNumbers[randomInteger(0, evenNumbers.length - 1)];
}

function randomCrop(): FoodCropDefinition {
    return foodCrops[randomInteger(0, foodCrops.length - 1)];
}

function randomStageDuration(crop: FoodCropDefinition): number {
    return randomInteger(crop.minStageDurationMs, crop.maxStageDurationMs);
}

function resetGame() {
    clearPlotTimers();

    const plotCount = randomEvenInteger(MIN_PLOTS, MAX_PLOTS);

    harvests.value = 0;
    targetHarvests.value = Math.ceil(plotCount * 1.5);
    lastResult.value = null;
    lastHarvestedCrop.value = null;
    plots.value = Array.from({ length: plotCount }, (_, index) => ({
        id: index + 1,
        crop: randomCrop(),
        stageIndex: 0,
        isWilted: false,
    }));

    plots.value.forEach(scheduleNextStep);
}

function clearPlotTimers() {
    plots.value.forEach(clearPlotTimer);
}

function clearPlotTimer(plot: CropPlot) {
    if (plot.timeoutId !== undefined) {
        window.clearTimeout(plot.timeoutId);
        plot.timeoutId = undefined;
    }
}

function scheduleNextStep(plot: CropPlot) {
    clearPlotTimer(plot);

    if (props.isCompleted) {
        return;
    }

    plot.timeoutId = window.setTimeout(() => {
        advancePlot(plot.id);
    }, randomStageDuration(plot.crop));
}

function advancePlot(plotId: number) {
    const plot = plots.value.find((candidate) => candidate.id === plotId);

    if (!plot || props.isCompleted) {
        return;
    }

    if (plot.stageIndex < plot.crop.stages.length - 1) {
        plot.stageIndex += 1;
        scheduleNextStep(plot);

        return;
    }

    wiltPlot(plot);
}

function wiltPlot(plot: CropPlot) {
    clearPlotTimer(plot);
    plot.isWilted = true;
    plot.timeoutId = window.setTimeout(() => {
        resowPlot(plot);
    }, WILT_DISPLAY_MILLISECONDS);
}

function resowPlot(plot: CropPlot) {
    clearPlotTimer(plot);
    plot.crop = randomCrop();
    plot.stageIndex = 0;
    plot.isWilted = false;
    scheduleNextStep(plot);
}

function harvest(plotId: number) {
    if (props.isSaving || props.isCompleted) {
        return;
    }

    const plot = plots.value.find((candidate) => candidate.id === plotId);

    if (!plot) {
        return;
    }

    if (plot.isWilted) {
        lastResult.value = 'wilted';

        return;
    }

    if (plot.stageIndex < plot.crop.stages.length - 1) {
        lastResult.value = 'early';
        wiltPlot(plot);

        return;
    }

    harvests.value += 1;
    lastResult.value = 'harvested';
    lastHarvestedCrop.value = plot.crop.name;

    if (harvests.value >= targetHarvests.value) {
        clearPlotTimers();
        emit('complete');

        return;
    }

    resowPlot(plot);
}

function plotImage(plot: CropPlot): string {
    return plot.isWilted
        ? wiltedCropImage
        : plot.crop.stages[plot.stageIndex].image;
}

function plotStageLabel(plot: CropPlot): string {
    return plot.isWilted ? 'Wilted' : plot.crop.stages[plot.stageIndex].label;
}

function isPlotRipe(plot: CropPlot): boolean {
    return !plot.isWilted && plot.stageIndex === plot.crop.stages.length - 1;
}
</script>

<template>
    <div
        class="mt-5 flex min-h-[420px] w-full flex-col overflow-hidden rounded-md border border-[#d6cab6] bg-[#eef0df] text-left sm:min-h-[520px] dark:border-[#35332c] dark:bg-[#10140e]"
    >
        <div
            class="relative flex min-h-[340px] flex-1 flex-col overflow-hidden bg-[#dfe7cc] px-4 py-5 dark:bg-[#11180f] sm:min-h-[440px]"
        >
            <div
                class="absolute inset-x-0 bottom-0 h-20 bg-[#7d8b5b] dark:bg-[#25311f]"
            ></div>
            <div
                class="absolute inset-x-0 bottom-16 h-24 bg-gradient-to-t from-[#c7d5a8] to-transparent dark:from-[#1d2918]"
            ></div>

            <div class="relative z-10 grid flex-1 grid-cols-2 gap-3 lg:grid-cols-3">
                <button
                    v-for="plot in plots"
                    :key="plot.id"
                    type="button"
                    class="flex min-h-36 cursor-pointer flex-col items-center justify-end rounded-md border bg-[#f0e4c7]/75 p-3 text-center transition hover:-translate-y-0.5 hover:border-[#b9aa8f] disabled:cursor-not-allowed disabled:opacity-70 dark:bg-[#1c2015]/80 dark:hover:border-[#56503f]"
                    :class="
                        isPlotRipe(plot)
                            ? 'border-[#8ea85d] shadow-[0_0_0_2px_rgba(142,168,93,0.2)] dark:border-[#9dcc84]'
                            : 'border-[#d6cab6] dark:border-[#35332c]'
                    "
                    :disabled="isSaving || isCompleted"
                    :aria-label="`Harvest ${plot.crop.name} plot`"
                    @click="harvest(plot.id)"
                >
                    <div
                        class="mb-2 flex h-28 w-full items-end justify-center rounded-md bg-[#9a7746]/20 dark:bg-[#4b3a22]/35"
                    >
                        <img
                            :src="plotImage(plot)"
                            :alt="`${plot.crop.name} ${plotStageLabel(plot)}`"
                            class="h-28 w-28 object-contain transition duration-300"
                            :class="isPlotRipe(plot) ? 'scale-110' : ''"
                            draggable="false"
                        />
                    </div>
                    <span class="text-sm font-bold">
                        Plot {{ plot.id }}
                    </span>
                    <span class="mt-1 text-xs font-semibold text-[#696250] dark:text-[#b6ae9d]">
                        {{ plot.crop.name }} | {{ plotStageLabel(plot) }}
                    </span>
                </button>
            </div>
        </div>

        <div
            class="border-t border-[#d6cab6] bg-[#f9f4e8] px-4 py-3 dark:border-[#35332c] dark:bg-[#151910]"
        >
            <div
                class="flex flex-col gap-3 text-sm font-semibold sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="inline-flex items-center gap-2">
                    <Sprout class="h-4 w-4" />
                    {{ statusLabel }}
                </span>
                <span class="text-[#637447] dark:text-[#b7d38e]">
                    {{ harvests }} / {{ targetHarvests }} successful harvests
                </span>
            </div>

            <div
                class="mt-3 overflow-hidden rounded-full border border-[#7d8d5f] bg-[#eef0d6] dark:border-[#4c5b3f] dark:bg-[#202516]"
            >
                <div
                    class="h-3 rounded-full bg-[#7faa41] transition-[width] dark:bg-[#9dcc84]"
                    :style="{
                        width: `${harvestPercent}%`,
                    }"
                ></div>
            </div>
        </div>
    </div>
</template>
