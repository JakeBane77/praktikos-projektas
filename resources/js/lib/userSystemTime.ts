export type UserSystemTime = {
    date: Date;
    iso: string;
    timestamp: number;
    timezone: string;
    timezoneOffsetMinutes: number;
    hour: number;
    minute: number;
    second: number;
    hourDecimal: number;
};

export function getUserSystemTime(date = new Date()): UserSystemTime {
    return {
        date,
        iso: date.toISOString(),
        timestamp: date.getTime(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        timezoneOffsetMinutes: date.getTimezoneOffset(),
        hour: date.getHours(),
        minute: date.getMinutes(),
        second: date.getSeconds(),
        hourDecimal:
            date.getHours() + date.getMinutes() / 60 + date.getSeconds() / 3600,
    };
}
