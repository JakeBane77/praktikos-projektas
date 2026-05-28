<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Pickaxe } from 'lucide-vue-next';
import { stoneRocks, type StoneRockDefinition } from './stoneRocks';

const props = defineProps<{
    isSaving: boolean;
    isCompleted: boolean;
}>();

const emit = defineEmits<{
    complete: [];
}>();

type MiningResult = 'spot' | 'rock' | 'cooldown' | null;
type SpotPosition = {
    x: number;
    y: number;
};

const CLICK_COOLDOWN_MILLISECONDS = 500;

const activeRock = ref<StoneRockDefinition>(stoneRocks[0]);
const health = ref(0);
const maxHealth = ref(0);
const markedSpot = ref<SpotPosition>({ x: 50, y: 50 });
const previousMarkedSpot = ref<SpotPosition | null>(null);
const lastResult = ref<MiningResult>(null);
const cooldownUntil = ref(0);
const cooldownRemainingMilliseconds = ref(0);

const healthPercent = computed(() =>
    maxHealth.value > 0
        ? Math.max(0, Math.floor((health.value / maxHealth.value) * 100))
        : 0,
);

const statusLabel = computed(() => {
    if (props.isSaving) {
        return 'Saving reward...';
    }

    if (cooldownRemainingMilliseconds.value > 0) {
        return `Swing cooldown ${Math.ceil(cooldownRemainingMilliseconds.value / 1000)}s`;
    }

    if (lastResult.value === 'spot') {
        return 'Marked spot hit: 2 damage';
    }

    if (lastResult.value === 'rock') {
        return 'Rock chipped: 1 damage';
    }

    if (lastResult.value === 'cooldown') {
        return 'Pickaxe is recovering';
    }

    return 'Ready to mine';
});

let cooldownInterval: number | undefined;

watch(
    () => props.isCompleted,
    (isCompleted) => {
        if (!isCompleted) {
            resetGame();
        }
    },
);

onMounted(() => {
    resetGame();

    cooldownInterval = window.setInterval(() => {
        cooldownRemainingMilliseconds.value = Math.max(
            0,
            cooldownUntil.value - Date.now(),
        );
    }, 50);
});

onBeforeUnmount(() => {
    if (cooldownInterval !== undefined) {
        window.clearInterval(cooldownInterval);
    }
});

function randomInteger(minimum: number, maximum: number): number {
    return Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
}

function randomRock(): StoneRockDefinition {
    return stoneRocks[randomInteger(0, stoneRocks.length - 1)];
}

function randomSpot(): SpotPosition {
    return {
        x: randomInteger(16, 84),
        y: randomInteger(18, 76),
    };
}

function distanceBetween(first: SpotPosition, second: SpotPosition): number {
    return Math.hypot(first.x - second.x, first.y - second.y);
}

function nextMarkedSpot(): SpotPosition {
    const previous = previousMarkedSpot.value;

    if (!previous) {
        return randomSpot();
    }

    for (let attempt = 0; attempt < 20; attempt += 1) {
        const candidate = randomSpot();

        if (
            distanceBetween(candidate, previous) >=
            activeRock.value.spotMinDistance
        ) {
            return candidate;
        }
    }

    return {
        x: 100 - previous.x,
        y: 100 - previous.y,
    };
}

function resetGame() {
    activeRock.value = randomRock();
    const nextHealth = randomInteger(
        activeRock.value.minHealth,
        activeRock.value.maxHealth,
    );

    maxHealth.value = nextHealth;
    health.value = nextHealth;
    previousMarkedSpot.value = null;
    markedSpot.value = nextMarkedSpot();
    lastResult.value = null;
    cooldownUntil.value = 0;
    cooldownRemainingMilliseconds.value = 0;
}

function canMine(): boolean {
    if (props.isSaving || props.isCompleted) {
        return false;
    }

    if (Date.now() < cooldownUntil.value) {
        lastResult.value = 'cooldown';

        return false;
    }

    return true;
}

function applyDamage(damage: number) {
    cooldownUntil.value = Date.now() + CLICK_COOLDOWN_MILLISECONDS;
    cooldownRemainingMilliseconds.value = CLICK_COOLDOWN_MILLISECONDS;
    health.value = Math.max(0, health.value - damage);

    if (health.value <= 0) {
        emit('complete');
    }
}

function mineRock() {
    if (!canMine()) {
        return;
    }

    lastResult.value = 'rock';
    applyDamage(1);
}

function mineMarkedSpot() {
    if (!canMine()) {
        return;
    }

    lastResult.value = 'spot';
    previousMarkedSpot.value = markedSpot.value;
    markedSpot.value = nextMarkedSpot();
    applyDamage(2);
}
</script>

<template>
    <div
        class="mt-5 flex min-h-[420px] w-full flex-col overflow-hidden rounded-md border border-[#d6cab6] bg-[#eef0df] text-left sm:min-h-[520px] dark:border-[#35332c] dark:bg-[#10140e]"
    >
        <div
            class="relative flex min-h-[340px] flex-1 items-center justify-center overflow-hidden bg-[#dfe2d7] px-5 py-8 dark:bg-[#111513] sm:min-h-[440px]"
        >
            <div
                class="absolute inset-x-0 bottom-0 h-20 bg-[#8b8f82] dark:bg-[#222820]"
            ></div>
            <div
                class="absolute inset-x-0 bottom-16 h-24 bg-gradient-to-t from-[#c2c8b6] to-transparent dark:from-[#1c231c]"
            ></div>
            <div
                class="relative z-10 h-[260px] w-[340px] max-w-full sm:h-[320px] sm:w-[430px]"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-crosshair rounded-[42%] transition disabled:cursor-not-allowed"
                    :disabled="isSaving || isCompleted"
                    aria-label="Mine rock"
                    @click="mineRock"
                >
                    <img
                        :src="activeRock.image"
                        :alt="activeRock.name"
                        class="h-full w-full object-contain drop-shadow-xl"
                        draggable="false"
                    />
                </button>

                <button
                    type="button"
                    class="absolute z-20 flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-[#f7f0c8] bg-[#b9543d] text-[#fff7d8] shadow-[0_0_18px_rgba(185,84,61,0.45)] transition hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSaving || isCompleted"
                    :style="{
                        left: `${markedSpot.x}%`,
                        top: `${markedSpot.y}%`,
                    }"
                    aria-label="Mine marked spot"
                    @click.stop="mineMarkedSpot"
                >
                    <Pickaxe class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div
            class="border-t border-[#d6cab6] bg-[#f9f4e8] px-4 py-3 dark:border-[#35332c] dark:bg-[#151910]"
        >
            <div
                class="flex flex-col gap-3 text-sm font-semibold sm:flex-row sm:items-center sm:justify-between"
            >
                <span>{{ statusLabel }}</span>
                <span class="text-[#68716b] dark:text-[#c6d0c7]">
                    {{ activeRock.name }} | {{ health }} / {{ maxHealth }}
                    health
                </span>
            </div>

            <div
                class="mt-3 overflow-hidden rounded-full border border-[#7f8074] bg-[#eee6d1] dark:border-[#4c5149] dark:bg-[#211f18]"
            >
                <div
                    class="h-3 rounded-full bg-[#8a9a9b] transition-[width] dark:bg-[#a8b4b5]"
                    :style="{
                        width: `${healthPercent}%`,
                    }"
                ></div>
            </div>
        </div>
    </div>
</template>
