<script setup lang="ts">
import { RotateCcw, X } from 'lucide-vue-next';
import { formatGameNumber } from '@/lib/game';

defineProps<{
    requirementLabel: string;
    currentRoadLength: number;
    prestigeCountAfterReset: number;
    isPrestiging: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <section
        class="max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <header class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="rounded-md bg-[#5c3b25] p-3 text-white">
                    <RotateCcw class="h-6 w-6" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Prestige
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">
                        Begin a new settlement
                    </h2>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close prestige confirmation"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <p class="mt-5 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]">
            Your current resources, buildings, and roads will reset to zero.
            Your unlocked achievements, achievement bonuses, lifetime resources,
            and prestige count stay.
        </p>

        <div
            class="mt-5 grid gap-3 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
        >
            <div class="flex items-center justify-between gap-4">
                <p
                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                >
                    Required road length
                </p>
                <p class="text-sm font-bold">{{ requirementLabel }} km</p>
            </div>
            <div class="flex items-center justify-between gap-4">
                <p
                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                >
                    Current road length
                </p>
                <p class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]">
                    {{ formatGameNumber(currentRoadLength) }} km
                </p>
            </div>
            <div class="flex items-center justify-between gap-4">
                <p
                    class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]"
                >
                    Prestiges after reset
                </p>
                <p class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]">
                    {{ formatGameNumber(prestigeCountAfterReset) }}
                </p>
            </div>
        </div>

        <div
            class="mt-5 flex flex-col-reverse gap-3 border-t border-[#e4dac7] pt-4 sm:flex-row sm:justify-end dark:border-[#35332c]"
        >
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-[#b7aa91] px-4 py-2.5 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                @click="emit('close')"
            >
                Cancel
            </button>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#5c3b25] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#472d1c] disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isPrestiging"
                @click="emit('confirm')"
            >
                <RotateCcw class="h-4 w-4" />
                {{ isPrestiging ? 'Prestiging...' : 'Confirm prestige' }}
            </button>
        </div>
    </section>
</template>
