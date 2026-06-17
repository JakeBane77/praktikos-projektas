import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { AchievementUnlock } from '@/lib/game';

export function useAchievementUnlockQueue(
    achievementUnlocks: () => AchievementUnlock[],
) {
    const achievementUnlockQueue = ref<AchievementUnlock[]>([]);
    const activeAchievementUnlockIndex = ref(0);

    const currentAchievementUnlock = computed(
        () => achievementUnlockQueue.value[activeAchievementUnlockIndex.value],
    );
    const achievementUnlockCount = computed(
        () => achievementUnlockQueue.value.length,
    );
    const achievementUnlockPosition = computed(() =>
        Math.min(
            activeAchievementUnlockIndex.value + 1,
            achievementUnlockCount.value,
        ),
    );
    const achievementUnlockButtonLabel = computed(() =>
        activeAchievementUnlockIndex.value + 1 < achievementUnlockCount.value
            ? 'Next'
            : 'Done',
    );

    watch(
        achievementUnlocks,
        (nextAchievementUnlocks) => {
            if (
                nextAchievementUnlocks.length === 0 ||
                achievementUnlockQueue.value.length > 0
            ) {
                return;
            }

            achievementUnlockQueue.value = [...nextAchievementUnlocks];
            activeAchievementUnlockIndex.value = 0;
        },
        { immediate: true },
    );

    function advanceAchievementUnlockPopup(): void {
        if (
            activeAchievementUnlockIndex.value + 1 <
            achievementUnlockCount.value
        ) {
            activeAchievementUnlockIndex.value += 1;

            return;
        }

        const seenAchievementUnlockIds = achievementUnlockQueue.value.map(
            (achievementUnlock) => achievementUnlock.id,
        );

        achievementUnlockQueue.value = [];
        activeAchievementUnlockIndex.value = 0;

        if (seenAchievementUnlockIds.length === 0) {
            return;
        }

        router.post(
            '/dashboard/achievements/unlocks/seen',
            {
                ids: seenAchievementUnlockIds,
            },
            {
                preserveScroll: true,
            },
        );
    }

    return {
        achievementUnlockQueue,
        activeAchievementUnlockIndex,
        currentAchievementUnlock,
        achievementUnlockCount,
        achievementUnlockPosition,
        achievementUnlockButtonLabel,
        advanceAchievementUnlockPopup,
    };
}
