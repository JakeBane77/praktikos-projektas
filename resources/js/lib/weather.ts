import {
    Cloud,
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Sun,
} from 'lucide-vue-next';
import type { Component } from 'vue';

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

const CLEAR_CODES = [0];
const CLOUDY_CODES = [1, 2, 3];
const RAIN_CODES = [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82];
const FOG_CODES = [45, 48];
const THUNDERSTORM_CODES = [95, 96, 99];
const SNOW_CODES = [71, 73, 75, 77, 85, 86];

function hasWeatherCode(codes: number[], weatherCode: number | null): boolean {
    return weatherCode !== null && codes.includes(weatherCode);
}

export function weatherConditionsForCode(
    weatherCode: number | null,
): WeatherConditions {
    return {
        clear: hasWeatherCode(CLEAR_CODES, weatherCode),
        cloudy: hasWeatherCode(CLOUDY_CODES, weatherCode),
        raining: hasWeatherCode(RAIN_CODES, weatherCode),
        foggy: hasWeatherCode(FOG_CODES, weatherCode),
        thunderstorm: hasWeatherCode(THUNDERSTORM_CODES, weatherCode),
        snowing: hasWeatherCode(SNOW_CODES, weatherCode),
    };
}

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
