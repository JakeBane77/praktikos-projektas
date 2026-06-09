<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Crown,
    LogOut,
    Shield,
    Trash2,
    UserRound,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { formatExactNumber } from '@/lib/game';
import type { AllianceMember, AllianceState, AllianceSummary } from '@/lib/game';

const props = defineProps<{
    alliances: AllianceState;
    isSubmitting: boolean;
}>();

const emit = defineEmits<{
    close: [];
    create: [
        payload: {
            name: string;
            description: string | null;
            member_limit: number;
            is_open: boolean;
        },
    ];
    join: [allianceId: number];
    updateAlliance: [
        payload: {
            allianceId: number;
            description?: string | null;
            is_open?: boolean;
        },
    ];
    leave: [allianceId: number];
    disband: [allianceId: number];
    kick: [payload: { allianceId: number; membershipId: number }];
    promote: [payload: { allianceId: number; membershipId: number }];
    demote: [payload: { allianceId: number; membershipId: number }];
    transferLeadership: [payload: { allianceId: number; membershipId: number }];
}>();

const createForm = reactive({
    name: '',
    description: '',
    member_limit: 20,
    is_open: true,
});
const updateForm = reactive({
    description: '',
    is_open: true,
});
const isLeaveConfirmationOpen = ref(false);
const isDisbandConfirmationOpen = ref(false);
const kickConfirmationMemberId = ref<number | null>(null);
const allianceSearchQuery = ref('');
const selectedAllianceId = ref<number | null>(null);
const roleChangeConfirmation = ref<{
    memberId: number;
    action: 'promote' | 'demote' | 'transferLeadership';
} | null>(null);

const currentAlliance = computed(() => props.alliances.current);
const filteredAlliances = computed(() => {
    const query = allianceSearchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.alliances.available;
    }

    return props.alliances.available.filter((alliance) => {
        const searchableValues = [
            alliance.name,
            alliance.slug,
            alliance.description ?? '',
            alliance.leaderName,
            ...alliance.members.map((member) => member.name),
        ];

        return searchableValues.some((value) =>
            value.toLowerCase().includes(query),
        );
    });
});
const selectedAlliance = computed(
    () =>
        props.alliances.available.find(
            (alliance) => alliance.id === selectedAllianceId.value,
        ) ?? null,
);
const canSubmitCreate = computed(
    () =>
        props.alliances.canCreate &&
        createForm.name.trim().length >= 3 &&
        !props.isSubmitting,
);
const canSubmitUpdate = computed(() => {
    const alliance = currentAlliance.value;

    if (
        !alliance ||
        props.isSubmitting ||
        (!alliance.canUpdate && !alliance.canUpdateVisibility)
    ) {
        return false;
    }

    const descriptionChanged =
        alliance.canUpdate &&
        (updateForm.description.trim() || null) !==
            (alliance.description?.trim() || null);
    const visibilityChanged =
        alliance.canUpdateVisibility && updateForm.is_open !== alliance.isOpen;

    return descriptionChanged || visibilityChanged;
});

const roleStyles = {
    leader: {
        label: 'Leader',
        icon: Crown,
        class: 'bg-[#f0d79a] text-[#2f2514]',
    },
    officer: {
        label: 'Officer',
        icon: Shield,
        class: 'bg-[#b9d4e6] text-[#172633]',
    },
    member: {
        label: 'Member',
        icon: UserRound,
        class: 'bg-[#d8d0c0] text-[#2a2721]',
    },
};

watch(
    currentAlliance,
    (alliance) => {
        updateForm.description = alliance?.description ?? '';
        updateForm.is_open = alliance?.isOpen ?? true;
    },
    { immediate: true },
);

watch(
    () => props.alliances.available,
    (alliances) => {
        if (
            selectedAllianceId.value !== null &&
            !alliances.some((alliance) => alliance.id === selectedAllianceId.value)
        ) {
            selectedAllianceId.value = null;
        }
    },
);

