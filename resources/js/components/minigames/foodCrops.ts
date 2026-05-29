export type FoodCropStage = {
    label: string;
    image: string;
};

export type FoodCropDefinition = {
    id: string;
    name: string;
    minStageDurationMs: number;
    maxStageDurationMs: number;
    stages: FoodCropStage[];
};

export const wiltedCropImage = new URL(
    './assets/crops/wilted.svg',
    import.meta.url,
).href;

export const foodCrops: FoodCropDefinition[] = [
    {
        id: 'wheat',
        name: 'Wheat',
        minStageDurationMs: 1800,
        maxStageDurationMs: 3200,
        stages: [
            {
                label: 'Seed',
                image: new URL('./assets/crops/wheat-0.svg', import.meta.url)
                    .href,
            },
            {
                label: 'Sprout',
                image: new URL('./assets/crops/wheat-1.svg', import.meta.url)
                    .href,
            },
            {
                label: 'Growing',
                image: new URL('./assets/crops/wheat-2.svg', import.meta.url)
                    .href,
            },
            {
                label: 'Ripe',
                image: new URL('./assets/crops/wheat-3.svg', import.meta.url)
                    .href,
            },
        ],
    },
    {
        id: 'strawberry',
        name: 'Strawberries',
        minStageDurationMs: 1600,
        maxStageDurationMs: 2900,
        stages: [
            {
                label: 'Seedling',
                image: new URL(
                    './assets/crops/strawberry-0.svg',
                    import.meta.url,
                ).href,
            },
            {
                label: 'Flowering',
                image: new URL(
                    './assets/crops/strawberry-1.svg',
                    import.meta.url,
                ).href,
            },
            {
                label: 'Fruit forming',
                image: new URL(
                    './assets/crops/strawberry-2.svg',
                    import.meta.url,
                ).href,
            },
            {
                label: 'Ripe',
                image: new URL(
                    './assets/crops/strawberry-3.svg',
                    import.meta.url,
                ).href,
            },
        ],
    },
    {
        id: 'carrot',
        name: 'Carrot',
        minStageDurationMs: 2000,
        maxStageDurationMs: 3600,
        stages: [
            {
                label: 'Sprout',
                image: new URL('./assets/crops/carrot-0.svg', import.meta.url)
                    .href,
            },
            {
                label: 'Rooting',
                image: new URL('./assets/crops/carrot-1.svg', import.meta.url)
                    .href,
            },
            {
                label: 'Ripe',
                image: new URL('./assets/crops/carrot-2.svg', import.meta.url)
                    .href,
            },
        ],
    },
];
