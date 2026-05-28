export type StoneRockDefinition = {
    id: string;
    name: string;
    image: string;
    minHealth: number;
    maxHealth: number;
    spotMinDistance: number;
};

export const stoneRocks: StoneRockDefinition[] = [
    {
        id: 'granite',
        name: 'Granite',
        image: new URL('./assets/rocks/granite.svg', import.meta.url).href,
        minHealth: 10,
        maxHealth: 15,
        spotMinDistance: 24,
    },
    {
        id: 'slate',
        name: 'Slate',
        image: new URL('./assets/rocks/slate.svg', import.meta.url).href,
        minHealth: 9,
        maxHealth: 13,
        spotMinDistance: 28,
    },
    {
        id: 'basalt',
        name: 'Basalt',
        image: new URL('./assets/rocks/basalt.svg', import.meta.url).href,
        minHealth: 13,
        maxHealth: 18,
        spotMinDistance: 22,
    },
    {
        id: 'crystal',
        name: 'Crystal ore',
        image: new URL('./assets/rocks/crystal.svg', import.meta.url).href,
        minHealth: 11,
        maxHealth: 16,
        spotMinDistance: 32,
    },
];
