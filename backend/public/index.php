<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Clockwork\Config;
use Clockwork\Controllers\AuthController;
use Clockwork\Controllers\HealthController;
use Clockwork\Http\Json;
use Clockwork\Http\Router;

// Promote PHP warnings/notices to exceptions so they surface as 500s.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$router = new Router();
$router->get('/api/health', [new HealthController(), 'show']);
$router->post('/api/auth', [new AuthController(), 'authenticate']);

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/'
    );
} catch (\Throwable $e) {
    Json::error(
        Config::isDev() ? $e->getMessage() : 'Internal server error',
        500
    );
}
