export type WoodTreeDefinition = {
    id: string;
    name: string;
    image: string;
    minHealth: number;
    maxHealth: number;
    minSpeed: number;
    maxSpeed: number;
    perfectHitRadius: number;
    hitRadius: number;
};

export const woodTrees: WoodTreeDefinition[] = [
    {
        id: 'oak',
        name: 'Oak',
        image: new URL('./assets/trees/oak.svg', import.meta.url).href,
        minHealth: 5,
        maxHealth: 8,
        minSpeed: 45,
        maxSpeed: 95,
        perfectHitRadius: 3,
        hitRadius: 10,
    },
    {
        id: 'pine',
        name: 'Pine',
        image: new URL('./assets/trees/pine.svg', import.meta.url).href,
        minHealth: 6,
        maxHealth: 9,
        minSpeed: 55,
        maxSpeed: 110,
        perfectHitRadius: 3,
        hitRadius: 9,
    },
    {
        id: 'ancient',
        name: 'Ancient tree',
        image: new URL('./assets/trees/ancient.svg', import.meta.url).href,
        minHealth: 8,
        maxHealth: 12,
        minSpeed: 35,
        maxSpeed: 75,
        perfectHitRadius: 2,
        hitRadius: 8,
    },
];
