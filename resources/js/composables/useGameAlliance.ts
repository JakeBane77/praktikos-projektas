import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import { useAllianceChatEcho } from '@/composables/useAllianceChatEcho';
import { useAlliancePresence } from '@/composables/useAlliancePresence';
import { useEchoAvailability } from '@/composables/useEchoAvailability';
import type { AllianceState, ResourceKey } from '@/lib/game';

type GameModalRef = Ref<string | null>;

export function useGameAlliance(options: {
    alliances: () => AllianceState;
    activeGameModal: GameModalRef;
    isBlocked?: () => boolean;
    beforeOpen?: () => void;
    beforeOpenChat?: () => void;
}) {
    const isSubmittingAlliance = ref(false);
    const hasUnreadAllianceChatMessage = ref(false);
    let allianceSearchReloadTimeout: ReturnType<typeof setTimeout> | null =
        null;
    const currentAllianceId = computed(
        () => options.alliances().current?.id ?? null,
    );
    const {
        onlineUsers: allianceOnlineUsers,
        onlineUserIds: allianceOnlineUserIds,
    } = useAlliancePresence(currentAllianceId);
    const { shouldUseHttpFallback: shouldUseChatHttpFallback } =
        useEchoAvailability();
    const isAllianceModalOpen = computed(
        () => options.activeGameModal.value === 'alliance',
    );
    const isAllianceChatModalOpen = computed(
        () =>
            options.activeGameModal.value === 'alliance-chat' &&
            options.alliances().current !== null,
    );

    useAllianceChatEcho(currentAllianceId, () => {
        if (!isAllianceChatModalOpen.value) {
            hasUnreadAllianceChatMessage.value = true;
        }

        router.reload({
            only: ['alliances'],
        });
    });

    watch(currentAllianceId, () => {
        hasUnreadAllianceChatMessage.value = false;
    });

    function openAlliance(): void {
        if (options.isBlocked?.()) {
            return;
        }

        options.beforeOpen?.();
        options.activeGameModal.value = 'alliance';
        scheduleAllianceReload('');
    }

    function closeAlliance(): void {
        if (options.activeGameModal.value === 'alliance') {
            options.activeGameModal.value = null;
        }
    }

    function openAllianceChat(): void {
        if (options.isBlocked?.() || options.alliances().current === null) {
            return;
        }

        options.beforeOpenChat?.();
        hasUnreadAllianceChatMessage.value = false;
        options.activeGameModal.value = 'alliance-chat';

        if (shouldUseChatHttpFallback.value) {
            router.reload({
                only: ['alliances'],
            });
        }
    }

    function closeAllianceChat(): void {
        if (options.activeGameModal.value === 'alliance-chat') {
            options.activeGameModal.value = null;
        }
    }

    function searchAlliances(query: string): void {
        scheduleAllianceReload(query);
    }

    function scheduleAllianceReload(query: string): void {
        if (allianceSearchReloadTimeout !== null) {
            clearTimeout(allianceSearchReloadTimeout);
        }

        allianceSearchReloadTimeout = setTimeout(() => {
            router.reload({
                data: {
                    alliance_search: query.trim(),
                },
                only: ['alliances'],
            });
        }, 500);
    }

    function createAlliance(payload: {
        name: string;
        description: string | null;
        is_open: boolean;
    }): void {
        router.post('/alliances', payload, submissionOptions('alliance'));
    }

    function joinAlliance(allianceId: number): void {
        router.post(
            `/alliances/${allianceId}/join`,
            {},
            submissionOptions('alliance'),
        );
    }

    function applyToAlliance(allianceId: number): void {
        router.post(
            `/alliances/${allianceId}/apply`,
            {},
            submissionOptions('alliance'),
        );
    }

    function updateAlliance(payload: {
        allianceId: number;
        description?: string | null;
        is_open?: boolean;
    }): void {
        router.patch(
            `/alliances/${payload.allianceId}`,
            payload,
            submissionOptions('alliance'),
        );
    }

    function acceptAllianceApplication(payload: {
        allianceId: number;
        applicationId: number;
    }): void {
        router.patch(
            `/alliances/${payload.allianceId}/applications/${payload.applicationId}/accept`,
            {},
            submissionOptions('alliance'),
        );
    }

    function denyAllianceApplication(payload: {
        allianceId: number;
        applicationId: number;
    }): void {
        router.delete(
            `/alliances/${payload.allianceId}/applications/${payload.applicationId}`,
            submissionOptions('alliance'),
        );
    }

    function leaveAlliance(allianceId: number): void {
        router.delete(
            `/alliances/${allianceId}/leave`,
            submissionOptions('alliance'),
        );
    }

    function disbandAlliance(allianceId: number): void {
        router.delete(`/alliances/${allianceId}`, submissionOptions('alliance'));
    }

    function promoteAllianceMember(payload: {
        allianceId: number;
        membershipId: number;
    }): void {
        router.patch(
            `/alliances/${payload.allianceId}/members/${payload.membershipId}/promote`,
            {},
            submissionOptions('alliance'),
        );
    }

    function demoteAllianceMember(payload: {
        allianceId: number;
        membershipId: number;
    }): void {
        router.patch(
            `/alliances/${payload.allianceId}/members/${payload.membershipId}/demote`,
            {},
            submissionOptions('alliance'),
        );
    }

    function transferAllianceLeadership(payload: {
        allianceId: number;
        membershipId: number;
    }): void {
        router.patch(
            `/alliances/${payload.allianceId}/members/${payload.membershipId}/transfer-leadership`,
            {},
            submissionOptions('alliance'),
        );
    }

    function kickAllianceMember(payload: {
        allianceId: number;
        membershipId: number;
    }): void {
        router.delete(
            `/alliances/${payload.allianceId}/members/${payload.membershipId}`,
            submissionOptions('alliance'),
        );
    }

    function contributeAllianceGoal(payload: {
        goalId: number;
        resource_type: ResourceKey;
        amount: number;
    }): void {
        router.post(
            `/alliance-goals/${payload.goalId}/contribute`,
            {
                resource_type: payload.resource_type,
                amount: payload.amount,
            },
            submissionOptions('alliance_goal'),
        );
    }

    function sendAllianceChatMessage(payload: {
        allianceId: number;
        message: string;
    }): void {
        router.post(
            `/alliances/${payload.allianceId}/chat-messages`,
            {
                message: payload.message,
            },
            {
                ...submissionOptions('alliance_chat'),
                only: ['alliances'],
            },
        );
    }

    function submissionOptions(errorKey: string) {
        return {
            preserveScroll: true,
            onStart: () => {
                isSubmittingAlliance.value = true;
            },
            onError: (errors: Partial<Record<string, string>>) => {
                const message = errors[errorKey];

                if (message) {
                    toast.error(message);
                }
            },
            onFinish: () => {
                isSubmittingAlliance.value = false;
            },
        };
    }

    onBeforeUnmount(() => {
        if (allianceSearchReloadTimeout !== null) {
            clearTimeout(allianceSearchReloadTimeout);
        }
    });

    return {
        isSubmittingAlliance,
        allianceOnlineUsers,
        allianceOnlineUserIds,
        hasUnreadAllianceChatMessage,
        isAllianceModalOpen,
        isAllianceChatModalOpen,
        openAlliance,
        closeAlliance,
        openAllianceChat,
        closeAllianceChat,
        searchAlliances,
        createAlliance,
        joinAlliance,
        applyToAlliance,
        updateAlliance,
        acceptAllianceApplication,
        denyAllianceApplication,
        leaveAlliance,
        disbandAlliance,
        promoteAllianceMember,
        demoteAllianceMember,
        transferAllianceLeadership,
        kickAllianceMember,
        contributeAllianceGoal,
        sendAllianceChatMessage,
    };
}
