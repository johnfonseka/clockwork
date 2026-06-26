<?php

declare(strict_types=1);

namespace Clockwork\Sync;

use PDO;

/**
 * Last-write-wins delta sync (spec §5).
 *
 * For each table the engine upserts incoming mutations — applying a record only
 * when its `updated_at` is newer than the stored row (LWW) — then returns every
 * row changed since the client's `last_sync_timestamp` that did not originate
 * from this payload, plus the authoritative server timestamp to use next time.
 *
 * `user_id` is always taken from the authenticated session, never the payload.
 * `habit_checklists` is not synced (the checklist definition travels with its
 * habit); only the three tables named in the spec sync protocol are handled.
 */
final class SyncEngine
{
    private const EPOCH = '1970-01-01 00:00:00';

    /**
     * Per-table sync metadata.
     *
     * - key:      columns forming the conflict target (natural identity)
     * - columns:  client-writable columns (user_id is injected separately)
     * - required: columns that must be present to insert a new row
     * - defaults: values used when an optional column is absent
     * - bools:    columns normalised to true/false on output
     * - ints:     columns normalised to int (nullable) on output
     * - json:     columns json-decoded on output
     */
    private const TABLES = [
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
            'ints' => ['target_duration_minutes' => true],
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
            'ints' => ['actual_duration_minutes' => true],
            'json' => ['checklist_state'],
        ],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string,mixed> $mutations
     *
     * @return array{server_timestamp:string,changes:array<string,list<array<string,mixed>>>}
     *
     * @throws SyncValidationException
     */
    public function sync(int $userId, ?string $lastSync, array $mutations): array
    {
        $since = $this->normaliseTimestamp($lastSync) ?? self::EPOCH;

        $this->validate($mutations);

        $this->db->beginTransaction();
        try {
            $writtenKeys = [];
            foreach (array_keys(self::TABLES) as $table) {
                $records = $this->records($mutations, $table);
                $writtenKeys[$table] = $this->applyMutations($table, $userId, $records);
            }

            $serverTimestamp = (string) $this->db->query('SELECT UTC_TIMESTAMP()')->fetchColumn();

            $changes = [];
            foreach (array_keys(self::TABLES) as $table) {
                $changes[$table] = $this->changesSince($table, $userId, $since, $writtenKeys[$table]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'server_timestamp' => $serverTimestamp,
            'changes' => $changes,
        ];
    }

    /**
     * @throws SyncValidationException
     */
    private function validate(array $mutations): void
    {
        $errors = [];
        foreach (self::TABLES as $table => $spec) {
            foreach ($this->records($mutations, $table) as $index => $record) {
                if (!is_array($record)) {
                    $errors[] = "{$table}[{$index}] is not an object";
                    continue;
                }
                foreach ($spec['required'] as $column) {
                    if (!array_key_exists($column, $record) || $record[$column] === null) {
                        $errors[] = "{$table}[{$index}] missing required field '{$column}'";
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new SyncValidationException(implode('; ', $errors));
        }
    }

    /**
     * @return list<array<string,mixed>> the natural-key values of written rows
     */
    private function applyMutations(string $table, int $userId, array $records): array
    {
        if ($records === []) {
            return [];
        }

        $spec = self::TABLES[$table];
        $insertColumns = array_values(array_unique([...$spec['columns'], 'user_id']));
        $statement = $this->db->prepare($this->upsertSql($table, $insertColumns));

        $writtenKeys = [];
        foreach ($records as $record) {
            $params = [];
            foreach ($insertColumns as $column) {
                $params[$column] = $this->bindValue($table, $column, $record, $userId);
            }
            $statement->execute($params);

            $key = [];
            foreach ($spec['key'] as $keyColumn) {
                $key[$keyColumn] = $keyColumn === 'user_id' ? $userId : ($params[$keyColumn] ?? null);
            }
            $writtenKeys[] = $key;
        }

        return $writtenKeys;
    }

    /**
     * @param list<string> $insertColumns
     */
    private function upsertSql(string $table, array $insertColumns): string
    {
        $spec = self::TABLES[$table];
        $cols = implode(', ', $insertColumns);
        $placeholders = implode(', ', array_map(static fn (string $c): string => ":{$c}", $insertColumns));

        // Only overwrite a stored row when the incoming row is at least as new.
        $updates = [];
        foreach ($insertColumns as $column) {
            if (in_array($column, $spec['key'], true) || $column === 'user_id') {
                continue;
            }
            $updates[] = "{$column} = IF(VALUES(updated_at) >= updated_at, VALUES({$column}), {$column})";
        }

        return "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders}) "
            . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    private function bindValue(string $table, string $column, array $record, int $userId): mixed
    {
        if ($column === 'user_id') {
            return $userId;
        }
        if ($column === 'updated_at') {
            $value = $record['updated_at'] ?? null;
            return $this->normaliseTimestamp(is_string($value) ? $value : null) ?? gmdate('Y-m-d H:i:s');
        }

        $spec = self::TABLES[$table];
        if (array_key_exists($column, $record)) {
            $value = $record[$column];
            if (in_array($column, $spec['json'], true) && $value !== null && !is_string($value)) {
                return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            return $value;
        }

        return $spec['defaults'][$column] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $excludeKeys
     *
     * @return list<array<string,mixed>>
     */
    private function changesSince(string $table, int $userId, string $since, array $excludeKeys): array
    {
        $spec = self::TABLES[$table];
        $sql = "SELECT * FROM {$table} WHERE user_id = :user_id AND updated_at > :since";
        $params = ['user_id' => $userId, 'since' => $since];

        // Exclude rows we just wrote so the client never receives its own echo.
        $idKeys = array_values(array_filter($excludeKeys, static fn (array $k): bool => isset($k['id'])));
        if ($spec['key'] === ['id'] && $idKeys !== []) {
            $names = [];
            foreach ($idKeys as $i => $key) {
                $names[] = ":x{$i}";
                $params["x{$i}"] = $key['id'];
            }
            $sql .= ' AND id NOT IN (' . implode(', ', $names) . ')';
        } elseif ($table === 'daily_logs' && $excludeKeys !== []) {
            $names = [];
            foreach ($excludeKeys as $i => $key) {
                $names[] = ":d{$i}";
                $params["d{$i}"] = $key['log_date'];
            }
            $sql .= ' AND log_date NOT IN (' . implode(', ', $names) . ')';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->normaliseRow($table, $row), $rows);
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    private function normaliseRow(string $table, array $row): array
    {
        $spec = self::TABLES[$table];

        if (isset($row['id']) && $table !== 'habits' && $table !== 'habit_entries') {
            $row['id'] = (int) $row['id'];
        }
        if (isset($row['user_id'])) {
            $row['user_id'] = (int) $row['user_id'];
        }
        foreach ($spec['bools'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = (bool) $row[$column];
            }
        }
        foreach ($spec['ints'] as $column => $enabled) {
            if ($enabled && array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (int) $row[$column];
            }
        }
        foreach ($spec['json'] as $column) {
            if (array_key_exists($column, $row) && is_string($row[$column])) {
                $row[$column] = json_decode($row[$column], true);
            }
        }

        return $row;
    }

    /**
     * @return list<mixed>
     */
    private function records(array $mutations, string $table): array
    {
        $value = $mutations[$table] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    private function normaliseTimestamp(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $timestamp = strtotime($value);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
