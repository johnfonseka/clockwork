<?php

declare(strict_types=1);

namespace Clockwork\Sync;

final class Timestamps
{
    /**
     * Normalises a timestamp string to UTC `Y-m-d H:i:s`, or `null` if empty or
     * unparseable.
     */
    public static function normalise(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
