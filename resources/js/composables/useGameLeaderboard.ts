import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';
import type { DashboardGameData, Leaderboard } from '@/lib/game';

type GameModalRef = Ref<string | null>;

export function useGameLeaderboard(options: {
    leaderboards: () => DashboardGameData['leaderboards'];
    activeGameModal: GameModalRef;
    isBlocked?: () => boolean;
    beforeOpen?: () => void;
}) {
    let leaderboardReloadTimeout: ReturnType<typeof setTimeout> | null = null;
    const selectedLeaderboardKey = ref(options.leaderboards().defaultKey);
    const leaderboards = computed<Leaderboard[]>(
        () => options.leaderboards().boards,
    );
    const defaultLeaderboard = computed<Leaderboard | null>(
        () =>
            leaderboards.value.find(
                (leaderboard) =>
                    leaderboard.key === options.leaderboards().defaultKey,
            ) ??
            leaderboards.value[0] ??
            null,
    );
    const selectedLeaderboard = computed<Leaderboard | null>(
        () =>
            leaderboards.value.find(
                (leaderboard) =>
                    leaderboard.key === selectedLeaderboardKey.value,
            ) ??
            defaultLeaderboard.value ??
            null,
    );
    const isLeaderboardModalOpen = computed(
        () =>
            options.activeGameModal.value === 'leaderboard' &&
            selectedLeaderboard.value !== null,
    );

    function scheduleLeaderboardReload(): void {
        if (leaderboardReloadTimeout !== null) {
            clearTimeout(leaderboardReloadTimeout);
        }

        leaderboardReloadTimeout = setTimeout(() => {
            router.reload({
                only: ['leaderboards'],
            });
        }, 500);
    }

    function openLeaderboard(leaderboardKey?: string | Event) {
        if (options.isBlocked?.()) {
            return;
        }

        options.beforeOpen?.();
        const selectedKey =
            typeof leaderboardKey === 'string'
                ? leaderboardKey
                : defaultLeaderboard.value?.key;

        if (selectedKey) {
            selectedLeaderboardKey.value = selectedKey;
        }

        options.activeGameModal.value = 'leaderboard';
        scheduleLeaderboardReload();
    }

    function closeLeaderboard(): void {
        if (options.activeGameModal.value === 'leaderboard') {
            options.activeGameModal.value = null;
        }
    }

    onBeforeUnmount(() => {
        if (leaderboardReloadTimeout !== null) {
            clearTimeout(leaderboardReloadTimeout);
        }
    });

    return {
        selectedLeaderboardKey,
        leaderboards,
        defaultLeaderboard,
        selectedLeaderboard,
        isLeaderboardModalOpen,
        openLeaderboard,
        closeLeaderboard,
        scheduleLeaderboardReload,
    };
}
