<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Kingdom Idle" />

    <main
        class="min-h-screen bg-[#f6f3ec] text-[#1f241c] dark:bg-[#12140f] dark:text-[#f3efe4]"
    >
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5"
        >
            <Link
                :href="$page.props.auth.user ? dashboard() : '/'"
                class="text-lg font-semibold"
            >
                Kingdom Idle
            </Link>

            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-md bg-[#243627] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1a291d]"
                >
                    Dashboard mode
                </Link>

                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-4 py-2 text-sm font-medium text-[#243627] transition hover:bg-[#e9e1d3] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="rounded-md bg-[#243627] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1a291d]"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <section
            class="mx-auto grid min-h-[calc(100vh-84px)] w-full max-w-6xl items-center gap-10 px-6 py-10 lg:grid-cols-[1fr_0.85fr]"
        >
            <div class="max-w-2xl">
                <p
                    class="mb-4 text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                >
                    Build. Upgrade. Compete.
                </p>

                <h1
                    class="text-4xl leading-tight font-bold sm:text-5xl lg:text-6xl"
                >
                    Grow your settlement one resource at a time.
                </h1>

                <p
                    class="mt-6 max-w-xl text-base leading-7 text-[#5d6356] dark:text-[#c6c0b3]"
                >
                    Gather gold, wood, stone, and food. Construct buildings,
                    unlock achievements, and climb the global leaderboard.
                </p>

                <div
                    v-if="!$page.props.auth.user"
                    class="mt-8 flex flex-wrap gap-3"
                >
                    <Link
                        :href="register()"
                        class="rounded-md bg-[#243627] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                    >
                        Start playing
                    </Link>
                    <Link
                        :href="login()"
                        class="rounded-md border border-[#b7aa91] px-5 py-3 text-sm font-semibold text-[#243627] transition hover:bg-[#ebe4d7] dark:border-[#554f42] dark:text-[#f3efe4] dark:hover:bg-[#24281d]"
                    >
                        Log in
                    </Link>
                </div>

                <Link
                    v-else
                    :href="dashboard()"
                    class="mt-8 inline-flex rounded-md bg-[#243627] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1a291d]"
                >
                    Continue to Dashboard mode
                </Link>
            </div>

            <div
                class="grid gap-4 rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 shadow-sm dark:border-[#38362f] dark:bg-[#1a1d15]"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-md bg-[#f0d79a] p-4 text-[#2f2514]">
                        <p class="text-sm font-medium">Gold</p>
                        <p class="mt-2 text-2xl font-bold">1,250</p>
                    </div>
                    <div class="rounded-md bg-[#9abf83] p-4 text-[#152412]">
                        <p class="text-sm font-medium">Food</p>
                        <p class="mt-2 text-2xl font-bold">840</p>
                    </div>
                    <div class="rounded-md bg-[#b58b62] p-4 text-[#24160d]">
                        <p class="text-sm font-medium">Wood</p>
                        <p class="mt-2 text-2xl font-bold">620</p>
                    </div>
                    <div class="rounded-md bg-[#aeb4b9] p-4 text-[#182027]">
                        <p class="text-sm font-medium">Stone</p>
                        <p class="mt-2 text-2xl font-bold">410</p>
                    </div>
                </div>

                <div
                    class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">Mine</p>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Level 4
                            </p>
                        </div>
                        <p
                            class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            +28 gold/hour
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">Lumbercamp</p>
                            <p
                                class="text-sm text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Level 3
                            </p>
                        </div>
                        <p
                            class="text-sm font-semibold text-[#47663b] dark:text-[#9dcc84]"
                        >
                            +18 wood/hour
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
