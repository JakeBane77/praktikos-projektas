import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, readonly, ref, watch } from 'vue';
import type { ComputedRef, DeepReadonly, Ref } from 'vue';

export type AllianceOnlineUser = {
    id: number;
    name: string;
};

type PresenceUserPayload = {
    id?: number | string;
    name?: string | null;
};

type AlliancePresenceState = {
    onlineUsers: DeepReadonly<Ref<AllianceOnlineUser[]>>;
    onlineUserIds: ComputedRef<Set<number>>;
};

function normalizePresenceUser(
    user: PresenceUserPayload,
): AllianceOnlineUser | null {
    const id = Number(user.id);

    if (!Number.isInteger(id)) {
        return null;
    }

    const name = typeof user.name === 'string' ? user.name.trim() : '';

    return {
        id,
        name: name === '' ? `User #${id}` : name,
    };
}

function sortOnlineUsers(users: AllianceOnlineUser[]): AllianceOnlineUser[] {
    return [...users].sort((firstUser, secondUser) =>
        firstUser.name.localeCompare(secondUser.name),
    );
}

export function useAlliancePresence(
    allianceId: Ref<number | null>,
): AlliancePresenceState {
    const onlineUsers = ref<AllianceOnlineUser[]>([]);
    const onlineUserIds = computed(
        () => new Set(onlineUsers.value.map((user) => user.id)),
    );
    let currentChannelName: string | null = null;

    function leaveCurrentChannel(): void {
        if (currentChannelName === null) {
            return;
        }

        if (echoIsConfigured()) {
            echo().leave(currentChannelName);
        }

        currentChannelName = null;
        onlineUsers.value = [];
    }

    function setOnlineUsers(users: PresenceUserPayload[]): void {
        const normalizedUsers = users
            .map((user) => normalizePresenceUser(user))
            .filter((user): user is AllianceOnlineUser => user !== null);

        onlineUsers.value = sortOnlineUsers(normalizedUsers);
    }

    function addOnlineUser(user: PresenceUserPayload): void {
        const normalizedUser = normalizePresenceUser(user);

        if (normalizedUser === null) {
            return;
        }

        onlineUsers.value = sortOnlineUsers([
            ...onlineUsers.value.filter(
                (onlineUser) => onlineUser.id !== normalizedUser.id,
            ),
            normalizedUser,
        ]);
    }

    function removeOnlineUser(user: PresenceUserPayload): void {
        const normalizedUser = normalizePresenceUser(user);

        if (normalizedUser === null) {
            return;
        }

        onlineUsers.value = onlineUsers.value.filter(
            (onlineUser) => onlineUser.id !== normalizedUser.id,
        );
    }

    watch(
        allianceId,
        (nextAllianceId) => {
            const nextChannelName =
                nextAllianceId === null
                    ? null
                    : `alliance.${nextAllianceId}.presence`;

            if (nextChannelName === currentChannelName) {
                return;
            }

            leaveCurrentChannel();

            if (nextChannelName === null || !echoIsConfigured()) {
                return;
            }

            currentChannelName = nextChannelName;
            echo()
                .join(nextChannelName)
                .here((users: PresenceUserPayload[]) => {
                    setOnlineUsers(users);
                })
                .joining((user: PresenceUserPayload) => {
                    addOnlineUser(user);
                })
                .leaving((user: PresenceUserPayload) => {
                    removeOnlineUser(user);
                });
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        leaveCurrentChannel();
    });

    return {
        onlineUsers: readonly(onlineUsers),
        onlineUserIds,
    };
}
