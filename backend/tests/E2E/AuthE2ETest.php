<?php

declare(strict_types=1);

namespace Clockwork\Tests\E2E;

final class AuthE2ETest extends E2ETestCase
{
    public function test_sync_without_auth_is_401(): void
    {
        $response = $this->post('/api/sync', ['mutations' => []]);
        $this->assertSame(401, $response['status']);
    }

    public function test_auth_without_token_is_400(): void
    {
        $response = $this->post('/api/auth', []);
        $this->assertSame(400, $response['status']);
    }

    public function test_dev_user_header_authenticates(): void
    {
        $response = $this->post('/api/sync', ['mutations' => []], $this->devUser('auth-e2e'));
        $this->assertSame(200, $response['status']);
        $this->assertArrayHasKey('server_timestamp', $response['json']);
    }
}
