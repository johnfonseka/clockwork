<?php

declare(strict_types=1);

namespace Clockwork\Tests\E2E;

final class SyncE2ETest extends E2ETestCase
{
    private const HABIT_ID = '11111111-1111-1111-1111-111111111111';
    private const ENTRY_ID = '22222222-2222-2222-2222-222222222222';

    /** @return array<string,mixed> */
    private function fullPayload(): array
    {
        return [
            'last_sync_timestamp' => null,
            'mutations' => [
                'habits' => [[
                    'id' => self::HABIT_ID,
                    'name' => 'Wake Up', 'category' => 'base', 'strictness_type' => 'strict',
                    'schedule_type' => 'weekly', 'schedule_value' => '1,2,3,4,5,6,7',
                    'target_start_time' => '06:30:00', 'target_duration_minutes' => 5,
                    'has_checklist' => false, 'is_active' => true,
                    'updated_at' => '2026-06-26 08:00:00',
                ]],
                'daily_logs' => [[
                    'log_date' => '2026-06-26', 'is_paused' => true, 'pause_reason' => 'Holiday',
                    'updated_at' => '2026-06-26 08:00:00',
                ]],
                'habit_entries' => [[
                    'id' => self::ENTRY_ID, 'log_date' => '2026-06-26', 'habit_id' => self::HABIT_ID,
                    'actual_start_time' => '06:32:00', 'actual_duration_minutes' => 6, 'completed' => true,
                    'checklist_state' => ['a' => true, 'b' => false],
                    'updated_at' => '2026-06-26 08:00:00',
                ]],
            ],
        ];
    }

    public function test_push_excludes_own_writes_then_pull_returns_them(): void
    {
        $push = $this->post('/api/sync', $this->fullPayload(), $this->devUser('alice'));
        $this->assertSame(200, $push['status']);
        $this->assertCount(0, $push['json']['changes']['habits']);
        $this->assertCount(0, $push['json']['changes']['habit_entries']);

        $pull = $this->post('/api/sync', [
            'last_sync_timestamp' => '2026-06-26 00:00:00',
            'mutations' => [],
        ], $this->devUser('alice'));

        $changes = $pull['json']['changes'];
        $this->assertCount(1, $changes['habits']);
        $this->assertCount(1, $changes['daily_logs']);
        $this->assertCount(1, $changes['habit_entries']);

        // Type normalisation survives a real round-trip.
        $this->assertSame('Wake Up', $changes['habits'][0]['name']);
        $this->assertFalse($changes['habits'][0]['has_checklist']);
        $this->assertSame(5, $changes['habits'][0]['target_duration_minutes']);
        $this->assertTrue($changes['daily_logs'][0]['is_paused']);
        $this->assertSame(['a' => true, 'b' => false], $changes['habit_entries'][0]['checklist_state']);
    }

    public function test_chained_habit_columns_round_trip(): void
    {
        $childId = '33333333-3333-3333-3333-333333333333';
        $push = $this->post('/api/sync', [
            'last_sync_timestamp' => null,
            'mutations' => ['habits' => [[
                'id' => $childId,
                'name' => 'Morning Workout', 'category' => 'health', 'strictness_type' => 'chained',
                'chain_parent_id' => self::HABIT_ID, 'chain_target_gap_minutes' => 15,
                'schedule_type' => 'weekly', 'schedule_value' => '1,2,3,4,5,6,7',
                'target_start_time' => '06:45:00', 'target_duration_minutes' => 45,
                'updated_at' => '2026-06-26 08:00:00',
            ]]],
        ], $this->devUser('carol'));
        $this->assertSame(200, $push['status']);

        $pull = $this->post('/api/sync', [
            'last_sync_timestamp' => '2026-06-26 00:00:00',
            'mutations' => [],
        ], $this->devUser('carol'));

        $habit = $pull['json']['changes']['habits'][0];
        $this->assertSame('chained', $habit['strictness_type']);
        $this->assertSame(self::HABIT_ID, $habit['chain_parent_id']);
        $this->assertSame(15, $habit['chain_target_gap_minutes']); // normalised to int
    }

    public function test_last_write_wins(): void
    {
        $this->post('/api/sync', $this->fullPayload(), $this->devUser('alice'));

        // Stale update (older updated_at) must be ignored.
        $this->post('/api/sync', ['mutations' => ['habits' => [[
            'id' => self::HABIT_ID, 'name' => 'STALE', 'category' => 'base',
            'schedule_type' => 'weekly', 'schedule_value' => '1', 'target_start_time' => '06:30:00',
            'target_duration_minutes' => 5, 'updated_at' => '2026-06-26 07:00:00',
        ]]]], $this->devUser('alice'));

        $this->assertSame('Wake Up', $this->habitName('alice'));

        // Newer update wins.
        $this->post('/api/sync', ['mutations' => ['habits' => [[
            'id' => self::HABIT_ID, 'name' => 'FRESH', 'category' => 'base',
            'schedule_type' => 'weekly', 'schedule_value' => '1', 'target_start_time' => '06:30:00',
            'target_duration_minutes' => 5, 'updated_at' => '2026-06-26 09:00:00',
        ]]]], $this->devUser('alice'));

        $this->assertSame('FRESH', $this->habitName('alice'));
    }

    public function test_per_user_isolation(): void
    {
        $this->post('/api/sync', $this->fullPayload(), $this->devUser('alice'));

        $bob = $this->post('/api/sync', [
            'last_sync_timestamp' => null,
            'mutations' => [],
        ], $this->devUser('bob'));

        $changes = $bob['json']['changes'];
        $this->assertCount(0, $changes['habits']);
        $this->assertCount(0, $changes['daily_logs']);
        $this->assertCount(0, $changes['habit_entries']);
    }

    public function test_invalid_payload_is_422(): void
    {
        $response = $this->post('/api/sync', ['mutations' => ['habits' => [[
            'id' => 'x', 'category' => 'base', // missing name, schedule_*, target_*
        ]]]], $this->devUser('alice'));

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString("missing required field 'name'", $response['json']['error']);
    }

    private function habitName(string $user): string
    {
        $pull = $this->post('/api/sync', [
            'last_sync_timestamp' => '2026-06-26 00:00:00',
            'mutations' => [],
        ], $this->devUser($user));

        return $pull['json']['changes']['habits'][0]['name'];
    }
}
