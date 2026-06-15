import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

if (typeof reverbAppKey === 'string' && reverbAppKey.trim() !== '') {
    configureEcho({
        broadcaster: 'reverb',
    });
} else if (import.meta.env.DEV) {
    console.warn(
        'Reverb Echo was not configured because VITE_REVERB_APP_KEY is missing.',
    );
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
