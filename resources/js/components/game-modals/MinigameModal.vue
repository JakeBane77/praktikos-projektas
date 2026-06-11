<script setup lang="ts">
import { Gamepad2, Trophy, X } from 'lucide-vue-next';
import type { Component } from 'vue';
import { formatExactNumber, formatGameNumber } from '@/lib/game';
import type { Minigame, ResourceKey } from '@/lib/game';

defineProps<{
    minigame: Minigame;
    minigameComponent: Component;
    activeMinigameResource: ResourceKey | null;
    hasWon: boolean;
    currentResourceAmount: number;
    showLeaderboardButton?: boolean;
    resourceNumberLabel: (key: string, value: number) => string;
    resourceNumberTitle: (key: string) => string;
}>();

const emit = defineEmits<{
    close: [];
    complete: [];
    continue: [];
    'open-leaderboard': [];
    'toggle-resource-number': [key: string];
}>();

function formatStaminaCooldown(seconds: number): string {
    if (seconds <= 0) {
        return 'Ready';
    }

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    if (minutes <= 0) {
        return `${remainingSeconds}s`;
    }

    return `${minutes}m ${remainingSeconds.toString().padStart(2, '0')}s`;
}
</script>

<template>
    <section
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
                    {{ minigame.label }}
                </h2>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button
                    v-if="showLeaderboardButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                    :disabled="activeMinigameResource !== null"
                    @click="emit('open-leaderboard')"
                >
                    <Trophy class="h-4 w-4" />
                    Leaderboard
                </button>
                <button
                    type="button"
                    class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] disabled:cursor-not-allowed disabled:opacity-60 dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                    aria-label="Close minigame"
                    :disabled="activeMinigameResource !== null"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </header>

        <div class="relative">
            <component
                :is="minigameComponent"
                :key="minigame.resource"
                :is-saving="activeMinigameResource === minigame.resource"
                :is-completed="hasWon"
                @complete="emit('complete')"
            />
            <button
                v-if="hasWon && activeMinigameResource === null"
                type="button"
                class="absolute inset-0 z-50 cursor-pointer rounded-md bg-transparent"
                aria-label="Continue playing"
                @click="emit('continue')"
            ></button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <p
                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                >
                    Stamina
                </p>
                <p class="mt-2 text-xl font-bold">
                    {{ minigame.stamina.label }}
                </p>
                <p class="mt-1 text-xs text-[#696250] dark:text-[#b6ae9d]">
                    {{
                        minigame.stamina.isAvailable
                            ? `${minigame.stamina.current} completions ready`
                            : `Ready in ${formatStaminaCooldown(
                                  minigame.stamina.availableInSeconds,
                              )}`
                    }}
                </p>
            </div>
            <div
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <p
                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                >
                    Reward
                </p>
                <p class="mt-2 text-xl font-bold">
                    {{ minigame.rewardLabel }}
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
                <button
                    type="button"
                    class="mt-2 block max-w-full cursor-pointer text-left text-xl font-bold break-words"
                    :aria-label="`Current ${minigame.resource} resources: ${formatExactNumber(currentResourceAmount)}`"
                    :title="
                        resourceNumberTitle(
                            `minigame-current-${minigame.resource}`,
                        )
                    "
                    @click="
                        emit(
                            'toggle-resource-number',
                            `minigame-current-${minigame.resource}`,
                        )
                    "
                >
                    {{
                        resourceNumberLabel(
                            `minigame-current-${minigame.resource}`,
                            currentResourceAmount,
                        )
                    }}
                </button>
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
                    {{ formatGameNumber(minigame.completions) }}
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
                    {{ formatGameNumber(minigame.resourcesGained) }}
                </p>
            </div>
        </div>

        <div
            v-if="hasWon"
            class="mt-5 flex flex-col gap-3 border-t border-[#e4dac7] pt-4 sm:flex-row sm:justify-end dark:border-[#35332c]"
        >
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                @click="emit('continue')"
            >
                <Gamepad2 class="h-4 w-4" />
                Continue playing
            </button>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-[#b7aa91] px-4 py-2.5 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                @click="emit('close')"
            >
                Stop playing
            </button>
        </div>
    </section>
</template>
