<script setup lang="ts">
import { Award, CheckCircle2 } from 'lucide-vue-next';
import type { AchievementUnlock } from '@/lib/game';

defineProps<{
    achievementUnlock: AchievementUnlock;
    position: number;
    count: number;
    buttonLabel: string;
}>();

const emit = defineEmits<{
    advance: [];
}>();
</script>

<template>
    <section
        class="max-h-[calc(100vh-3rem)] w-full max-w-md overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <div class="flex items-start gap-4">
            <div class="rounded-md bg-[#243627] p-3 text-white">
                <Award class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
                <p
                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                >
                    Achievement unlocked
                </p>
                <h2 class="mt-1 text-2xl font-bold">
                    {{ achievementUnlock.name }}
                </h2>
                <p
                    v-if="achievementUnlock.description"
                    class="mt-3 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                >
                    {{ achievementUnlock.description }}
                </p>
            </div>
        </div>

        <div
            class="mt-5 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2
                    class="h-5 w-5 text-[#47663b] dark:text-[#9dcc84]"
                />
                <p class="text-sm font-semibold">Bonus activated</p>
            </div>
            <p
                class="mt-2 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
            >
                {{ achievementUnlock.rewardLabel }}
            </p>
        </div>

        <div
            class="mt-5 flex items-center justify-between gap-4 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
        >
            <p class="text-sm font-semibold text-[#696250] dark:text-[#b6ae9d]">
                {{ position }} / {{ count }}
            </p>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                @click="emit('advance')"
            >
                {{ buttonLabel }}
            </button>
        </div>
    </section>
</template>
