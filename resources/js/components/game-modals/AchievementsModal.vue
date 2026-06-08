<script setup lang="ts">
import { Award, CheckCircle2, Lock, X } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Achievement, AchievementBonus } from '@/lib/game';

const props = defineProps<{
    achievements: Achievement[];
    achievementBonuses: AchievementBonus[];
    hideCompleted: boolean;
}>();

const emit = defineEmits<{
    close: [];
    'update:hideCompleted': [value: boolean];
}>();

const visibleAchievements = computed(() =>
    props.hideCompleted
        ? props.achievements.filter((achievement) => !achievement.isUnlocked)
        : props.achievements,
);

const unlockedAchievementCount = computed(
    () =>
        props.achievements.filter((achievement) => achievement.isUnlocked)
            .length,
);

function setHideCompleted(event: Event): void {
    const target = event.target;

    emit(
        'update:hideCompleted',
        target instanceof HTMLInputElement ? target.checked : false,
    );
}
</script>

<template>
    <section
        class="max-h-[calc(100vh-3rem)] w-full max-w-5xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <header class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="rounded-md bg-[#243627] p-2 text-white">
                    <Award class="h-5 w-5" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Achievements
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">Milestones</h2>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close achievements"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <div
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                Production bonuses unlocked by milestones
            </p>
            <div class="flex flex-wrap items-center gap-4">
                <label
                    class="inline-flex items-center gap-2 text-sm font-semibold text-[#5d6356] dark:text-[#c6c0b3]"
                >
                    <input
                        :checked="hideCompleted"
                        type="checkbox"
                        class="h-4 w-4 rounded border-[#b7aa91] text-[#243627] focus:ring-[#47663b] dark:border-[#554f42]"
                        @change="setHideCompleted"
                    />
                    Hide completed
                </label>
                <p
                    class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                >
                    {{ unlockedAchievementCount }} /
                    {{ achievements.length }} unlocked
                </p>
            </div>
        </div>

        <div class="mt-5 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]">
            <h3 class="text-sm font-semibold">Current bonuses</h3>
            <div
                v-if="achievementBonuses.length > 0"
                class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="bonus in achievementBonuses"
                    :key="bonus.id"
                    class="rounded-md border border-[#e4dac7] px-3 py-2 dark:border-[#35332c]"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold">
                            {{ bonus.label }}
                        </p>
                        <p
                            class="text-sm font-bold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            {{ bonus.bonusLabel }}
                        </p>
                    </div>
                    <p
                        class="mt-1 text-xs font-medium text-[#7a705d] dark:text-[#aaa18f]"
                    >
                        base production
                    </p>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-[#5d6356] dark:text-[#c6c0b3]">
                No active bonuses yet.
            </p>
        </div>

        <div
            v-if="visibleAchievements.length > 0"
            class="mt-5 grid gap-3 lg:grid-cols-2"
        >
            <article
                v-for="achievement in visibleAchievements"
                :key="achievement.id"
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold">
                                {{ achievement.name }}
                            </h3>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-sm px-2 py-1 text-xs font-semibold',
                                    achievement.isUnlocked
                                        ? 'bg-[#dce9d1] text-[#263f20] dark:bg-[#273820] dark:text-[#bde4a5]'
                                        : 'bg-[#e9e1d3] text-[#4e432f] dark:bg-[#24281d] dark:text-[#d8ccb8]',
                                ]"
                            >
                                <CheckCircle2
                                    v-if="achievement.isUnlocked"
                                    class="h-3.5 w-3.5"
                                />
                                <Lock v-else class="h-3.5 w-3.5" />
                                {{
                                    achievement.isUnlocked
                                        ? 'Unlocked'
                                        : 'Locked'
                                }}
                            </span>
                        </div>
                        <p
                            v-if="achievement.description"
                            class="mt-2 text-sm leading-6 text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            {{ achievement.description }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <div
                        class="flex items-center justify-between gap-4 text-xs font-semibold text-[#7a705d] dark:text-[#aaa18f]"
                    >
                        <span>Progress</span>
                        <span>{{ achievement.progressLabel }}</span>
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-[#e9e1d3] dark:bg-[#24281d]"
                    >
                        <div
                            class="h-full rounded-full bg-[#47663b] dark:bg-[#9dcc84]"
                            :style="{
                                width: `${achievement.progressPercent}%`,
                            }"
                        />
                    </div>
                </div>

                <p
                    class="mt-3 text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                >
                    {{ achievement.rewardLabel }}
                </p>
            </article>
        </div>

        <p
            v-else
            class="mt-5 rounded-md border border-[#e4dac7] p-4 text-sm text-[#5d6356] dark:border-[#35332c] dark:text-[#c6c0b3]"
        >
            {{
                achievements.length > 0
                    ? 'Completed achievements hidden.'
                    : 'No achievements configured yet.'
            }}
        </p>
    </section>
</template>