function createAlliance(): void {
    if (!canSubmitCreate.value) {
        return;
    }

    emit('create', {
        name: createForm.name.trim(),
        description: createForm.description.trim() || null,
        member_limit: createForm.member_limit,
        is_open: createForm.is_open,
    });
}

function joinAlliance(alliance: AllianceSummary): void {
    if (!alliance.canJoin || props.isSubmitting) {
        return;
    }

    emit('join', alliance.id);
}

function selectAlliance(alliance: AllianceSummary): void {
    selectedAllianceId.value =
        selectedAllianceId.value === alliance.id ? null : alliance.id;
}

function updateAlliance(): void {
    const alliance = currentAlliance.value;

    if (!alliance || !canSubmitUpdate.value) {
        return;
    }

    const payload: {
        allianceId: number;
        description?: string | null;
        is_open?: boolean;
    } = {
        allianceId: alliance.id,
    };

    if (alliance.canUpdate) {
        payload.description = updateForm.description.trim() || null;
    }

    if (alliance.canUpdateVisibility) {
        payload.is_open = updateForm.is_open;
    }

    emit('updateAlliance', payload);
}

function requestLeave(): void {
    if (!currentAlliance.value?.canLeave || props.isSubmitting) {
        return;
    }

    isLeaveConfirmationOpen.value = true;
    isDisbandConfirmationOpen.value = false;
    kickConfirmationMemberId.value = null;
    roleChangeConfirmation.value = null;
}

function cancelLeave(): void {
    isLeaveConfirmationOpen.value = false;
}

function confirmLeave(): void {
    if (!currentAlliance.value?.canLeave || props.isSubmitting) {
        return;
    }

    emit('leave', currentAlliance.value.id);
    isLeaveConfirmationOpen.value = false;
}

function requestDisband(): void {
    if (!currentAlliance.value?.canDisband || props.isSubmitting) {
        return;
    }

    isDisbandConfirmationOpen.value = true;
    isLeaveConfirmationOpen.value = false;
    kickConfirmationMemberId.value = null;
    roleChangeConfirmation.value = null;
}

function cancelDisband(): void {
    isDisbandConfirmationOpen.value = false;
}

function confirmDisband(): void {
    if (!currentAlliance.value?.canDisband || props.isSubmitting) {
        return;
    }

    emit('disband', currentAlliance.value.id);
    isDisbandConfirmationOpen.value = false;
}

function requestKick(member: AllianceMember): void {
    if (!member.canKick || props.isSubmitting) {
        return;
    }

    kickConfirmationMemberId.value = member.id;
    roleChangeConfirmation.value = null;
    isLeaveConfirmationOpen.value = false;
    isDisbandConfirmationOpen.value = false;
}

function requestPromote(member: AllianceMember): void {
    if (!member.canPromote || props.isSubmitting) {
        return;
    }

    roleChangeConfirmation.value = {
        memberId: member.id,
        action: 'promote',
    };
    kickConfirmationMemberId.value = null;
    isLeaveConfirmationOpen.value = false;
    isDisbandConfirmationOpen.value = false;
}

function requestDemote(member: AllianceMember): void {
    if (!member.canDemote || props.isSubmitting) {
        return;
    }

    roleChangeConfirmation.value = {
        memberId: member.id,
        action: 'demote',
    };
    kickConfirmationMemberId.value = null;
    isLeaveConfirmationOpen.value = false;
    isDisbandConfirmationOpen.value = false;
}

function requestTransferLeadership(member: AllianceMember): void {
    if (!member.canTransferLeadership || props.isSubmitting) {
        return;
    }

    roleChangeConfirmation.value = {
        memberId: member.id,
        action: 'transferLeadership',
    };
    kickConfirmationMemberId.value = null;
    isLeaveConfirmationOpen.value = false;
    isDisbandConfirmationOpen.value = false;
}

function cancelRoleChange(): void {
    roleChangeConfirmation.value = null;
}

