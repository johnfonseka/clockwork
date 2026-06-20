<?php

declare(strict_types=1);

namespace Clockwork\Http;

/**
 * Helpers for reading JSON request bodies and writing JSON responses.
 */
final class Json
{
    public static function send(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $status): void
    {
        self::send(['error' => $message], $status);
    }

    /**
     * Decodes the request body as a JSON object.
     *
     * @return array<string,mixed>
     */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
