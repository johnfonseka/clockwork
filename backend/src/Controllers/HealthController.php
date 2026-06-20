<?php

declare(strict_types=1);

namespace Clockwork\Controllers;

use Clockwork\Database;
use Clockwork\Http\Json;

final class HealthController
{
    public function show(): void
    {
        $database = 'ok';
        try {
            Database::connection()->query('SELECT 1');
        } catch (\Throwable $e) {
            $database = 'error';
        }

        Json::send([
            'status' => 'ok',
            'service' => 'clockwork-api',
            'database' => $database,
            'time' => gmdate('Y-m-d H:i:s') . ' UTC',
        ], $database === 'ok' ? 200 : 503);
    }
}
