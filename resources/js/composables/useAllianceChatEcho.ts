import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { onBeforeUnmount, watch } from 'vue';
import type { Ref } from 'vue';

const ALLIANCE_CHAT_EVENT = 'AllianceChatUpdateEvent';

export function useAllianceChatEcho(
    allianceId: Ref<number | null>,
    onMessage: () => void,
): void {
    let currentChannelName: string | null = null;

    function leaveCurrentChannel(): void {
        if (currentChannelName === null) {
            return;
        }

        if (echoIsConfigured()) {
            echo().leave(currentChannelName);
        }

        currentChannelName = null;
    }

    watch(
        allianceId,
        (nextAllianceId) => {
            const nextChannelName =
                nextAllianceId === null
                    ? null
                    : `alliance.${nextAllianceId}.chat`;

            if (nextChannelName === currentChannelName) {
                return;
            }

            leaveCurrentChannel();

            if (nextChannelName === null || !echoIsConfigured()) {
                return;
            }

            currentChannelName = nextChannelName;
            echo()
                .private(nextChannelName)
                .listen(ALLIANCE_CHAT_EVENT, onMessage);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        leaveCurrentChannel();
    });
}
