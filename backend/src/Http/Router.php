<?php

declare(strict_types=1);

namespace Clockwork\Http;

/**
 * Minimal exact-match router. Sufficient for the small, fixed API surface;
 * swap for path patterns if the route table grows.
 */
final class Router
{
    /** @var list<array{method:string,path:string,handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        $method = strtoupper($method);

        $pathMatched = false;
        foreach ($this->routes as $route) {
            if ($route['path'] !== $path) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] === $method) {
                ($route['handler'])();

                return;
            }
        }

        if ($pathMatched) {
            Json::error('Method not allowed', 405);

            return;
        }

        Json::error('Not found', 404);
    }
}