function confirmRoleChange(member: AllianceMember): void {
    if (!currentAlliance.value || !roleChangeConfirmation.value) {
        return;
    }

    if (
        roleChangeConfirmation.value.memberId !== member.id ||
        props.isSubmitting
    ) {
        return;
    }

    if (roleChangeConfirmation.value.action === 'promote') {
        promoteMember(member);

        return;
    }

    if (roleChangeConfirmation.value.action === 'transferLeadership') {
        transferLeadership(member);

        return;
    }

    demoteMember(member);
}

function promoteMember(member: AllianceMember): void {
    if (!currentAlliance.value || !member.canPromote || props.isSubmitting) {
        return;
    }

    emit('promote', {
        allianceId: currentAlliance.value.id,
        membershipId: member.id,
    });
    roleChangeConfirmation.value = null;
}

function demoteMember(member: AllianceMember): void {
    if (!currentAlliance.value || !member.canDemote || props.isSubmitting) {
        return;
    }

    emit('demote', {
        allianceId: currentAlliance.value.id,
        membershipId: member.id,
    });
    roleChangeConfirmation.value = null;
}

function transferLeadership(member: AllianceMember): void {
    if (
        !currentAlliance.value ||
        !member.canTransferLeadership ||
        props.isSubmitting
    ) {
        return;
    }

    emit('transferLeadership', {
        allianceId: currentAlliance.value.id,
        membershipId: member.id,
    });
    roleChangeConfirmation.value = null;
}

function cancelKick(): void {
    kickConfirmationMemberId.value = null;
}

function confirmKick(member: AllianceMember): void {
    if (!currentAlliance.value || !member.canKick || props.isSubmitting) {
        return;
    }

    emit('kick', {
        allianceId: currentAlliance.value.id,
        membershipId: member.id,
    });
    kickConfirmationMemberId.value = null;
}
</script>

