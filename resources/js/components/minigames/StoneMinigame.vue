<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next';

defineProps<{
    isSaving: boolean;
    isCompleted: boolean;
}>();

defineEmits<{
    complete: [];
}>();
</script>

<template>
    <div
        class="mt-5 flex min-h-[420px] items-center justify-center rounded-md border border-[#e4dac7] bg-[#f6f3ec] sm:min-h-[520px] dark:border-[#35332c] dark:bg-[#12140f]"
    >
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isSaving || isCompleted"
            @click="$emit('complete')"
        >
            <CheckCircle2 class="h-4 w-4" />
            {{ isSaving ? 'Completing...' : 'Win' }}
        </button>
    </div>
</template>
