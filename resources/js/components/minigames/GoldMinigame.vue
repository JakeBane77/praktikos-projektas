<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { goldRunThemes } from './goldRuns';
import type { GoldObstacleDefinition, GoldRunTheme } from './goldRuns';

const props = defineProps<{
    isSaving: boolean;
    isCompleted: boolean;
}>();

const emit = defineEmits<{
    complete: [];
}>();

type Lane = 0 | 1 | 2;
type RunResult = 'hit' | 'move' | 'finished' | null;

type RunnerObstacle = {
    id: number;
    lane: Lane;
    y: number;
    definition: GoldObstacleDefinition;
};

type ImpactEffect = {
    id: number;
    lane: Lane;
    y: number;
    createdAt: number;
    obstacleName: string;
};

const LANES: Lane[] = [0, 1, 2];
const STARTING_LANE: Lane = 1;
const OBSTACLE_START_Y = -10;
const OBSTACLE_REMOVE_Y = 118;
const COLLISION_MIN_Y = 73;
const COLLISION_MAX_Y = 89;
const IMPACT_DURATION_MS = 420;
const SPEED_PENALTY_RECOVERY_PER_SECOND = 7;
const ANIMATION_FPS = 60;
const ANIMATION_TICK_MILLISECONDS = 1000 / ANIMATION_FPS;
const MAX_ELAPSED_SECONDS = 0.1;
const MIN_OBSTACLE_SPAWN_GAP_Y = 38;
const OBSTACLE_SPAWN_RETRY_DELAY_MS = 150;

const activeTheme = ref<GoldRunTheme>(goldRunThemes[0]);
const playerLane = ref<Lane>(STARTING_LANE);
const distance = ref(0);
const speed = ref(0);
const obstacles = ref<RunnerObstacle[]>([]);
const impacts = ref<ImpactEffect[]>([]);
const lastResult = ref<RunResult>(null);
const hasSubmittedCompletion = ref(false);
const impactUntil = ref(0);
const speedPenalty = ref(0);

let animationInterval: number | undefined;
let lastAnimationAt: number | undefined;
let nextObstacleId = 1;
let nextImpactId = 1;
let nextSpawnAt = 0;

const progressPercent = computed(() =>
    activeTheme.value.targetDistance > 0
        ? Math.min(
              100,
              Math.floor(
                  (distance.value / activeTheme.value.targetDistance) * 100,
              ),
          )
        : 0,
);

const distanceRemaining = computed(() =>
    Math.max(0, Math.ceil(activeTheme.value.targetDistance - distance.value)),
);

const isImpactActive = computed(() => impactUntil.value > 0);

const roadMotionStyle = computed(() => {
    const speedRatio = Math.min(
        1,
        Math.max(0, speed.value / activeTheme.value.baseSpeed),
    );

    return {
        backgroundPosition: `center ${Math.floor(distance.value * 3) % 96}px`,
        opacity: `${0.22 + speedRatio * 0.4}`,
    };
});

const statusLabel = computed(() => {
    if (props.isSaving) {
        return 'Saving reward...';
    }

    if (props.isCompleted || hasSubmittedCompletion.value) {
        return 'Run complete';
    }

    if (lastResult.value === 'hit') {
        return 'Obstacle hit: speed reduced';
    }

    if (lastResult.value === 'move') {
        return 'Lane changed';
    }

    return 'Avoid obstacles until the finish';
});

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

function randomTheme(): GoldRunTheme {
    return goldRunThemes[randomInteger(0, goldRunThemes.length - 1)];
}

function randomObstacle(): GoldObstacleDefinition {
    return activeTheme.value.obstacles[
        randomInteger(0, activeTheme.value.obstacles.length - 1)
    ];
}

function randomLane(): Lane {
    return LANES[randomInteger(0, LANES.length - 1)];
}

function laneCenterPercent(lane: Lane): number {
    return 16.666 + lane * 33.333;
}

function obstacleScale(y: number): number {
    return Math.min(1.2, Math.max(0.55, 0.55 + y / 150));
}

