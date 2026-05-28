<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    isSaving: boolean;
    isCompleted: boolean;
}>();

const emit = defineEmits<{
    complete: [];
}>();

const linePercent = ref(50);
const direction = ref(1);
const speed = ref(0);
const health = ref(0);
const maxHealth = ref(0);
const lastResult = ref<'perfect' | 'hit' | 'miss' | null>(null);
const cooldownUntil = ref(0);
const cooldownRemainingMilliseconds = ref(0);

const MIN_SPEED = 45;
const MAX_SPEED = 95;
const MIN_HEALTH = 5;
const MAX_HEALTH = 8;
const PERFECT_RADIUS = 3;
const HIT_RADIUS = 10;
const MISS_COOLDOWN_MILLISECONDS = 500;

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
        return `Miss cooldown ${Math.ceil(cooldownRemainingMilliseconds.value / 1000)}s`;
    }

    if (lastResult.value === 'perfect') {
        return 'Perfect hit: 2 damage';
    }

    if (lastResult.value === 'hit') {
        return 'Glancing hit: 1 damage';
    }

    if (lastResult.value === 'miss') {
        return 'Miss';
    }

    return 'Ready';
});

let animationFrame: number | undefined;
let lastAnimationAt: number | undefined;

watch(
    () => props.isCompleted,
    (isCompleted) => {
        if (isCompleted) {
            stopAnimation();

            return;
        }

        resetGame();
        startAnimation();
    },
);

onMounted(() => {
    resetGame();
    startAnimation();
});

onBeforeUnmount(() => {
    stopAnimation();
});

function randomInteger(minimum: number, maximum: number): number {
    return Math.floor(Math.random() * (maximum - minimum + 1)) + minimum;
}

function randomSpeed(): number {
    return randomInteger(MIN_SPEED, MAX_SPEED);
}

function resetGame() {
    const nextHealth = randomInteger(MIN_HEALTH, MAX_HEALTH);

    maxHealth.value = nextHealth;
    health.value = nextHealth;
    linePercent.value = randomInteger(0, 100);
    direction.value = Math.random() >= 0.5 ? 1 : -1;
    speed.value = randomSpeed();
    lastResult.value = null;
    cooldownUntil.value = 0;
    cooldownRemainingMilliseconds.value = 0;
}

function startAnimation() {
    if (
        typeof window === 'undefined' ||
        props.isCompleted ||
        animationFrame !== undefined
    ) {
        return;
    }

    lastAnimationAt = undefined;
    animationFrame = window.requestAnimationFrame(animate);
}

function stopAnimation() {
    if (typeof window === 'undefined' || animationFrame === undefined) {
        return;
    }

    window.cancelAnimationFrame(animationFrame);
    animationFrame = undefined;
    lastAnimationAt = undefined;
}

function animate(timestamp: number) {
    if (props.isCompleted) {
        animationFrame = undefined;
        lastAnimationAt = undefined;

        return;
    }

    const previousTimestamp = lastAnimationAt ?? timestamp;
    const elapsedSeconds = Math.min(
        0.05,
        (timestamp - previousTimestamp) / 1000,
    );

    lastAnimationAt = timestamp;
    linePercent.value += direction.value * speed.value * elapsedSeconds;

    if (linePercent.value >= 100) {
        linePercent.value = 100;
        direction.value = -1;
        speed.value = randomSpeed();
    }

    if (linePercent.value <= 0) {
        linePercent.value = 0;
        direction.value = 1;
        speed.value = randomSpeed();
    }

    cooldownRemainingMilliseconds.value = Math.max(
        0,
        cooldownUntil.value - Date.now(),
    );

    animationFrame = window.requestAnimationFrame(animate);
}

function hit() {
    if (props.isSaving || props.isCompleted) {
        return;
    }

    const now = Date.now();

    if (now < cooldownUntil.value) {
        return;
    }

    const distanceFromCenter = Math.abs(linePercent.value - 50);
    let damage = 0;

    if (distanceFromCenter <= PERFECT_RADIUS) {
        damage = 2;
        lastResult.value = 'perfect';
    } else if (distanceFromCenter <= HIT_RADIUS) {
        damage = 1;
        lastResult.value = 'hit';
    } else {
        lastResult.value = 'miss';
        cooldownUntil.value = now + MISS_COOLDOWN_MILLISECONDS;
        cooldownRemainingMilliseconds.value = MISS_COOLDOWN_MILLISECONDS;

        return;
    }

    health.value = Math.max(0, health.value - damage);

    if (health.value <= 0) {
        emit('complete');
    }
}
</script>

