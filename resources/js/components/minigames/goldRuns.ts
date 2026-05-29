export type GoldObstacleDefinition = {
    id: string;
    name: string;
    image: string;
};

export type GoldRunTheme = {
    id: string;
    name: string;
    pullerName: string;
    pullerImage: string;
    backgroundClass: string;
    horizonClass: string;
    roadClass: string;
    laneClass: string;
    obstacles: GoldObstacleDefinition[];
    targetDistance: number;
    baseSpeed: number;
    minSpeed: number;
    accelerationResponsiveness: number;
    slowdownOnHit: number;
    obstacleSpeed: number;
    minSpawnDelayMs: number;
    maxSpawnDelayMs: number;
};

const barrelObstacle: GoldObstacleDefinition = {
    id: 'barrel',
    name: 'Barrel',
    image: new URL('./assets/gold/barrel.svg', import.meta.url).href,
};

const logObstacle: GoldObstacleDefinition = {
    id: 'log',
    name: 'Fallen log',
    image: new URL('./assets/gold/log.svg', import.meta.url).href,
};

const rockObstacle: GoldObstacleDefinition = {
    id: 'rock',
    name: 'Rock',
    image: new URL('./assets/gold/rock.svg', import.meta.url).href,
};

const cactusObstacle: GoldObstacleDefinition = {
    id: 'cactus',
    name: 'Cactus',
    image: new URL('./assets/gold/cactus.svg', import.meta.url).href,
};

const tumbleweedObstacle: GoldObstacleDefinition = {
    id: 'tumbleweed',
    name: 'Tumbleweed',
    image: new URL('./assets/gold/tumbleweed.svg', import.meta.url).href,
};

const boneObstacle: GoldObstacleDefinition = {
    id: 'bone',
    name: 'Sun-bleached bones',
    image: new URL('./assets/gold/bone.svg', import.meta.url).href,
};

const snowPineObstacle: GoldObstacleDefinition = {
    id: 'snow-pine',
    name: 'Snowy pine',
    image: new URL('./assets/gold/snow-pine.svg', import.meta.url).href,
};

const iceRockObstacle: GoldObstacleDefinition = {
    id: 'ice-rock',
    name: 'Ice rock',
    image: new URL('./assets/gold/ice-rock.svg', import.meta.url).href,
};

export const goldRunThemes: GoldRunTheme[] = [
    {
        id: 'meadow-road',
        name: 'Meadow road',
        pullerName: 'Horse',
        pullerImage: new URL(
            './assets/gold/horse-svgrepo-com.svg',
            import.meta.url,
        ).href,
        backgroundClass: 'bg-[#c9dfb0] dark:bg-[#102010]',
        horizonClass: 'bg-[#789e5a] dark:bg-[#273f25]',
        roadClass: 'bg-[#8b6a43] dark:bg-[#3a2d20]',
        laneClass: 'border-[#d8c28e]/65 dark:border-[#6d5a38]/70',
        obstacles: [barrelObstacle, logObstacle, rockObstacle],
        targetDistance: 1200,
        baseSpeed: 56,
        minSpeed: 34,
        accelerationResponsiveness: 7,
        slowdownOnHit: 32,
        obstacleSpeed: 31,
        minSpawnDelayMs: 760,
        maxSpawnDelayMs: 1250,
    },
    {
        id: 'desert-trail',
        name: 'Desert trail',
        pullerName: 'Camel',
        pullerImage: new URL(
            './assets/gold/camel-svgrepo-com.svg',
            import.meta.url,
        ).href,
        backgroundClass: 'bg-[#efd394] dark:bg-[#2a2113]',
        horizonClass: 'bg-[#cc9e52] dark:bg-[#5b3f1f]',
        roadClass: 'bg-[#b98345] dark:bg-[#4d3420]',
        laneClass: 'border-[#f3d68f]/70 dark:border-[#7a5630]/75',
        obstacles: [
            cactusObstacle,
            tumbleweedObstacle,
            boneObstacle,
            rockObstacle,
        ],
        targetDistance: 1300,
        baseSpeed: 53,
        minSpeed: 32,
        accelerationResponsiveness: 6.5,
        slowdownOnHit: 32,
        obstacleSpeed: 30,
        minSpawnDelayMs: 760,
        maxSpawnDelayMs: 1320,
    },
    {
        id: 'snow-pass',
        name: 'Snow pass',
        pullerName: 'Winter horse',
        pullerImage: new URL(
            './assets/gold/horse-svgrepo-com.svg',
            import.meta.url,
        ).href,
        backgroundClass: 'bg-[#d9e7e4] dark:bg-[#111c22]',
        horizonClass: 'bg-[#9bb8b4] dark:bg-[#29434a]',
        roadClass: 'bg-[#879091] dark:bg-[#2f3738]',
        laneClass: 'border-[#edf5f4]/80 dark:border-[#68797a]/75',
        obstacles: [snowPineObstacle, iceRockObstacle, logObstacle],
        targetDistance: 1150,
        baseSpeed: 50,
        minSpeed: 30,
        accelerationResponsiveness: 6,
        slowdownOnHit: 30,
        obstacleSpeed: 29,
        minSpawnDelayMs: 820,
        maxSpawnDelayMs: 1380,
    },
];