function currentTargetSpeed(): number {
    const progressRatio = Math.min(
        1,
        Math.max(0, distance.value / activeTheme.value.targetDistance),
    );
    const easedProgress = 1 - Math.pow(1 - progressRatio, 2);

    const targetSpeed =
        activeTheme.value.minSpeed +
        (activeTheme.value.baseSpeed - activeTheme.value.minSpeed) *
            easedProgress;

    return Math.max(
        activeTheme.value.minSpeed,
        targetSpeed - speedPenalty.value,
    );
}

function scheduleNextObstacle(timestamp: number) {
    nextSpawnAt =
        timestamp +
        randomInteger(
            activeTheme.value.minSpawnDelayMs,
            activeTheme.value.maxSpawnDelayMs,
        );
}

function hasObstacleSpawnClearance(): boolean {
    return obstacles.value.every(
        (obstacle) =>
            obstacle.y - OBSTACLE_START_Y >= MIN_OBSTACLE_SPAWN_GAP_Y,
    );
}

function spawnObstacle(timestamp: number) {
    if (!hasObstacleSpawnClearance()) {
        nextSpawnAt = timestamp + OBSTACLE_SPAWN_RETRY_DELAY_MS;

        return;
    }

    obstacles.value.push({
        id: nextObstacleId,
        lane: randomLane(),
        y: OBSTACLE_START_Y,
        definition: randomObstacle(),
    });
    nextObstacleId += 1;
    scheduleNextObstacle(timestamp);
}

