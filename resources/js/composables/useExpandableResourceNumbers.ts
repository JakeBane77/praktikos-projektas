import { ref } from 'vue';
import { formatExactNumber, formatGameNumber } from '@/lib/game';

export function useExpandableResourceNumbers() {
    const expandedResourceNumberKeys = ref<Set<string>>(new Set());

    function isResourceNumberExpanded(key: string): boolean {
        return expandedResourceNumberKeys.value.has(key);
    }

    function toggleResourceNumber(key: string): void {
        const nextExpandedKeys = new Set(expandedResourceNumberKeys.value);

        if (nextExpandedKeys.has(key)) {
            nextExpandedKeys.delete(key);
        } else {
            nextExpandedKeys.add(key);
        }

        expandedResourceNumberKeys.value = nextExpandedKeys;
    }

    function resourceNumberLabel(key: string, value: number): string {
        return isResourceNumberExpanded(key)
            ? formatExactNumber(value)
            : formatGameNumber(value);
    }

    function resourceNumberTitle(key: string): string {
        return isResourceNumberExpanded(key)
            ? 'Show compact number'
            : 'Show full number';
    }

    return {
        expandedResourceNumberKeys,
        isResourceNumberExpanded,
        toggleResourceNumber,
        resourceNumberLabel,
        resourceNumberTitle,
    };
}
