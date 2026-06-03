<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'Auto' },
] as const;
</script>

<template>
    <div
        class="appearance-tabs inline-flex gap-1 rounded-md border border-[#ded2bd] bg-[#fffaf0] p-1 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
        aria-label="Appearance"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'appearance-tab flex items-center rounded-sm px-2 py-1.5 text-sm font-semibold transition-colors sm:px-3',
                appearance === value
                    ? 'bg-[#243627] text-white shadow-xs'
                    : 'text-[#5d6356] hover:bg-[#ebe4d7] hover:text-[#243627] dark:text-[#c6c0b3] dark:hover:bg-[#24281d] dark:hover:text-[#f3efe4]',
            ]"
            :aria-pressed="appearance === value"
        >
            <component :is="Icon" class="h-4 w-4 sm:-ml-1" />
            <span class="appearance-tab-label ml-1.5 hidden sm:inline">{{
                label
            }}</span>
        </button>
    </div>
</template>

<style scoped>
@media (max-height: 480px) and (max-width: 900px) {
    .appearance-tabs {
        gap: 0.25rem;
    }

    .appearance-tab {
        padding: 0.5rem;
    }

    .appearance-tab-label {
        display: none !important;
    }
}
</style>
