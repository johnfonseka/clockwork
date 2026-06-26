<?php

declare(strict_types=1);

namespace Clockwork\Sync;

/**
 * Converts between client JSON records and database row values for sync.
 */
final class RowNormaliser
{
    /**
     * Prepares the value to bind for one insert column, applying defaults,
     * the injected `user_id`, a server `updated_at` fallback, JSON encoding,
     * and boolean coercion.
     *
     * @param array<string,mixed> $record
     */
    public static function bindValue(string $table, string $column, array $record, int $userId): mixed
    {
        if ($column === 'user_id') {
            return $userId;
        }
        if ($column === 'updated_at') {
            $value = $record['updated_at'] ?? null;
            return Timestamps::normalise(is_string($value) ? $value : null) ?? gmdate('Y-m-d H:i:s');
        }

        $spec = SyncSchema::spec($table);
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
     * Normalises a raw database row for the JSON response: server ids and
     * `user_id` to int, tinyints to bool, and JSON columns decoded.
     *
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    public static function output(string $table, array $row): array
    {
        $spec = SyncSchema::spec($table);

        // habits/habit_entries use client UUID ids; only server INT ids are cast.
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
        foreach ($spec['ints'] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
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
}
