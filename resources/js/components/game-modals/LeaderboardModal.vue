<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { formatExactNumber, formatGameNumber } from '@/lib/game';
import type { Leaderboard } from '@/lib/game';

defineProps<{
    leaderboards: Leaderboard[];
    selectedLeaderboard: Leaderboard;
}>();

const emit = defineEmits<{
    close: [];
    'select-leaderboard': [key: string];
}>();
</script>

<template>
    <section
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
                @click="emit('close')"
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
                @click="emit('select-leaderboard', leaderboard.key)"
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
                    #{{ formatExactNumber(selectedLeaderboard.currentRank) }}
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
                    {{ formatGameNumber(selectedLeaderboard.currentValue) }}
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
                class="grid grid-cols-[4.5rem_1fr_8rem] gap-3 border-b border-[#e4dac7] bg-[#f6f0e5] px-4 py-3 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:bg-[#151910] dark:text-[#b6ae9d]"
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

            <div v-else class="divide-y divide-[#e4dac7] dark:divide-[#35332c]">
                <div
                    v-for="entry in selectedLeaderboard.entries"
                    :key="`${selectedLeaderboard.key}-${entry.userId}`"
                    class="grid grid-cols-[4.5rem_1fr_8rem] items-center gap-3 px-4 py-3 text-sm"
                    :class="
                        entry.isCurrentUser
                            ? 'bg-[#edf6e8] text-[#243627] dark:bg-[#1d2a17] dark:text-[#d7edc5]'
                            : ''
                    "
                >
                    <span class="font-bold">
                        #{{ formatExactNumber(entry.rank) }}
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
                        {{ formatGameNumber(entry.value) }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
