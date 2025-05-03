<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final class ProjectPath
{
    /**
     * Get base path of the project.
     */
    public static function getBasePath(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * Get config path.
     */
    public static function getConfigPath(string $configFile = ''): string
    {
        $path = self::getBasePath() . '/config';

        if ($configFile !== '') {
            $path .= '/' . $configFile;
        }

        return $path;
    }
}
