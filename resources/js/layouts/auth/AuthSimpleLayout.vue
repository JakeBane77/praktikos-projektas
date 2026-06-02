<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Award, Gamepad2, Route } from 'lucide-vue-next';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import oakTree from '@/components/minigames/assets/trees/oak.svg';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="min-h-svh bg-[#f6f3ec] text-[#1f241c] dark:bg-[#12140f] dark:text-[#f3efe4]"
    >
        <div
            class="mx-auto grid min-h-svh w-full max-w-7xl lg:grid-cols-[1fr_0.78fr]"
        >
            <section
                class="relative hidden overflow-hidden border-r border-[#ded2bd] px-8 py-8 lg:flex lg:flex-col lg:justify-between dark:border-[#38362f]"
            >
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <Link
                            :href="home()"
                            class="inline-flex items-center gap-3 text-lg font-bold"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-md bg-[#243627] text-white"
                            >
                                <AppLogoIcon class="h-5 w-5" />
                            </div>
                            Kingdom Idle
                        </Link>
                        <AppearanceTabs />
                    </div>

                    <div class="mt-16 max-w-xl">
                        <p
                            class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            Settlement access
                        </p>
                        <h1 class="mt-4 text-5xl leading-tight font-bold">
                            Your kingdom keeps growing between visits.
                        </h1>
                        <p
                            class="mt-5 text-justify text-base leading-7 text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            Collect daily resources, upgrade production, clear
                            resource minigames, unlock achievement bonuses, and
                            push your road network toward prestige.
                        </p>
                    </div>
                </div>

                <div>
                    <img
                        :src="oakTree"
                        alt=""
                        class="pointer-events-none absolute right-4 bottom-16 h-[26rem] w-[26rem] opacity-45"
                    />
                    <div class="relative z-10 grid max-w-xl grid-cols-3 gap-3">
                        <div
                            class="rounded-md border border-[#ded2bd] bg-[#fffaf0]/90 p-4 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]/90"
                        >
                            <Gamepad2
                                class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                            />
                            <p class="mt-3 text-sm font-semibold">Minigames</p>
                        </div>
                        <div
                            class="rounded-md border border-[#ded2bd] bg-[#fffaf0]/90 p-4 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]/90"
                        >
                            <Award
                                class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                            />
                            <p class="mt-3 text-sm font-semibold">Bonuses</p>
                        </div>
                        <div
                            class="rounded-md border border-[#ded2bd] bg-[#fffaf0]/90 p-4 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]/90"
                        >
                            <Route
                                class="h-5 w-5 text-[#7b633d] dark:text-[#caa66c]"
                            />
                            <p class="mt-3 text-sm font-semibold">Prestige</p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="flex min-h-svh items-center justify-center px-5 py-8 sm:px-6 lg:px-10"
            >
                <div class="w-full max-w-md">
                    <div
                        class="mb-8 flex flex-col items-center gap-4 lg:hidden"
                    >
                        <div
                            class="flex w-full items-center justify-between gap-4"
                        >
                            <Link
                                :href="home()"
                                class="flex items-center gap-3 text-lg font-bold"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-md bg-[#243627] text-white"
                                >
                                    <AppLogoIcon class="h-5 w-5" />
                                </div>
                                Kingdom Idle
                            </Link>
                            <AppearanceTabs />
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm sm:p-6 dark:border-[#38362f] dark:bg-[#1a1d15]"
                    >
                        <div class="mb-6 space-y-2">
                            <p
                                class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Account
                            </p>
                            <h1 class="text-2xl font-bold">{{ title }}</h1>
                            <p
                                class="text-justify text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
