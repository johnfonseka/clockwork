<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Sync\SyncValidator;
use Clockwork\Sync\SyncValidationException;
use PHPUnit\Framework\TestCase;

final class SyncValidatorTest extends TestCase
{
    private const VALID_HABIT = [
        'id' => 'h1',
        'name' => 'Wake Up',
        'category' => 'base',
        'schedule_type' => 'weekly',
        'schedule_value' => '1,2,3,4,5,6,7',
        'target_start_time' => '06:30:00',
        'target_duration_minutes' => 5,
    ];

    public function test_empty_mutations_pass(): void
    {
        $this->expectNotToPerformAssertions();
        SyncValidator::validate([]);
        SyncValidator::validate(['habits' => [], 'daily_logs' => [], 'habit_entries' => []]);
    }

    public function test_complete_records_pass(): void
    {
        $this->expectNotToPerformAssertions();
        SyncValidator::validate([
            'habits' => [self::VALID_HABIT],
            'daily_logs' => [['log_date' => '2026-06-26']],
            'habit_entries' => [['id' => 'e1', 'log_date' => '2026-06-26', 'habit_id' => 'h1']],
        ]);
    }

    public function test_missing_required_field_is_reported(): void
    {
        $habit = self::VALID_HABIT;
        unset($habit['name']);

        try {
            SyncValidator::validate(['habits' => [$habit]]);
            $this->fail('Expected SyncValidationException');
        } catch (SyncValidationException $e) {
            $this->assertStringContainsString("habits[0] missing required field 'name'", $e->getMessage());
        }
    }

    public function test_null_required_field_is_reported(): void
    {
        $this->expectException(SyncValidationException::class);
        SyncValidator::validate(['daily_logs' => [['log_date' => null]]]);
    }

    public function test_non_object_record_is_reported(): void
    {
        $this->expectExceptionMessage('habits[0] is not an object');
        SyncValidator::validate(['habits' => ['just a string']]);
    }

    public function test_all_errors_are_aggregated(): void
    {
        try {
            SyncValidator::validate([
                'habit_entries' => [['id' => 'e1']], // missing log_date and habit_id
            ]);
            $this->fail('Expected SyncValidationException');
        } catch (SyncValidationException $e) {
            $this->assertStringContainsString("missing required field 'log_date'", $e->getMessage());
            $this->assertStringContainsString("missing required field 'habit_id'", $e->getMessage());
        }
    }
}
