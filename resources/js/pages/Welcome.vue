<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    BarChart3,
    Castle,
    CloudSun,
    Coins,
    Gamepad2,
    Hammer,
    Mountain,
    RotateCcw,
    Route,
    TreePine,
    Wheat,
} from 'lucide-vue-next';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { dashboard, immersive, login, register } from '@/routes';
import oakTree from '@/components/minigames/assets/trees/oak.svg';

const resourceCards = [
    {
        label: 'Wood',
        amount: '1,840',
        rate: '+560/hour',
        icon: TreePine,
        class: 'bg-[#b58b62] text-[#24160d]',
    },
    {
        label: 'Food',
        amount: '1,220',
        rate: '+410/hour',
        icon: Wheat,
        class: 'bg-[#9abf83] text-[#152412]',
    },
    {
        label: 'Stone',
        amount: '780',
        rate: '+295/hour',
        icon: Mountain,
        class: 'bg-[#aeb4b9] text-[#182027]',
    },
    {
        label: 'Gold',
        amount: '460',
        rate: '+120/hour',
        icon: Coins,
        class: 'bg-[#f0d79a] text-[#2f2514]',
    },
];

const featureCards = [
    {
        title: 'Build and upgrade',
        text: 'Mines, farms, lumbercamps, quarries, and roads expand your settlement with every upgrade.',
        icon: Hammer,
    },
    {
        title: 'Timed minigames',
        text: 'Wood, food, stone, and gold minigames reward active play with resource-specific gains.',
        icon: Gamepad2,
    },
    {
        title: 'Immersive kingdom',
        text: 'A visual map changes with local time, weather, settlement stage, and long-term progress.',
        icon: CloudSun,
    },
    {
        title: 'Achievement bonuses',
        text: 'Unlocked milestones boost production for specific buildings or the whole settlement.',
        icon: Award,
    },
    {
        title: 'Prestige loop',
        text: 'Reach the required road network, reset progress, and keep long-term achievement power.',
        icon: RotateCcw,
    },
];
</script>

<template>
    <Head title="Kingdom Idle" />

    <main
        class="min-h-screen bg-[#f6f3ec] text-[#1f241c] dark:bg-[#12140f] dark:text-[#f3efe4]"
    >
        <header
            class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-6"
        >
            <Link
                :href="$page.props.auth.user ? dashboard() : '/'"
                class="inline-flex items-center gap-3 text-lg font-bold"
            >
                <span
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-[#243627] text-white"
                >
                    <Castle class="h-5 w-5" />
                </span>
                Kingdom Idle
            </Link>

            <nav class="flex items-center gap-2 sm:gap-3">
                <div class="hidden sm:block">
                    <AppearanceTabs />
                </div>

                <template v-if="$page.props.auth.user">
                    <Link
                        :href="immersive()"
                        class="hidden rounded-md px-4 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#e9e1d3] sm:inline-flex dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                    >
                        Immersive mode
                    </Link>
                    <Link
                        :href="dashboard()"
                        class="rounded-md bg-[#243627] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                    >
                        Dashboard mode
                    </Link>
                </template>

                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-4 py-2 text-sm font-semibold text-[#243627] transition hover:bg-[#e9e1d3] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="rounded-md bg-[#243627] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <div
            class="mx-auto flex w-full max-w-7xl justify-end px-5 pb-2 sm:hidden"
        >
            <AppearanceTabs />
        </div>

        <section
            class="relative isolate mx-auto flex min-h-[calc(100svh-132px)] w-full max-w-7xl items-center overflow-hidden px-5 py-8 sm:px-6 lg:py-10"
        >
            <div
                class="absolute inset-x-0 bottom-0 -z-10 h-40 bg-[#d8d0b4] dark:bg-[#1d2517]"
            ></div>
            <div
                class="absolute inset-x-0 bottom-24 -z-10 h-36 bg-[#e7e0c9] dark:bg-[#172011]"
            ></div>
            <img
                :src="oakTree"
                alt=""
                class="pointer-events-none absolute right-[-4rem] bottom-16 -z-10 h-[28rem] w-[28rem] max-w-none opacity-35 sm:right-0 sm:h-[34rem] sm:w-[34rem] lg:right-16 lg:opacity-55"
            />

            <div class="w-full max-w-3xl">
                <p
                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                >
                    Idle settlement game
                </p>

                <h1
                    class="mt-4 max-w-2xl text-4xl leading-tight font-bold sm:text-5xl lg:text-6xl"
                >
                    Kingdom Idle
                </h1>

                <p
                    class="mt-5 max-w-xl text-justify text-base leading-7 text-[#4f574b] sm:text-lg dark:text-[#c6c0b3]"
                >
                    Gather resources, upgrade production, complete minigames,
                    unlock bonuses, and watch your kingdom shift between
                    dashboard control and immersive world view.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="register()"
                        class="rounded-md bg-[#243627] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                    >
                        Register
                    </Link>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="rounded-md border border-[#b7aa91] px-5 py-3 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                    >
                        Log in
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="rounded-md bg-[#243627] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                    >
                        Continue settlement
                    </Link>
                </div>

                <div
                    class="mt-10 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4"
                >
                    <div
                        v-for="resource in resourceCards"
                        :key="resource.label"
                        :class="['rounded-md p-4 shadow-sm', resource.class]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">
                                {{ resource.label }}
                            </p>
                            <component :is="resource.icon" class="h-4 w-4" />
                        </div>
                        <p class="mt-3 text-2xl font-bold">
                            {{ resource.amount }}
                        </p>
                        <p class="mt-1 text-sm font-semibold opacity-80">
                            {{ resource.rate }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-y border-[#ded2bd] dark:border-[#38362f]">
            <div
                class="mx-auto grid w-full max-w-7xl gap-4 px-5 py-8 sm:grid-cols-3 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <div class="rounded-md bg-[#243627] p-3 text-white">
                        <Route class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                            Prestige requirement
                        </p>
                        <p class="text-xl font-bold">Road max level</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rounded-md bg-[#243627] p-3 text-white">
                        <Award class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                            Achievement rewards
                        </p>
                        <p class="text-xl font-bold">Production bonuses</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rounded-md bg-[#243627] p-3 text-white">
                        <BarChart3 class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                            Leaderboard
                        </p>
                        <p class="text-xl font-bold">Prestige ranking</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-5 py-10 sm:px-6">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Game systems
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">
                        Built for long progression
                    </h2>
                </div>
                <p
                    class="max-w-xl text-justify text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                >
                    Passive production keeps working between visits, while daily
                    collection and minigames give active players more to do.
                </p>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="feature in featureCards"
                    :key="feature.title"
                    class="rounded-md border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
                >
                    <div class="rounded-md bg-[#243627] p-3 text-white">
                        <component :is="feature.icon" class="h-5 w-5" />
                    </div>
                    <h3 class="mt-4 text-lg font-bold">{{ feature.title }}</h3>
                    <p
                        class="mt-2 text-justify text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                    >
                        {{ feature.text }}
                    </p>
                </article>
            </div>
        </section>
    </main>
</template>
