import type { Component } from 'vue';
import {
    Cloud,
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Sun,
} from 'lucide-vue-next';

export type WeatherConditions = {
    sunny: boolean;
    raining: boolean;
    foggy: boolean;
    thunderstorm: boolean;
    snowing: boolean;
};

export type WeatherSnapshot = {
    latitude: number;
    longitude: number;
    weatherCode: number | null;
    conditions: WeatherConditions;
    apiTime: string | null;
    updatedAt: string | null;
};

export function weatherConditionLabel(conditions: WeatherConditions): string {
    if (conditions.thunderstorm) {
        return 'Thunderstorm';
    }

    if (conditions.snowing) {
        return 'Snowing';
    }

    if (conditions.raining) {
        return 'Raining';
    }

    if (conditions.foggy) {
        return 'Foggy';
    }

    if (conditions.sunny) {
        return 'Sunny';
    }

    return 'Unknown';
}

export function weatherIconFor(
    weatherCode: number | null,
    conditions: WeatherConditions,
): Component {
    if (conditions.thunderstorm) {
        return CloudLightning;
    }

    if (conditions.snowing) {
        return CloudSnow;
    }

    if (conditions.raining) {
        return CloudRain;
    }

    if (conditions.foggy) {
        return CloudFog;
    }

    if (weatherCode === 0) {
        return Sun;
    }

    if (conditions.sunny) {
        return CloudSun;
    }

    if (weatherCode === 3) {
        return Cloud;
    }

    return CloudSun;
}
