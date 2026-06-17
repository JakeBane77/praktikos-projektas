import { computed, ref } from 'vue';
import type { Component } from 'vue';
import type { OfflineProgress, ResourceKey } from '@/lib/game';

type OfflineProgressResource = {
    key: ResourceKey;
    label: string;
    icon: Component;
};

export function useOfflineProgress(options: {
    offlineProgress: () => OfflineProgress | null;
    resources: OfflineProgressResource[];
}) {
    const isOfflineProgressDismissed = ref(false);
    const offlineProgressDurationLabel = computed(() =>
        formatOfflineProgressDuration(
            options.offlineProgress()?.elapsedHours ?? 0,
        ),
    );
    const offlineProgressResourceRows = computed(() =>
        options.resources
            .map((resource) => ({
                ...resource,
                amount: options.offlineProgress()?.resources[resource.key] ?? 0,
            }))
            .filter((resource) => resource.amount > 0),
    );

    function closeOfflineProgress(): void {
        isOfflineProgressDismissed.value = true;
    }

    return {
        isOfflineProgressDismissed,
        offlineProgressDurationLabel,
        offlineProgressResourceRows,
        closeOfflineProgress,
    };
}

function formatOfflineProgressDuration(elapsedHours: number): string {
    const days = Math.floor(elapsedHours / 24);
    const hours = elapsedHours % 24;
    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
    }

    if (hours > 0 || parts.length === 0) {
        parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
    }

    return parts.join(', ');
}
