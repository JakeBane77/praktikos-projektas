<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SlidersHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useImmersiveTestingPanel } from '@/composables/useImmersiveTestingPanel';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const isImmersivePage = computed(() => page.component === 'Immersive');
const { isImmersiveTestingPanelOpen, toggleImmersiveTestingPanel } =
    useImmersiveTestingPanel();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button
                v-if="isImmersivePage"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-semibold shadow-sm transition"
                :class="
                    isImmersiveTestingPanelOpen
                        ? 'border-[#243627] bg-[#243627] text-white hover:bg-[#1a291d] dark:border-[#caa66c]/40 dark:bg-[#243627] dark:text-white dark:hover:bg-[#2f4632]'
                        : 'border-[#ded2bd] bg-[#fffaf0] text-[#5d6356] hover:bg-[#ebe4d7] hover:text-[#243627] dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#c6c0b3] dark:hover:bg-[#24281d] dark:hover:text-[#f3efe4]'
                "
                :aria-pressed="isImmersiveTestingPanelOpen"
                aria-label="Toggle testing panel"
                title="Testing panel"
                @click="toggleImmersiveTestingPanel"
            >
                <SlidersHorizontal class="h-4 w-4" />
                <span class="hidden sm:inline">Testing</span>
            </button>
            <AppearanceTabs />
        </div>
    </header>
</template>
