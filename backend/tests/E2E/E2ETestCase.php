<?php

declare(strict_types=1);

namespace Clockwork\Tests\E2E;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for end-to-end API tests. Each test runs against the live test
 * stack over HTTP and starts from an empty `clockwork_test` database (truncated
 * in setUp), so tests are isolated and deterministic.
 */
abstract class E2ETestCase extends TestCase
{
    protected string $baseUrl;
    private static ?PDO $db = null;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('BASE_URL') ?: 'http://localhost:8081';
        $this->truncateAll();
    }

    /** @return array{status:int,json:array<string,mixed>|null,raw:string} */
    protected function post(string $path, ?array $body, array $headers = []): array
    {
        return $this->request('POST', $path, $body, $headers);
    }

    /** @return array{status:int,json:array<string,mixed>|null,raw:string} */
    protected function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, null, $headers);
    }

    /** @return array<string,string> */
    protected function devUser(string $id): array
    {
        return ['X-Dev-User' => $id];
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string>     $headers
     *
     * @return array{status:int,json:array<string,mixed>|null,raw:string}
     */
    private function request(string $method, string $path, ?array $body, array $headers): array
    {
        $handle = curl_init($this->baseUrl . $path);
        $headerLines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, (string) json_encode($body));
        }

        $raw = (string) curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $json = json_decode($raw, true);

        return ['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => $raw];
    }

    private function db(): PDO
    {
        if (self::$db === null) {
            $host = getenv('DB_TEST_HOST') ?: '127.0.0.1';
            $port = getenv('DB_TEST_PORT') ?: '3307';
            $name = getenv('DB_TEST_NAME') ?: 'clockwork_test';
            $user = getenv('DB_TEST_USER') ?: 'clockwork';
            $pass = getenv('DB_TEST_PASSWORD') ?: 'test';

            self::$db = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }

        return self::$db;
    }

    private function truncateAll(): void
    {
        $db = $this->db();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['habit_entries', 'habit_checklists', 'daily_logs', 'habits', 'users'] as $table) {
            $db->exec("TRUNCATE TABLE {$table}");
        }
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