<template>
    <section
        class="max-h-[calc(100vh-3rem)] w-full max-w-5xl overflow-y-auto rounded-lg border border-[#ded2bd] bg-[#fffaf0] p-5 text-[#1f241c] shadow-xl dark:border-[#38362f] dark:bg-[#1a1d15] dark:text-[#f3efe4]"
    >
        <header class="flex items-start justify-between gap-4">
            <div>
                <p
                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                >
                    Alliance
                </p>
                <h2 class="mt-1 text-2xl font-bold">Kingdom alliances</h2>
            </div>
            <button
                type="button"
                class="rounded-md p-2 text-[#5d6356] transition hover:bg-[#ebe4d7] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                aria-label="Close alliance window"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </header>

        <div v-if="currentAlliance" class="mt-5 grid gap-5 lg:grid-cols-3">
            <div
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <p
                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                >
                    Current alliance
                </p>
                <h3 class="mt-2 text-xl font-bold">
                    {{ currentAlliance.name }}
                </h3>
                <p class="mt-2 text-sm text-[#696250] dark:text-[#b6ae9d]">
                    {{
                        currentAlliance.description ||
                        'No alliance description set.'
                    }}
                </p>

                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-[#696250] dark:text-[#b6ae9d]"
                            >Leader</span
                        >
                        <span class="font-semibold">{{
                            currentAlliance.leaderName
                        }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-[#696250] dark:text-[#b6ae9d]"
                            >Members</span
                        >
                        <span class="font-semibold">
                            {{ formatExactNumber(currentAlliance.memberCount) }}
                            /
                            {{ formatExactNumber(currentAlliance.memberLimit) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-[#696250] dark:text-[#b6ae9d]"
                            >Status</span
                        >
                        <span class="font-semibold">
                            {{ currentAlliance.isOpen ? 'Open' : 'Private' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-[#696250] dark:text-[#b6ae9d]"
                            >Your role</span
                        >
                        <span class="font-semibold capitalize">
                            {{ currentAlliance.currentUserRole }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="currentAlliance.canLeave"
                    class="mt-5 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
                >
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-[#b64b3f] px-4 py-2.5 text-sm font-semibold text-[#b64b3f] transition hover:bg-[#b64b3f] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#ff8f7f] dark:text-[#ffb0a5] dark:hover:bg-[#803227] dark:hover:text-white"
                        :disabled="isSubmitting"
                        @click="requestLeave"
                    >
                        <LogOut class="h-4 w-4" />
                        Leave alliance
                    </button>

                    <div
                        v-if="isLeaveConfirmationOpen"
                        class="mt-3 rounded-md border border-[#e4dac7] bg-[#fff8eb] p-3 dark:border-[#35332c] dark:bg-[#11150f]"
                    >
                        <p
                            class="text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            Leave
                            <span
                                class="font-bold text-[#1f241c] dark:text-[#f3efe4]"
                            >
                                {{ currentAlliance.name }}
                            </span>
                            ?
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                                @click="cancelLeave"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-[#b64b3f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#8f382f] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isSubmitting"
                                @click="confirmLeave"
                            >
                                Confirm leave
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-md border border-[#e4dac7] dark:border-[#35332c] lg:col-span-2"
            >
                <div
                    class="grid grid-cols-[1fr_6.5rem_8rem_9rem] gap-3 border-b border-[#e4dac7] bg-[#f6f0e5] px-4 py-3 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:bg-[#151910] dark:text-[#b6ae9d]"
                >
                    <span>Member</span>
                    <span>Role</span>
                    <span class="text-right">Contribution</span>
                    <span class="text-right">Action</span>
                </div>

                <div
                    class="divide-y divide-[#e4dac7] dark:divide-[#35332c]"
                >
                    <div
                        v-for="member in currentAlliance.members"
                        :key="member.id"
                        class="grid grid-cols-[1fr_6.5rem_8rem_9rem] items-center gap-3 px-4 py-3 text-sm"
                        :class="
                            member.isCurrentUser
                                ? 'bg-[#edf6e8] text-[#243627] dark:bg-[#1d2a17] dark:text-[#d7edc5]'
                                : ''
                        "
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{ member.name }}
                                <span
                                    v-if="member.isCurrentUser"
                                    class="ml-2 rounded-sm bg-[#243627] px-2 py-0.5 text-xs text-white"
                                >
                                    You
                                </span>
                            </p>
                            <p
                                v-if="member.joinedAt"
                                class="mt-1 text-xs text-[#696250] dark:text-[#b6ae9d]"
                            >
                                Joined {{ member.joinedAt }}
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center justify-center gap-1 rounded-sm px-2 py-1 text-xs font-bold"
                            :class="roleStyles[member.role].class"
                        >
                            <component
                                :is="roleStyles[member.role].icon"
                                class="h-3.5 w-3.5"
                            />
                            {{ roleStyles[member.role].label }}
                        </span>

                        <span class="text-right font-bold">
                            {{
                                formatExactNumber(member.totalContributed)
                            }}
                        </span>

                        <div class="flex justify-end gap-1.5">
                            <button
                                v-if="member.canTransferLeadership"
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-[#caa66c] px-2.5 py-2 text-[#7b633d] transition hover:bg-[#7b633d] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#caa66c] dark:text-[#caa66c] dark:hover:bg-[#5b4729] dark:hover:text-white"
                                :disabled="isSubmitting"
                                :aria-label="`Transfer leadership to ${member.name}`"
                                @click="requestTransferLeadership(member)"
                            >
                                <Crown class="h-4 w-4" />
                            </button>
                            <button
                                v-if="member.canPromote"
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-[#4f7d46] px-2.5 py-2 text-[#4f7d46] transition hover:bg-[#4f7d46] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#9dcc84] dark:text-[#9dcc84] dark:hover:bg-[#365f2f] dark:hover:text-white"
                                :disabled="isSubmitting"
                                :aria-label="`Promote ${member.name}`"
                                @click="requestPromote(member)"
                            >
                                <ArrowUp class="h-4 w-4" />
                            </button>
                            <button
                                v-if="member.canDemote"
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-[#7b633d] px-2.5 py-2 text-[#7b633d] transition hover:bg-[#7b633d] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#caa66c] dark:text-[#caa66c] dark:hover:bg-[#5b4729] dark:hover:text-white"
                                :disabled="isSubmitting"
                                :aria-label="`Demote ${member.name}`"
                                @click="requestDemote(member)"
                            >
                                <ArrowDown class="h-4 w-4" />
                            </button>
                            <button
                                v-if="member.canKick"
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-[#b64b3f] px-2.5 py-2 text-[#b64b3f] transition hover:bg-[#b64b3f] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#ff8f7f] dark:text-[#ffb0a5] dark:hover:bg-[#803227] dark:hover:text-white"
                                :disabled="isSubmitting"
                                :aria-label="`Kick ${member.name}`"
                                @click="requestKick(member)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <div
                            v-if="kickConfirmationMemberId === member.id"
                            class="col-span-4 rounded-md border border-[#e4dac7] bg-[#fff8eb] p-3 dark:border-[#35332c] dark:bg-[#11150f]"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p
                                    class="text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    Kick
                                    <span
                                        class="font-bold text-[#1f241c] dark:text-[#f3efe4]"
                                    >
                                        {{ member.name }}
                                    </span>
                                    from the alliance?
                                </p>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                                        @click="cancelKick"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#b64b3f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#8f382f] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="isSubmitting"
                                        @click="confirmKick(member)"
                                    >
                                        Confirm kick
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                roleChangeConfirmation?.memberId === member.id
                            "
                            class="col-span-4 rounded-md border border-[#e4dac7] bg-[#fff8eb] p-3 dark:border-[#35332c] dark:bg-[#11150f]"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p
                                    class="text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                                >
                                    {{
                                        roleChangeConfirmation.action ===
                                        'transferLeadership'
                                            ? 'Transfer leadership to'
                                            : roleChangeConfirmation.action ===
                                                'promote'
                                              ? 'Promote'
                                              : 'Demote'
                                    }}
                                    <span
                                        class="font-bold text-[#1f241c] dark:text-[#f3efe4]"
                                    >
                                        {{ member.name }}
                                    </span>
                                    {{
                                        roleChangeConfirmation.action ===
                                        'transferLeadership'
                                            ? ''
                                            : roleChangeConfirmation.action ===
                                                'promote'
                                              ? 'to officer'
                                              : 'to member'
                                    }}?
                                </p>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                                        @click="cancelRoleChange"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#243627] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="isSubmitting"
                                        @click="confirmRoleChange(member)"
                                    >
                                        {{
                                            roleChangeConfirmation.action ===
                                            'transferLeadership'
                                                ? 'Confirm transfer'
                                                : roleChangeConfirmation.action ===
                                                    'promote'
                                                  ? 'Confirm promote'
                                                  : 'Confirm demote'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form
                    v-if="
                        currentAlliance.canUpdate ||
                        currentAlliance.canUpdateVisibility
                    "
                    class="mt-5 grid gap-3 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
                    @submit.prevent="updateAlliance"
                >
                    <p class="text-sm font-bold">Alliance settings</p>

                    <label
                        v-if="currentAlliance.canUpdate"
                        class="grid gap-1 text-sm font-semibold"
                    >
                        Description
                        <textarea
                            v-model="updateForm.description"
                            maxlength="1000"
                            rows="4"
                            class="resize-none rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                        ></textarea>
                    </label>

                    <label
                        v-if="currentAlliance.canUpdateVisibility"
                        class="flex items-center justify-between gap-3 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold dark:border-[#4a4438]"
                    >
                        Open joining
                        <input
                            v-model="updateForm.is_open"
                            type="checkbox"
                            class="h-5 w-5 accent-[#243627]"
                        />
                    </label>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!canSubmitUpdate"
                    >
                        {{ isSubmitting ? 'Saving...' : 'Save changes' }}
                    </button>
                </form>

                <div
                    v-if="currentAlliance.canDisband"
                    class="mt-5 border-t border-[#e4dac7] pt-4 dark:border-[#35332c]"
                >
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-[#b64b3f] px-4 py-2.5 text-sm font-semibold text-[#b64b3f] transition hover:bg-[#b64b3f] hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-[#ff8f7f] dark:text-[#ffb0a5] dark:hover:bg-[#803227] dark:hover:text-white"
                        :disabled="isSubmitting"
                        @click="requestDisband"
                    >
                        <Trash2 class="h-4 w-4" />
                        Disband alliance
                    </button>

                    <div
                        v-if="isDisbandConfirmationOpen"
                        class="mt-3 rounded-md border border-[#e4dac7] bg-[#fff8eb] p-3 dark:border-[#35332c] dark:bg-[#11150f]"
                    >
                        <p
                            class="text-sm font-medium text-[#5d6356] dark:text-[#c6c0b3]"
                        >
                            Disband
                            <span
                                class="font-bold text-[#1f241c] dark:text-[#f3efe4]"
                            >
                                {{ currentAlliance.name }}
                            </span>
                            ? If other members remain, leadership must be
                            assigned first.
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold text-[#4f574b] transition hover:bg-[#ebe4d7] dark:border-[#4a4438] dark:text-[#c6c0b3] dark:hover:bg-[#24281d]"
                                @click="cancelDisband"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md bg-[#b64b3f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#8f382f] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isSubmitting"
                                @click="confirmDisband"
                            >
                                Confirm disband
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section
            v-if="currentAlliance"
            class="mt-5 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold">Other alliances</h3>
                    <p class="mt-1 text-sm text-[#696250] dark:text-[#b6ae9d]">
                        Search alliances and inspect member contributions.
                    </p>
                </div>
                <label class="grid gap-1 text-sm font-semibold sm:w-72">
                    Search
                    <input
                        v-model="allianceSearchQuery"
                        type="search"
                        placeholder="Name, leader, member"
                        class="rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                    />
                </label>
            </div>

            <div
                v-if="props.alliances.available.length === 0"
                class="mt-4 rounded-md border border-[#e4dac7] p-4 text-sm text-[#696250] dark:border-[#35332c] dark:text-[#b6ae9d]"
            >
                No other alliances available yet.
            </div>

            <div
                v-else-if="filteredAlliances.length === 0"
                class="mt-4 rounded-md border border-[#e4dac7] p-4 text-sm text-[#696250] dark:border-[#35332c] dark:text-[#b6ae9d]"
            >
                No alliances match your search.
            </div>

            <div v-else class="mt-4 grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="grid gap-3">
                    <article
                        v-for="alliance in filteredAlliances"
                        :key="alliance.id"
                        role="button"
                        tabindex="0"
                        class="rounded-md border p-4 text-left transition hover:border-[#c9b995] hover:bg-[#fff8eb] dark:hover:border-[#5a523f] dark:hover:bg-[#151910]"
                        :class="
                            selectedAllianceId === alliance.id
                                ? 'border-[#caa66c] bg-[#fff8eb] dark:border-[#caa66c] dark:bg-[#151910]'
                                : 'border-[#e4dac7] dark:border-[#35332c]'
                        "
                        @click="selectAlliance(alliance)"
                        @keydown.enter.prevent="selectAlliance(alliance)"
                        @keydown.space.prevent="selectAlliance(alliance)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="font-bold">{{ alliance.name }}</h4>
                                <p
                                    class="mt-1 line-clamp-2 text-sm text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    {{
                                        alliance.description ||
                                        'No alliance description set.'
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-sm px-2 py-1 text-xs font-bold"
                                :class="
                                    alliance.isOpen
                                        ? 'bg-[#d7edc5] text-[#243627]'
                                        : 'bg-[#ead9d4] text-[#6f2f27]'
                                "
                            >
                                {{ alliance.isOpen ? 'Open' : 'Private' }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                        >
                            {{ alliance.leaderName }} ·
                            {{ formatExactNumber(alliance.memberCount) }}
                            /
                            {{ formatExactNumber(alliance.memberLimit) }}
                            members
                        </p>
                    </article>
                </div>

                <div
                    class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <template v-if="selectedAlliance">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                                >
                                    Members
                                </p>
                                <h4 class="mt-1 text-lg font-bold">
                                    {{ selectedAlliance.name }}
                                </h4>
                            </div>
                            <span class="text-sm font-semibold">
                                {{
                                    formatExactNumber(
                                        selectedAlliance.memberCount,
                                    )
                                }}
                                players
                            </span>
                        </div>

                        <div
                            class="mt-4 grid grid-cols-[1fr_6rem_8rem] gap-3 border-b border-[#e4dac7] pb-2 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:text-[#b6ae9d]"
                        >
                            <span>Member</span>
                            <span>Role</span>
                            <span class="text-right">Contribution</span>
                        </div>
                        <div class="divide-y divide-[#e4dac7] dark:divide-[#35332c]">
                            <div
                                v-for="member in selectedAlliance.members"
                                :key="member.id"
                                class="grid grid-cols-[1fr_6rem_8rem] items-center gap-3 py-3 text-sm"
                            >
                                <span class="truncate font-semibold">
                                    {{ member.name }}
                                </span>
                                <span class="capitalize">{{ member.role }}</span>
                                <span class="text-right font-bold">
                                    {{
                                        formatExactNumber(
                                            member.totalContributed,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-sm text-[#696250] dark:text-[#b6ae9d]">
                        Select an alliance to view members and contribution
                        totals.
                    </p>
                </div>
            </div>
        </section>

        <div v-else class="mt-5 grid gap-5 lg:grid-cols-[1fr_1.3fr]">
            <form
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                @submit.prevent="createAlliance"
            >
                <div class="flex items-center gap-3">
                    <div class="rounded-md bg-[#243627] p-2 text-white">
                        <UsersRound class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Create alliance</h3>
                        <p
                            class="text-sm leading-6 text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Start a new group for your kingdom.
                        </p>
                    </div>
                </div>

                <fieldset
                    :disabled="!alliances.canCreate || isSubmitting"
                    class="mt-4 grid gap-3 disabled:opacity-60"
                >
                    <label class="grid gap-1 text-sm font-semibold">
                        Name
                        <input
                            v-model="createForm.name"
                            type="text"
                            maxlength="80"
                            class="rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                        />
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Description
                        <textarea
                            v-model="createForm.description"
                            maxlength="1000"
                            rows="3"
                            class="resize-none rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                        ></textarea>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold">
                            Member limit
                            <input
                                v-model.number="createForm.member_limit"
                                type="number"
                                min="2"
                                max="100"
                                class="rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                            />
                        </label>

                        <label
                            class="flex items-center justify-between gap-3 rounded-md border border-[#d7cbb8] px-3 py-2 text-sm font-semibold dark:border-[#4a4438]"
                        >
                            Open joining
                            <input
                                v-model="createForm.is_open"
                                type="checkbox"
                                class="h-5 w-5 accent-[#243627]"
                            />
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-md bg-[#243627] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!canSubmitCreate"
                    >
                        {{
                            isSubmitting
                                ? 'Creating...'
                                : alliances.canCreate
                                  ? 'Create alliance'
                                  : 'Creation on cooldown'
                        }}
                    </button>
                </fieldset>
            </form>

            <div
                class="rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h3 class="text-lg font-bold">Available alliances</h3>
                        <p
                            class="mt-1 text-sm text-[#696250] dark:text-[#b6ae9d]"
                        >
                            Browse alliances, inspect members, and join open
                            groups.
                        </p>
                    </div>
                    <label class="grid gap-1 text-sm font-semibold sm:w-72">
                        Search
                        <input
                            v-model="allianceSearchQuery"
                            type="search"
                            placeholder="Name, leader, member"
                            class="rounded-md border border-[#d7cbb8] bg-white px-3 py-2 text-[#1f241c] dark:border-[#4a4438] dark:bg-[#11150f] dark:text-[#f3efe4]"
                        />
                    </label>
                </div>

                <div
                    v-if="alliances.available.length === 0"
                    class="mt-4 rounded-md border border-[#e4dac7] p-4 text-sm text-[#696250] dark:border-[#35332c] dark:text-[#b6ae9d]"
                >
                    No alliances available yet.
                </div>

                <div
                    v-else-if="filteredAlliances.length === 0"
                    class="mt-4 rounded-md border border-[#e4dac7] p-4 text-sm text-[#696250] dark:border-[#35332c] dark:text-[#b6ae9d]"
                >
                    No alliances match your search.
                </div>

                <div v-else class="mt-4 grid gap-4">
                    <article
                        v-for="alliance in filteredAlliances"
                        :key="alliance.id"
                        role="button"
                        tabindex="0"
                        class="rounded-md border p-4 text-left transition hover:border-[#c9b995] hover:bg-[#fff8eb] dark:hover:border-[#5a523f] dark:hover:bg-[#151910]"
                        :class="
                            selectedAllianceId === alliance.id
                                ? 'border-[#caa66c] bg-[#fff8eb] dark:border-[#caa66c] dark:bg-[#151910]'
                                : 'border-[#e4dac7] dark:border-[#35332c]'
                        "
                        @click="selectAlliance(alliance)"
                        @keydown.enter.prevent="selectAlliance(alliance)"
                        @keydown.space.prevent="selectAlliance(alliance)"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h4 class="font-bold">{{ alliance.name }}</h4>
                                <p
                                    class="mt-1 text-sm text-[#696250] dark:text-[#b6ae9d]"
                                >
                                    {{
                                        alliance.description ||
                                        'No alliance description set.'
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-xs font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                                >
                                    {{ alliance.leaderName }} ·
                                    {{ formatExactNumber(alliance.memberCount) }}
                                    /
                                    {{ formatExactNumber(alliance.memberLimit) }}
                                    members ·
                                    {{ alliance.isOpen ? 'Open' : 'Private' }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-md bg-[#243627] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1a291d] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!alliance.canJoin || isSubmitting"
                                @click.stop="joinAlliance(alliance)"
                            >
                                {{
                                    alliance.canJoin
                                        ? isSubmitting
                                            ? 'Joining...'
                                            : 'Join'
                                        : alliance.isOpen
                                          ? 'Full'
                                          : 'Private'
                                }}
                            </button>
                        </div>
                    </article>
                </div>

                <div
                    v-if="selectedAlliance"
                    class="mt-4 rounded-md border border-[#e4dac7] p-4 dark:border-[#35332c]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold tracking-wider text-[#7b633d] uppercase dark:text-[#caa66c]"
                            >
                                Members
                            </p>
                            <h4 class="mt-1 text-lg font-bold">
                                {{ selectedAlliance.name }}
                            </h4>
                        </div>
                        <span class="text-sm font-semibold">
                            {{
                                formatExactNumber(selectedAlliance.memberCount)
                            }}
                            players
                        </span>
                    </div>

                    <div
                        class="mt-4 grid grid-cols-[1fr_6rem_8rem] gap-3 border-b border-[#e4dac7] pb-2 text-xs font-semibold tracking-wider text-[#696250] uppercase dark:border-[#35332c] dark:text-[#b6ae9d]"
                    >
                        <span>Member</span>
                        <span>Role</span>
                        <span class="text-right">Contribution</span>
                    </div>
                    <div class="divide-y divide-[#e4dac7] dark:divide-[#35332c]">
                        <div
                            v-for="member in selectedAlliance.members"
                            :key="member.id"
                            class="grid grid-cols-[1fr_6rem_8rem] items-center gap-3 py-3 text-sm"
                        >
                            <span class="truncate font-semibold">
                                {{ member.name }}
                            </span>
                            <span class="capitalize">{{ member.role }}</span>
                            <span class="text-right font-bold">
                                {{
                                    formatExactNumber(member.totalContributed)
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
