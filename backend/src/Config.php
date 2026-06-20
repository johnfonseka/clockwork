<?php

declare(strict_types=1);

namespace Clockwork;

/**
 * Thin wrapper over environment variables. Values are injected by Docker
 * Compose (see docker-compose.yml) or the host environment.
 */
final class Config
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new \RuntimeException("Missing required environment variable: {$key}");
        }

        return $value;
    }

    public static function isDev(): bool
    {
        return self::get('APP_ENV', 'production') === 'development';
    }
}
