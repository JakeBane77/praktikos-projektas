import { ref } from 'vue';

const isImmersiveTestingPanelOpen = ref(false);

export function useImmersiveTestingPanel() {
    function toggleImmersiveTestingPanel(): void {
        isImmersiveTestingPanelOpen.value = !isImmersiveTestingPanelOpen.value;
    }

    return {
        isImmersiveTestingPanelOpen,
        toggleImmersiveTestingPanel,
    };
}