function resetGame() {
    activeTheme.value = randomTheme();
    playerLane.value = STARTING_LANE;
    distance.value = 0;
    speed.value = activeTheme.value.minSpeed;
    obstacles.value = [];
    impacts.value = [];
    lastResult.value = null;
    hasSubmittedCompletion.value = false;
    impactUntil.value = 0;
    speedPenalty.value = 0;
    nextObstacleId = 1;
    nextImpactId = 1;
    nextSpawnAt = 0;
    lastAnimationAt = undefined;
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
    if (props.isCompleted || hasSubmittedCompletion.value) {
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

    if (nextSpawnAt === 0) {
        scheduleNextObstacle(timestamp);
    }

    if (timestamp >= nextSpawnAt) {
        spawnObstacle(timestamp);
    }

    speedPenalty.value = Math.max(
        0,
        speedPenalty.value - SPEED_PENALTY_RECOVERY_PER_SECOND * elapsedSeconds,
    );

    const targetSpeed = currentTargetSpeed();
    const accelerationAmount = Math.min(
        1,
        activeTheme.value.accelerationResponsiveness * elapsedSeconds,
    );

    speed.value += (targetSpeed - speed.value) * accelerationAmount;
    distance.value = Math.min(
        activeTheme.value.targetDistance,
        distance.value + speed.value * elapsedSeconds,
    );
    impactUntil.value = Math.max(0, impactUntil.value - elapsedSeconds * 1000);
    impacts.value = impacts.value.filter(
        (impact) => timestamp - impact.createdAt <= IMPACT_DURATION_MS,
    );

    const nextObstacles: RunnerObstacle[] = [];

    for (const obstacle of obstacles.value) {
        const nextY =
            obstacle.y + activeTheme.value.obstacleSpeed * elapsedSeconds;

        if (
            obstacle.lane === playerLane.value &&
            nextY >= COLLISION_MIN_Y &&
            nextY <= COLLISION_MAX_Y
        ) {
            speed.value = Math.max(
                activeTheme.value.minSpeed,
                speed.value - activeTheme.value.slowdownOnHit,
            );
            speedPenalty.value = Math.max(
                speedPenalty.value,
                activeTheme.value.slowdownOnHit,
            );
            lastResult.value = 'hit';
            impactUntil.value = IMPACT_DURATION_MS;
            impacts.value.push({
                id: nextImpactId,
                lane: obstacle.lane,
                y: nextY,
                createdAt: timestamp,
                obstacleName: obstacle.definition.name,
            });
            nextImpactId += 1;

            continue;
        }

        if (nextY <= OBSTACLE_REMOVE_Y) {
            nextObstacles.push({
                ...obstacle,
                y: nextY,
            });
        }
    }

    obstacles.value = nextObstacles;

    if (distance.value >= activeTheme.value.targetDistance) {
        completeGame();
    }
}

function setLane(lane: Lane) {
    if (props.isSaving || props.isCompleted || hasSubmittedCompletion.value) {
        return;
    }

    playerLane.value = lane;
    lastResult.value = 'move';
}

function completeGame() {
    if (props.isSaving || props.isCompleted || hasSubmittedCompletion.value) {
        return;
    }

    hasSubmittedCompletion.value = true;
    lastResult.value = 'finished';
    stopAnimation();
    emit('complete');
}
</script>

<template>
    <div
        class="mt-5 flex min-h-[420px] w-full flex-col overflow-hidden rounded-md border border-[#d6cab6] bg-[#eef0df] text-left sm:min-h-[560px] dark:border-[#35332c] dark:bg-[#10140e]"
    >
        <div
            class="relative min-h-[340px] flex-1 overflow-hidden px-3 pt-4 pb-0 sm:min-h-[470px] sm:px-5"
            :class="[
                activeTheme.backgroundClass,
                isImpactActive ? 'gold-impact-scene' : '',
            ]"
        >
            <div
                v-if="isImpactActive"
                class="gold-impact-flash pointer-events-none absolute inset-0 z-30 bg-[#f05f45]/15"
            ></div>
            <div
                class="absolute inset-x-0 bottom-0 h-[58%]"
                :class="activeTheme.horizonClass"
            ></div>
            <div
                class="absolute inset-x-[10%] top-12 bottom-0 isolate grid grid-cols-3 overflow-hidden rounded-t-[42%] border-x border-t shadow-[0_-18px_45px_rgba(0,0,0,0.16)]"
                :class="activeTheme.roadClass"
            >
                <div
                    class="gold-road-motion pointer-events-none absolute inset-0 z-0"
                    :style="roadMotionStyle"
                ></div>
                <div
                    class="pointer-events-none absolute inset-0 z-10 grid grid-cols-3"
                >
                    <div
                        class="border-r border-[#fff1b8]/55 dark:border-[#d8c28e]/35"
                    ></div>
                    <div
                        class="border-r border-[#fff1b8]/55 dark:border-[#d8c28e]/35"
                    ></div>
                    <div></div>
                </div>
                <button
                    v-for="laneOption in LANES"
                    :key="laneOption"
                    type="button"
                    class="relative z-20 cursor-pointer border-l first:border-l-0 disabled:cursor-not-allowed"
                    :class="activeTheme.laneClass"
                    :disabled="
                        isSaving || isCompleted || hasSubmittedCompletion
                    "
                    :aria-label="`Move to lane ${laneOption + 1}`"
                    @click="setLane(laneOption)"
                >
                    <span
                        class="absolute inset-x-1/2 top-0 bottom-0 w-px -translate-x-1/2 border-l border-dashed opacity-45"
                        :class="activeTheme.laneClass"
                    ></span>
                </button>
            </div>

            <div
                class="pointer-events-none absolute inset-x-[10%] top-12 bottom-0 z-20"
            >
                <img
                    v-for="obstacle in obstacles"
                    :key="obstacle.id"
                    :src="obstacle.definition.image"
                    :alt="obstacle.definition.name"
                    class="absolute h-16 w-16 object-contain drop-shadow-lg sm:h-20 sm:w-20"
                    draggable="false"
                    :style="{
                        left: `${laneCenterPercent(obstacle.lane)}%`,
                        top: `${obstacle.y}%`,
                        transform: `translate(-50%, -50%) scale(${obstacleScale(obstacle.y)})`,
                    }"
                />

                <div
                    v-for="impact in impacts"
                    :key="impact.id"
                    class="gold-impact-burst absolute z-30 flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-[#fff2bf] bg-[#b4472f]/80 text-[10px] font-bold tracking-wider text-white uppercase shadow-[0_0_24px_rgba(180,71,47,0.55)] sm:h-20 sm:w-20"
                    :style="{
                        left: `${laneCenterPercent(impact.lane)}%`,
                        top: `${impact.y}%`,
                    }"
                    :aria-label="`Impact with ${impact.obstacleName}`"
                >
                    Impact
                </div>

                <div
                    class="absolute bottom-4 h-28 w-32 -translate-x-1/2 transition-[left] duration-150 ease-out sm:h-36 sm:w-44"
                    :style="{
                        left: `${laneCenterPercent(playerLane)}%`,
                    }"
                >
                    <img
                        :src="activeTheme.pullerImage"
                        :alt="activeTheme.pullerName"
                        class="h-full w-full object-contain drop-shadow-xl"
                        :class="isImpactActive ? 'gold-impact-cart' : ''"
                        draggable="false"
                    />
                </div>
            </div>
        </div>

        <div
            class="border-t border-[#d6cab6] bg-[#f9f4e8] px-4 py-3 dark:border-[#35332c] dark:bg-[#151910]"
        >
            <div
                class="flex flex-col gap-3 text-sm font-semibold sm:flex-row sm:items-center sm:justify-between"
            >
                <span>{{ statusLabel }}</span>
                <span class="text-[#6a5f49] dark:text-[#cfc4ad]">
                    {{ activeTheme.name }} | Speed
                    {{ Math.round(speed).toLocaleString() }}
                </span>
            </div>

            <div
                class="mt-3 flex flex-col gap-2 text-xs font-semibold text-[#696250] sm:flex-row sm:items-center sm:justify-between dark:text-[#b6ae9d]"
            >
                <span>
                    {{ Math.floor(distance).toLocaleString() }} /
                    {{ activeTheme.targetDistance.toLocaleString() }} m
                </span>
                <span
                    >{{ distanceRemaining.toLocaleString() }} m remaining</span
                >
            </div>

            <div
                class="mt-3 overflow-hidden rounded-full border border-[#a69161] bg-[#efe5ca] dark:border-[#4f4636] dark:bg-[#211f18]"
            >
                <div
                    class="h-3 rounded-full bg-[#c99a38] transition-[width] dark:bg-[#d8ae4e]"
                    :style="{
                        width: `${progressPercent}%`,
                    }"
                ></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gold-impact-scene {
    animation: gold-impact-scene 180ms steps(11, end) 2;
}

