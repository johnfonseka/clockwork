<?php

declare(strict_types=1);

namespace Clockwork\Sync;

/**
 * Per-table sync metadata, shared by the sync engine and its helpers.
 *
 * - key:      columns forming the conflict target (natural identity)
 * - columns:  client-writable columns (user_id is injected separately)
 * - required: columns that must be present to insert a new row
 * - defaults: values used when an optional column is absent
 * - bools:    columns normalised to true/false on output
 * - ints:     columns normalised to int (nullable) on output
 * - json:     columns json-decoded on output / json-encoded on input
 */
final class SyncSchema
{
    public const TABLES = [
        'habits' => [
            'key' => ['id'],
            'columns' => [
                'id', 'name', 'category', 'strictness_type', 'schedule_type',
                'schedule_value', 'target_start_time', 'target_duration_minutes',
                'has_checklist', 'is_active', 'updated_at',
            ],
            'required' => [
                'id', 'name', 'category', 'schedule_type', 'schedule_value',
                'target_start_time', 'target_duration_minutes',
            ],
            'defaults' => ['has_checklist' => 0, 'is_active' => 1],
            'bools' => ['has_checklist', 'is_active'],
            'ints' => ['target_duration_minutes'],
            'json' => [],
        ],
        'daily_logs' => [
            'key' => ['user_id', 'log_date'],
            'columns' => ['log_date', 'is_paused', 'pause_reason', 'updated_at'],
            'required' => ['log_date'],
            'defaults' => ['is_paused' => 0],
            'bools' => ['is_paused'],
            'ints' => [],
            'json' => [],
        ],
        'habit_entries' => [
            'key' => ['id'],
            'columns' => [
                'id', 'log_date', 'habit_id', 'actual_start_time',
                'actual_duration_minutes', 'completed', 'checklist_state',
                'external_source', 'external_id', 'updated_at',
            ],
            'required' => ['id', 'log_date', 'habit_id'],
            'defaults' => ['completed' => 0],
            'bools' => ['completed'],
            'ints' => ['actual_duration_minutes'],
            'json' => ['checklist_state'],
        ],
    ];

    /** @return list<string> */
    public static function tableNames(): array
    {
        return array_keys(self::TABLES);
    }

    /** @return array<string,mixed> */
    public static function spec(string $table): array
    {
        return self::TABLES[$table];
    }

    /**
     * The client-supplied records for a table, always as a list.
     *
     * @param array<string,mixed> $mutations
     *
     * @return list<mixed>
     */
    public static function records(array $mutations, string $table): array
    {
        $value = $mutations[$table] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
