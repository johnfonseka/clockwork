<?php

declare(strict_types=1);

namespace Clockwork\Tests\E2E;

final class HealthE2ETest extends E2ETestCase
{
    public function test_health_reports_db_ok(): void
    {
        $response = $this->get('/api/health');

        $this->assertSame(200, $response['status']);
        $this->assertSame('ok', $response['json']['status']);
        $this->assertSame('ok', $response['json']['database']);
    }
}