.gold-impact-cart {
    animation: gold-impact-cart 260ms steps(16, end);
}

.gold-impact-flash {
    animation: gold-impact-flash 420ms steps(25, end) forwards;
}

.gold-impact-burst {
    animation: gold-impact-burst 420ms steps(25, end) forwards;
}

.gold-road-motion {
    background-image:
        repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent 26px,
            rgb(255 244 190 / 0.72) 26px,
            rgb(255 244 190 / 0.72) 42px,
            transparent 42px,
            transparent 96px
        ),
        linear-gradient(
            to right,
            rgb(255 255 255 / 0.22),
            transparent 10%,
            transparent 90%,
            rgb(255 255 255 / 0.18)
        );
    background-size:
        100% 96px,
        100% 100%;
    mask-image: linear-gradient(
        to bottom,
        transparent 0,
        rgb(0 0 0 / 0.35) 14%,
        black 100%
    );
}

@keyframes gold-impact-scene {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-4px);
    }
    75% {
        transform: translateX(4px);
    }
}

@keyframes gold-impact-cart {
    0% {
        transform: rotate(0deg) scale(1);
    }
    30% {
        transform: rotate(-4deg) scale(0.96);
    }
    65% {
        transform: rotate(3deg) scale(1.02);
    }
    100% {
        transform: rotate(0deg) scale(1);
    }
}

@keyframes gold-impact-flash {
    0% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

@keyframes gold-impact-burst {
    0% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(0.75);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.35);
    }
}
</style>
