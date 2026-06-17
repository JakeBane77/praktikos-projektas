import { router } from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import { minigameWithLiveStamina } from '@/lib/game';
import type { Minigame, ResourceKey, Resources } from '@/lib/game';

type GameModalRef = Ref<string | null>;

export function useGameMinigames(options: {
    minigames: () => Minigame[];
    resources: () => Resources;
    activeGameModal: GameModalRef;
    beforeOpen?: () => void;
}) {
    const activeMinigameResource = ref<ResourceKey | null>(null);
    const selectedMinigameResource = ref<ResourceKey | null>(null);
    const hasWonMinigame = ref(false);
    const staminaClockMilliseconds = ref(Date.now());
    const staminaClockStartedAtMilliseconds = ref(Date.now());
    let staminaClockInterval: number | undefined;
    let staminaRefreshReloadTimeout: number | undefined;

    const staminaElapsedSeconds = computed(() =>
        Math.floor(
            (staminaClockMilliseconds.value -
                staminaClockStartedAtMilliseconds.value) /
                1000,
        ),
    );
    const minigames = computed<Minigame[]>(() =>
        options
            .minigames()
            .map((minigame) =>
                minigameWithLiveStamina(
                    minigame,
                    staminaElapsedSeconds.value,
                ),
            ),
    );
    const selectedMinigame = computed<Minigame | null>(() =>
        selectedMinigameResource.value
            ? (minigames.value.find(
                  (minigame) =>
                      minigame.resource === selectedMinigameResource.value,
              ) ?? null)
            : null,
    );
    const selectedMinigameResourceAmount = computed(() =>
        selectedMinigame.value
            ? options.resources()[selectedMinigame.value.resource]
            : 0,
    );
    const isMinigameOpen = computed(
        () =>
            options.activeGameModal.value === 'minigame' &&
            selectedMinigame.value !== null,
    );

    function resetStaminaClock(): void {
        const now = Date.now();

        staminaClockStartedAtMilliseconds.value = now;
        staminaClockMilliseconds.value = now;
    }

    function clearStaminaRefreshReloadTimeout(): void {
        if (staminaRefreshReloadTimeout !== undefined) {
            window.clearTimeout(staminaRefreshReloadTimeout);
            staminaRefreshReloadTimeout = undefined;
        }
    }

    function scheduleStaminaRefreshReload(): void {
        clearStaminaRefreshReloadTimeout();

        const nextRefreshSeconds = Math.min(
            ...options
                .minigames()
                .map((minigame) => minigame.stamina.availableInSeconds)
                .filter((seconds) => seconds > 0),
        );

        if (!Number.isFinite(nextRefreshSeconds)) {
            return;
        }

        staminaRefreshReloadTimeout = window.setTimeout(() => {
            router.reload({
                only: ['minigames'],
            });
        }, (nextRefreshSeconds + 1) * 1000);
    }

    function completeMinigame(minigame: Minigame): void {
        router.post(
            `/dashboard/minigames/${minigame.resource}/complete`,
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    activeMinigameResource.value = minigame.resource;
                },
                onSuccess: () => {
                    hasWonMinigame.value = true;
                },
                onError: (errors) => {
                    if (errors.minigame) {
                        toast.error(errors.minigame);
                    }
                },
                onFinish: () => {
                    activeMinigameResource.value = null;
                },
            },
        );
    }

    function openMinigame(minigame: Minigame): void {
        if (
            activeMinigameResource.value !== null ||
            !minigame.stamina.isAvailable
        ) {
            return;
        }

        options.beforeOpen?.();
        selectedMinigameResource.value = minigame.resource;
        hasWonMinigame.value = false;
        options.activeGameModal.value = 'minigame';
    }

    function closeMinigame(): void {
        if (activeMinigameResource.value !== null) {
            return;
        }

        selectedMinigameResource.value = null;
        hasWonMinigame.value = false;

        if (options.activeGameModal.value === 'minigame') {
            options.activeGameModal.value = null;
        }
    }

    function continueMinigame(): void {
        hasWonMinigame.value = false;
    }

    function completeSelectedMinigame(): void {
        if (!selectedMinigame.value) {
            return;
        }

        completeMinigame(selectedMinigame.value);
    }

    watch(
        options.minigames,
        () => {
            resetStaminaClock();
            scheduleStaminaRefreshReload();
        },
        { immediate: true },
    );

    onMounted(() => {
        staminaClockInterval = window.setInterval(() => {
            staminaClockMilliseconds.value = Date.now();
        }, 1000);
    });

    onBeforeUnmount(() => {
        if (staminaClockInterval !== undefined) {
            window.clearInterval(staminaClockInterval);
        }

        clearStaminaRefreshReloadTimeout();
    });

    return {
        activeMinigameResource,
        selectedMinigameResource,
        hasWonMinigame,
        staminaClockMilliseconds,
        staminaClockStartedAtMilliseconds,
        staminaElapsedSeconds,
        minigames,
        selectedMinigame,
        selectedMinigameResourceAmount,
        isMinigameOpen,
        resetStaminaClock,
        clearStaminaRefreshReloadTimeout,
        scheduleStaminaRefreshReload,
        completeMinigame,
        openMinigame,
        closeMinigame,
        continueMinigame,
        completeSelectedMinigame,
    };
}