<template>
    <button
        type="button"
        class="mt-5 flex min-h-[420px] w-full cursor-crosshair flex-col overflow-hidden rounded-md border border-[#d6cab6] bg-[#eef0df] text-left transition hover:border-[#b9aa8f] disabled:cursor-not-allowed sm:min-h-[520px] dark:border-[#35332c] dark:bg-[#10140e] dark:hover:border-[#56503f]"
        :disabled="isSaving || isCompleted"
        @click="hit"
    >
        <div
            class="relative flex min-h-[340px] flex-1 items-end justify-center overflow-hidden bg-[#dfe7cc] px-5 pb-7 dark:bg-[#11180f] sm:min-h-[440px]"
        >
            <div
                class="absolute inset-x-0 bottom-0 h-16 bg-[#7c8d5d] dark:bg-[#25311f]"
            ></div>
            <div
                class="absolute inset-x-0 bottom-12 h-20 bg-gradient-to-t from-[#c6d5a4] to-transparent dark:from-[#1d2918]"
            ></div>
            <div
                class="absolute top-5 left-1/2 h-[calc(100%-2.5rem)] w-[20%] -translate-x-1/2 rounded-full bg-[#d5bd7d]/35 dark:bg-[#6b5832]/35"
            ></div>
            <div
                class="absolute top-5 left-1/2 h-[calc(100%-2.5rem)] w-[6%] -translate-x-1/2 rounded-full bg-[#eddd93]/70 dark:bg-[#b49344]/55"
            ></div>
            <div
                class="absolute top-5 bottom-4 w-1 -translate-x-1/2 rounded-full bg-[#f9faf4] shadow-[0_0_18px_rgba(249,250,244,0.9)] dark:bg-[#f7f1d6]"
                :style="{
                    left: `${linePercent}%`,
                }"
            ></div>

            <div class="relative z-10 flex flex-col items-center">
                <div class="relative h-48 w-56 sm:h-64 sm:w-72">
                    <div
                        class="absolute bottom-0 left-1/2 h-24 w-10 -translate-x-1/2 rounded-t-lg bg-[#8d5f32] shadow-inner dark:bg-[#6e4726]"
                    ></div>
                    <div
                        class="absolute bottom-16 left-1/2 h-20 w-28 -translate-x-1/2 rounded-full bg-[#3d733c] shadow-lg dark:bg-[#28552c]"
                    ></div>
                    <div
                        class="absolute bottom-24 left-5 h-20 w-24 rounded-full bg-[#4d8846] shadow-md dark:bg-[#326235]"
                    ></div>
                    <div
                        class="absolute right-4 bottom-24 h-20 w-24 rounded-full bg-[#2f6634] shadow-md dark:bg-[#214b29]"
                    ></div>
                    <div
                        class="absolute bottom-[7.75rem] left-1/2 h-24 w-28 -translate-x-1/2 rounded-full bg-[#5a994c] shadow-md dark:bg-[#3d7138]"
                    ></div>
                    <div
                        class="absolute bottom-8 left-1/2 h-4 w-20 -translate-x-1/2 rounded-full bg-[#71451f]/50"
                    ></div>
                </div>
                <div
                    class="mt-3 w-44 overflow-hidden rounded-full border border-[#7d5b36] bg-[#f4e8c6] dark:border-[#80633f] dark:bg-[#211d14]"
                >
                    <div
                        class="h-3 rounded-full bg-[#6aa84f] transition-[width] dark:bg-[#8dbf64]"
                        :style="{
                            width: `${healthPercent}%`,
                        }"
                    ></div>
                </div>
                <p
                    class="mt-2 text-center text-sm font-semibold text-[#314020] dark:text-[#d6e6bc]"
                >
                    {{ health }} / {{ maxHealth }} health
                </p>
            </div>
        </div>
        <div
            class="flex w-full items-center justify-between gap-3 border-t border-[#d6cab6] bg-[#f9f4e8] px-4 py-3 text-sm font-semibold dark:border-[#35332c] dark:bg-[#151910]"
        >
            <span>{{ statusLabel }}</span>
            <span class="text-[#637447] dark:text-[#b7d38e]">
                Speed {{ speed.toLocaleString() }}
            </span>
        </div>
    </button>
</template>
