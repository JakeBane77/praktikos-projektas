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
    clear: boolean;
    cloudy: boolean;
    raining: boolean;
    foggy: boolean;
    thunderstorm: boolean;
    snowing: boolean;
};

export type WeatherSnapshot = {
    latitude: number;
    longitude: number;
    isUsingGeolocation: boolean;
    locationUpdatedAt: string | null;
    weatherCode: number | null;
    conditions: WeatherConditions;
    apiTime: string | null;
    apiTimeIso: string | null;
    updatedAt: string | null;
    updatedAtIso: string | null;
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

    if (conditions.cloudy) {
        return 'Cloudy';
    }

    if (conditions.clear) {
        return 'Clear';
    }

    return 'Unknown';
}

export function weatherIconFor(
    _weatherCode: number | null,
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

    if (conditions.clear) {
        return Sun;
    }

    if (conditions.cloudy) {
        return Cloud;
    }

    return CloudSun;
}
