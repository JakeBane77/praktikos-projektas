<script setup lang="ts">
import { PackagePlus, X } from 'lucide-vue-next';
import type { Component } from 'vue';
import { formatExactNumber } from '@/lib/game';
import type { OfflineProgress, ResourceKey } from '@/lib/game';

defineProps<{
    offlineProgress: OfflineProgress;
    durationLabel: string;
    resourceRows: {
        key: ResourceKey;
        label: string;
        amount: number;
        icon: Component;
    }[];
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <section
        class="max-h-[calc(100vh-3rem)] w-full max-w-md overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <header class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="rounded-md bg-[#243627] p-3 text-white">
                    <PackagePlus class="h-6 w-6" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Offline progress
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">Welcome back</h2>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close offline progress"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <div
            class="mt-5 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
        >
            <p class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]">
                Time away
            </p>
            <p class="mt-2 text-3xl font-bold">
                {{ durationLabel }}
            </p>
        </div>

        <div class="mt-4 grid gap-2">
            <div
                v-for="resource in resourceRows"
                :key="resource.key"
                class="flex items-center justify-between gap-4 rounded-md border border-[#e4dac7] p-3 dark:border-[#35332c]"
            >
                <div class="flex items-center gap-3">
                    <component
                        :is="resource.icon"
                        class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                    />
                    <p class="font-semibold">
                        {{ resource.label }}
                    </p>
                </div>
                <p
                    class="text-right font-bold text-[#47663b] dark:text-[#9dcc84]"
                >
                    +{{ formatExactNumber(resource.amount) }}
                </p>
            </div>
        </div>

        <div
            class="mt-5 flex items-center justify-between gap-4 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
        >
            <p class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]">
                +{{ formatExactNumber(offlineProgress.total) }} total resources
            </p>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                @click="emit('close')"
            >
                Continue
            </button>
        </div>
    </section>
</template>
