<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Maximize2 } from 'lucide-vue-next';
import { dashboard, immersive } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Immersive mode',
                href: immersive(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Immersive mode" />

    <main class="min-h-full bg-[#10140f] text-[#f3efe4]">
        <section
            class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl flex-col justify-center gap-6 px-4 py-8 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-lg border border-[#38362f] bg-[#171b13] p-6 shadow-sm"
            >
                <div
                    class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-[#243627] p-2 text-white">
                            <Maximize2 class="h-5 w-5" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-wider text-[#caa66c] uppercase"
                            >
                                Immersive mode
                            </p>
                            <h1 class="mt-2 text-3xl font-bold">
                                Full-screen settlement view
                            </h1>
                            <p
                                class="mt-3 max-w-2xl text-sm leading-6 text-[#c6c0b3]"
                            >
                                This mode is ready for a more visual version of
                                the game experience while Dashboard mode stays
                                focused on management and numbers.
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-[#554f42] px-4 py-2.5 text-sm font-semibold text-[#f3efe4] transition hover:bg-[#24281d]"
                    >
                        <LayoutGrid class="h-4 w-4" />
                        Dashboard mode
                    </Link>
                </div>
            </div>
        </section>
    </main>
</template>
