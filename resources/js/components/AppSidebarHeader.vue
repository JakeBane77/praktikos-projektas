<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { MapPin, RotateCcw, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useImmersiveTestingPanel } from '@/composables/useImmersiveTestingPanel';
import type { WeatherSnapshot } from '@/lib/weather';
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
const weather = computed(
    () => page.props.weather as WeatherSnapshot | undefined,
);
const isUpdatingWeatherLocation = ref(false);
const { isImmersiveTestingPanelOpen, toggleImmersiveTestingPanel } =
    useImmersiveTestingPanel();

type OpenMeteoCurrentWeatherResponse = {
    current?: {
        time?: string;
        weather_code?: number;
    };
};

function updateWeatherLocation(): void {
    if (!navigator.geolocation) {
        toast.error('Geolocation is not supported here.');

        return;
    }

    isUpdatingWeatherLocation.value = true;

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            try {
                const currentWeather = await fetchCurrentWeather(
                    position.coords.latitude,
                    position.coords.longitude,
                );

                router.post(
                    '/dashboard/weather-location',
                    {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        weather_code: currentWeather.weatherCode,
                        api_time: currentWeather.apiTime,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            toast.success('Weather location updated.');
                        },
                        onError: () => {
                            toast.error(
                                'Weather location could not be updated.',
                            );
                        },
                        onFinish: () => {
                            isUpdatingWeatherLocation.value = false;
                        },
                    },
                );
            } catch {
                isUpdatingWeatherLocation.value = false;
                toast.error('Weather could not be fetched from this device.');
            }
        },
        (error) => {
            isUpdatingWeatherLocation.value = false;
            toast.error(
                error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied.'
                    : 'Current location could not be detected.',
            );
        },
        {
            enableHighAccuracy: false,
            maximumAge: 300000,
            timeout: 10000,
        },
    );
}

async function fetchCurrentWeather(latitude: number, longitude: number) {
    const parameters = new URLSearchParams({
        latitude: String(latitude),
        longitude: String(longitude),
        current: 'weather_code',
        timezone: 'UTC',
    });
    const response = await fetch(
        `https://api.open-meteo.com/v1/forecast?${parameters.toString()}`,
    );

    if (!response.ok) {
        throw new Error('Open-Meteo request failed.');
    }

    const data = (await response.json()) as OpenMeteoCurrentWeatherResponse;
    const weatherCode = Number(data.current?.weather_code);
    const apiTime = data.current?.time;

    if (!Number.isInteger(weatherCode) || !apiTime) {
        throw new Error('Open-Meteo response is missing current weather.');
    }

    return {
        weatherCode,
        apiTime: apiTime.endsWith('Z') ? apiTime : `${apiTime}Z`,
    };
}

function useDefaultWeatherLocation(): void {
    isUpdatingWeatherLocation.value = true;

    router.post(
        '/dashboard/weather-location/default',
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Default weather location restored.');
            },
            onError: () => {
                toast.error('Default weather location could not be restored.');
            },
            onFinish: () => {
                isUpdatingWeatherLocation.value = false;
            },
        },
    );
}
</script>

<template>
    <header
        class="immersive-app-header flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:h-16 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div
                    class="immersive-header-breadcrumbs hidden min-w-0 sm:block"
                >
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>
        <div
            class="immersive-header-actions ml-auto flex min-w-0 items-center gap-1 sm:gap-2"
        >
            <section
                v-if="isImmersivePage"
                class="immersive-location-controls flex items-center gap-1 rounded-md border border-[#ded2bd] bg-[#fffaf0] p-1 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
                aria-label="Weather location controls"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-sm px-2.5 py-1.5 text-xs font-semibold text-[#5d6356] transition hover:bg-[#ebe4d7] hover:text-[#243627] disabled:cursor-not-allowed disabled:opacity-55 dark:text-[#c6c0b3] dark:hover:bg-[#24281d] dark:hover:text-[#f3efe4]"
                    :disabled="isUpdatingWeatherLocation"
                    title="Use my location for weather"
                    @click="updateWeatherLocation"
                >
                    <MapPin class="h-3.5 w-3.5" />
                    <span class="immersive-header-label hidden sm:inline"
                        >Use my location</span
                    >
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-sm px-2.5 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-55"
                    :class="
                        weather?.isUsingGeolocation
                            ? 'text-[#5d6356] hover:bg-[#ebe4d7] hover:text-[#243627] dark:text-[#c6c0b3] dark:hover:bg-[#24281d] dark:hover:text-[#f3efe4]'
                            : 'bg-[#243627] text-white dark:bg-[#caa66c] dark:text-[#12140f]'
                    "
                    :disabled="isUpdatingWeatherLocation"
                    title="Use default weather location"
                    @click="useDefaultWeatherLocation"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    <span class="immersive-header-label hidden sm:inline"
                        >Default location</span
                    >
                </button>
            </section>
            <button
                v-if="isImmersivePage"
                type="button"
                class="immersive-testing-toggle inline-flex items-center gap-1.5 rounded-md border px-2 py-2 text-sm font-semibold shadow-sm transition sm:px-3"
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
                <span class="immersive-header-label hidden sm:inline"
                    >Testing</span
                >
            </button>
            <AppearanceTabs />
        </div>
    </header>
</template>

<style scoped>
@media (max-height: 480px) and (max-width: 900px) {
    .immersive-app-header {
        height: 3.25rem;
        padding-inline: 0.5rem;
    }

    .immersive-header-breadcrumbs,
    .immersive-header-label {
        display: none !important;
    }

    .immersive-header-actions {
        gap: 0.25rem;
    }

    .immersive-location-controls button,
    .immersive-testing-toggle {
        padding: 0.5rem;
    }
}
</style>
