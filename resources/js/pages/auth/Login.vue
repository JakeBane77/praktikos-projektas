<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Return to your kingdom',
        description:
            'Log in to collect resources, manage upgrades, and continue your prestige run.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 rounded-md border border-[#9abf83] bg-[#edf6e8] px-4 py-3 text-center text-sm font-semibold text-[#2d5b28] dark:border-[#4d6f41] dark:bg-[#172214] dark:text-[#9dcc84]"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email" class="text-[#3b3f36] dark:text-[#f3efe4]"
                    >Email address</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-[#3b3f36] dark:text-[#f3efe4]"
                        >Password</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-[#7b633d] underline-offset-4 hover:text-[#243627] dark:text-[#caa66c] dark:hover:text-[#f3efe4]"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center space-x-3 text-[#3b3f36] dark:text-[#f3efe4]"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full bg-[#243627] text-white hover:bg-[#1a291d]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Enter kingdom
            </Button>
        </div>

        <div class="text-center text-sm text-[#696250] dark:text-[#b6ae9d]">
            Don't have an account?
            <TextLink
                :href="register()"
                class="text-[#7b633d] underline-offset-4 hover:text-[#243627] dark:text-[#caa66c] dark:hover:text-[#f3efe4]"
                :tabindex="5"
            >
                Create kingdom
            </TextLink>
        </div>
    </Form>
</template>
