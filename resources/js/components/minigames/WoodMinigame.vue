<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { woodTrees } from './woodTrees';
import type { WoodTreeDefinition } from './woodTrees';

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
const lastResult = ref<'perfect' | 'hit' | 'miss' | 'cooldown' | null>(null);
const cooldownUntil = ref(0);
const cooldownRemainingMilliseconds = ref(0);
const activeTree = ref<WoodTreeDefinition>(woodTrees[0]);
const hasSubmittedCompletion = ref(false);

const CLICK_COOLDOWN_MILLISECONDS = 500;
const ANIMATION_FPS = 60;
const ANIMATION_TICK_MILLISECONDS = 1000 / ANIMATION_FPS;
const MAX_ELAPSED_SECONDS = 0.1;

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
        return `Axe cooldown ${Math.ceil(cooldownRemainingMilliseconds.value / 1000)}s`;
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

    if (lastResult.value === 'cooldown') {
        return 'Axe is recovering';
    }

    return 'Ready';
});

let animationInterval: number | undefined;
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
    return randomInteger(activeTree.value.minSpeed, activeTree.value.maxSpeed);
}

function randomTree(): WoodTreeDefinition {
    return woodTrees[randomInteger(0, woodTrees.length - 1)];
}

function resetGame() {
    activeTree.value = randomTree();
    const nextHealth = randomInteger(
        activeTree.value.minHealth,
        activeTree.value.maxHealth,
    );

    maxHealth.value = nextHealth;
    health.value = nextHealth;
    linePercent.value = randomInteger(0, 100);
    direction.value = Math.random() >= 0.5 ? 1 : -1;
    speed.value = randomSpeed();
    lastResult.value = null;
    cooldownUntil.value = 0;
    cooldownRemainingMilliseconds.value = 0;
    hasSubmittedCompletion.value = false;
}

function startAnimation() {
    if (
        typeof window === 'undefined' ||
        props.isCompleted ||
        animationInterval !== undefined
    ) {
        return;
    }

    lastAnimationAt = performance.now();
    animationInterval = window.setInterval(
        animate,
        ANIMATION_TICK_MILLISECONDS,
    );
}

function stopAnimation() {
    if (typeof window === 'undefined' || animationInterval === undefined) {
        return;
    }

    window.clearInterval(animationInterval);
    animationInterval = undefined;
    lastAnimationAt = undefined;
}

function animate() {
    if (props.isCompleted) {
        stopAnimation();

        return;
    }

    const timestamp = performance.now();
    const previousTimestamp = lastAnimationAt ?? timestamp;
    const elapsedSeconds = Math.min(
        MAX_ELAPSED_SECONDS,
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
}

function hit() {
    if (props.isSaving || props.isCompleted || hasSubmittedCompletion.value) {
        return;
    }

    const now = Date.now();

    if (now < cooldownUntil.value) {
        lastResult.value = 'cooldown';

        return;
    }

    cooldownUntil.value = now + CLICK_COOLDOWN_MILLISECONDS;
    cooldownRemainingMilliseconds.value = CLICK_COOLDOWN_MILLISECONDS;

    const distanceFromCenter = Math.abs(linePercent.value - 50);
    let damage = 0;

    if (distanceFromCenter <= activeTree.value.perfectHitRadius) {
        damage = 2;
        lastResult.value = 'perfect';
    } else if (distanceFromCenter <= activeTree.value.hitRadius) {
        damage = 1;
        lastResult.value = 'hit';
    } else {
        lastResult.value = 'miss';

        return;
    }

    health.value = Math.max(0, health.value - damage);

    if (health.value <= 0) {
        hasSubmittedCompletion.value = true;
        emit('complete');
    }
}
</script>

<template>
    <button
        type="button"
        class="mt-5 flex min-h-[420px] w-full cursor-crosshair flex-col overflow-hidden rounded-md border border-[#d6cab6] bg-[#eef0df] text-left transition hover:border-[#b9aa8f] disabled:cursor-not-allowed sm:min-h-[520px] dark:border-[#35332c] dark:bg-[#10140e] dark:hover:border-[#56503f]"
        :disabled="isSaving || isCompleted || hasSubmittedCompletion"
        @click="hit"
    >
        <div
            class="relative flex min-h-[340px] flex-1 items-end justify-center overflow-hidden bg-[#dfe7cc] px-5 pb-7 sm:min-h-[440px] dark:bg-[#11180f]"
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
                <img
                    :src="activeTree.image"
                    :alt="activeTree.name"
                    class="h-56 w-56 object-contain drop-shadow-xl sm:h-72 sm:w-72"
                    draggable="false"
                />
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
            <span class="text-right text-[#637447] dark:text-[#b7d38e]">
                {{ activeTree.name }} | Speed {{ speed.toLocaleString() }}
            </span>
        </div>
    </button>
</template>
