<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Sync\RowNormaliser;
use PHPUnit\Framework\TestCase;

final class RowNormaliserTest extends TestCase
{
    // MARK: bindValue (client record -> DB value)

    public function test_user_id_is_injected_from_session(): void
    {
        $this->assertSame(42, RowNormaliser::bindValue('habits', 'user_id', [], 42));
    }

    public function test_updated_at_passthrough_and_fallback(): void
    {
        $this->assertSame(
            '2026-06-26 08:00:00',
            RowNormaliser::bindValue('habits', 'updated_at', ['updated_at' => '2026-06-26 08:00:00'], 1)
        );

        $generated = RowNormaliser::bindValue('habits', 'updated_at', [], 1);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $generated);
    }

    public function test_booleans_become_tinyint(): void
    {
        $this->assertSame(1, RowNormaliser::bindValue('habits', 'has_checklist', ['has_checklist' => true], 1));
        $this->assertSame(0, RowNormaliser::bindValue('habits', 'has_checklist', ['has_checklist' => false], 1));
    }

    public function test_defaults_applied_when_absent(): void
    {
        $this->assertSame(0, RowNormaliser::bindValue('habits', 'has_checklist', [], 1));
        $this->assertSame(1, RowNormaliser::bindValue('habits', 'is_active', [], 1));
        $this->assertSame(0, RowNormaliser::bindValue('daily_logs', 'is_paused', [], 1));
    }

    public function test_absent_nullable_is_null(): void
    {
        $this->assertNull(RowNormaliser::bindValue('daily_logs', 'pause_reason', [], 1));
        $this->assertNull(RowNormaliser::bindValue('habit_entries', 'external_source', [], 1));
    }

    public function test_json_column_is_encoded_from_array(): void
    {
        $value = RowNormaliser::bindValue('habit_entries', 'checklist_state', ['checklist_state' => ['a' => true]], 1);
        $this->assertSame('{"a":true}', $value);
    }

    public function test_json_column_string_passthrough(): void
    {
        $value = RowNormaliser::bindValue('habit_entries', 'checklist_state', ['checklist_state' => '{"a":true}'], 1);
        $this->assertSame('{"a":true}', $value);
    }

    // MARK: output (DB row -> JSON response)

    public function test_habit_output_types(): void
    {
        $row = RowNormaliser::output('habits', [
            'id' => 'uuid-stays-string',
            'user_id' => '7',
            'has_checklist' => '1',
            'is_active' => '0',
            'target_duration_minutes' => '5',
        ]);

        $this->assertSame('uuid-stays-string', $row['id']); // UUID, not cast
        $this->assertSame(7, $row['user_id']);
        $this->assertTrue($row['has_checklist']);
        $this->assertFalse($row['is_active']);
        $this->assertSame(5, $row['target_duration_minutes']);
    }

    public function test_daily_log_output_casts_int_id_and_bool(): void
    {
        $row = RowNormaliser::output('daily_logs', [
            'id' => '13',
            'user_id' => '7',
            'is_paused' => '1',
        ]);

        $this->assertSame(13, $row['id']); // server INT id IS cast
        $this->assertTrue($row['is_paused']);
    }

    public function test_habit_entry_output_decodes_json_and_keeps_uuid(): void
    {
        $row = RowNormaliser::output('habit_entries', [
            'id' => 'entry-uuid',
            'user_id' => '7',
            'completed' => '1',
            'actual_duration_minutes' => '6',
            'checklist_state' => '{"a":true,"b":false}',
        ]);

        $this->assertSame('entry-uuid', $row['id']);
        $this->assertTrue($row['completed']);
        $this->assertSame(6, $row['actual_duration_minutes']);
        $this->assertSame(['a' => true, 'b' => false], $row['checklist_state']);
    }

    public function test_null_nullable_int_stays_null_in_output(): void
    {
        $row = RowNormaliser::output('habit_entries', ['actual_duration_minutes' => null]);
        $this->assertNull($row['actual_duration_minutes']);
    }
}
