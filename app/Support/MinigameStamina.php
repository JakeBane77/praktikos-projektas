<?php

namespace App\Support;

class MinigameStamina
{
    public const SOURCE_PREFIX = 'minigame_';

    public const MAX_COMPLETIONS_PER_HOUR = 100;

    public const WINDOW_SECONDS = 3600;

    public static function sourceFor(string $resource): string
    {
        return self::SOURCE_PREFIX.$resource;
    }

    public static function sourcePattern(): string
    {
        return self::SOURCE_PREFIX.'%';
    }
}
