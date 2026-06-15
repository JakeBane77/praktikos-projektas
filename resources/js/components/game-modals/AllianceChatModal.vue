<script setup lang="ts">
import { MessageCircle, Send, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import type { AllianceOnlineUser } from '@/composables/useAlliancePresence';
import type { CurrentAlliance } from '@/lib/game';

const props = defineProps<{
    alliance: CurrentAlliance;
    isSubmitting: boolean;
    onlineUsers: readonly Readonly<AllianceOnlineUser>[];
}>();

const emit = defineEmits<{
    close: [];
    send: [payload: { allianceId: number; message: string }];
}>();

const message = ref('');
const messagesContainer = ref<HTMLElement | null>(null);

const canSend = computed(
    () => message.value.trim().length > 0 && !props.isSubmitting,
);

const onlineUsersLabel = computed(() => {
    const onlineCount = props.onlineUsers.length;

    return `${onlineCount} ${onlineCount === 1 ? 'member' : 'members'} online`;
});

function sendMessage(): void {
    const trimmedMessage = message.value.trim();

    if (trimmedMessage === '' || props.isSubmitting) {
        return;
    }

    emit('send', {
        allianceId: props.alliance.id,
        message: trimmedMessage,
    });
    message.value = '';
}

function scrollMessagesToBottom(): void {
    nextTick(() => {
        if (!messagesContainer.value) {
            return;
        }

        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    });
}

watch(
    () => props.alliance.chatMessages.length,
    () => {
        scrollMessagesToBottom();
    },
    { immediate: true },
);
</script>

<template>
    <section
        class="flex max-h-[calc(100vh-3rem)] w-full max-w-3xl flex-col rounded-lg border border-[#ded2bd] bg-[#fffaf0] text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <header
            class="flex items-start justify-between gap-4 border-b border-[#e4dac7] p-5 dark:border-[#35332c]"
        >
            <div class="flex items-start gap-3">
                <div class="rounded-md bg-[#12313d] p-2 text-[#d9f5ff]">
                    <MessageCircle class="h-5 w-5" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                    >
                        Alliance chat
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">
                        {{ alliance.name }}
                    </h2>
                    <p class="mt-1 text-sm text-[#696250] dark:text-[#b6ae9d]">
                        {{ onlineUsersLabel }}
                    </p>
                    <div
                        v-if="onlineUsers.length > 0"
                        class="mt-3 flex max-w-xl flex-wrap gap-1.5"
                    >
                        <span
                            v-for="onlineUser in onlineUsers"
                            :key="onlineUser.id"
                            class="inline-flex items-center gap-1.5 rounded-md border border-[#d7cbb8] bg-[#f7f0e2] px-2 py-1 text-xs font-semibold text-[#44513b] dark:border-[#35332c] dark:bg-[#202419] dark:text-[#d7e7cf]"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#6aa84f]"
                            ></span>
                            {{ onlineUser.name }}
                        </span>
                    </div>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close alliance chat"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <div
            ref="messagesContainer"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5"
        >
            <div
                v-if="alliance.chatMessages.length === 0"
                class="rounded-md border border-dashed border-[#d7cbb8] p-6 text-center text-sm text-[#696250] dark:border-[#4a4438] dark:text-[#b6ae9d]"
            >
                No alliance messages yet.
            </div>

            <article
                v-for="chatMessage in alliance.chatMessages"
                :key="chatMessage.id"
                class="flex"
                :class="
                    chatMessage.isCurrentUser ? 'justify-end' : 'justify-start'
                "
            >
                <div
                    class="max-w-[min(82%,34rem)] rounded-lg border px-4 py-3"
                    :class="
                        chatMessage.isCurrentUser
                            ? 'border-[#b9d7e7] bg-[#e8f5fb] dark:border-[#26566a] dark:bg-[#12313d]'
                            : 'border-[#e4dac7] bg-[#f7f0e2] dark:border-[#35332c] dark:bg-[#202419]'
                    "
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                    >
                        <h3 class="text-sm font-bold">
                            {{ chatMessage.userName }}
                        </h3>
                        <time
                            v-if="chatMessage.sentAt"
                            class="text-xs text-[#696250] dark:text-[#b6ae9d]"
                        >
                            {{ chatMessage.sentAt }}
                        </time>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm">
                        {{ chatMessage.message }}
                    </p>
                </div>
            </article>
        </div>

        <form
            class="border-t border-[#e4dac7] p-5 dark:border-[#35332c]"
            @submit.prevent="sendMessage"
        >
            <label class="sr-only" for="alliance-chat-message">
                Message
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <textarea
                    id="alliance-chat-message"
                    v-model="message"
                    maxlength="100"
                    rows="2"
                    class="min-h-12 flex-1 resize-none rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-sm text-[#1f241c] outline-none transition focus:border-[#9f8655] dark:border-[#4a4438] dark:bg-[#10140f] dark:text-[#f3efe4]"
                    placeholder="Write a message..."
                    :disabled="isSubmitting"
                    @keydown.enter.exact.prevent="sendMessage"
                ></textarea>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-[#243627] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!canSend"
                >
                    <Send class="h-4 w-4" />
                    {{ isSubmitting ? 'Sending...' : 'Send' }}
                </button>
            </div>
            <p class="mt-2 text-right text-xs text-[#696250] dark:text-[#b6ae9d]">
                {{ message.length }} / 100
            </p>
        </form>
    </section>
</template>
