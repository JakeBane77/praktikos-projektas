import { echoIsConfigured, useConnectionStatus } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import type { ComputedRef } from 'vue';

type EchoAvailability = {
    isEchoConnected: ComputedRef<boolean>;
    shouldUseHttpFallback: ComputedRef<boolean>;
};

export function useEchoAvailability(): EchoAvailability {
    const isConfigured = echoIsConfigured();
    const connectionStatus = isConfigured
        ? useConnectionStatus()
        : ref('disconnected');

    const isEchoConnected = computed(
        () => isConfigured && connectionStatus.value === 'connected',
    );

    return {
        isEchoConnected,
        shouldUseHttpFallback: computed(() => !isEchoConnected.value),
    };
}
