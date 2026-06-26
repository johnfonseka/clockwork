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
 *
 * Pure logic lives in {@see SyncSchema}, {@see SyncValidator},
 * {@see RowNormaliser} and {@see Timestamps}; this class is the DB orchestration.
 */
final class SyncEngine
{
    private const EPOCH = '1970-01-01 00:00:00';

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
        $since = Timestamps::normalise($lastSync) ?? self::EPOCH;

        SyncValidator::validate($mutations);

        $this->db->beginTransaction();
        try {
            $writtenKeys = [];
            foreach (SyncSchema::tableNames() as $table) {
                $records = SyncSchema::records($mutations, $table);
                $writtenKeys[$table] = $this->applyMutations($table, $userId, $records);
            }

            $serverTimestamp = (string) $this->db->query('SELECT UTC_TIMESTAMP()')->fetchColumn();

            $changes = [];
            foreach (SyncSchema::tableNames() as $table) {
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
     * @param list<mixed> $records
     *
     * @return list<array<string,mixed>> the natural-key values of written rows
     */
    private function applyMutations(string $table, int $userId, array $records): array
    {
        if ($records === []) {
            return [];
        }

        $spec = SyncSchema::spec($table);
        $insertColumns = array_values(array_unique([...$spec['columns'], 'user_id']));
        $statement = $this->db->prepare($this->upsertSql($table, $insertColumns));

        $writtenKeys = [];
        foreach ($records as $record) {
            /** @var array<string,mixed> $record */
            $params = [];
            foreach ($insertColumns as $column) {
                $params[$column] = RowNormaliser::bindValue($table, $column, $record, $userId);
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
        $spec = SyncSchema::spec($table);
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

    /**
     * @param list<array<string,mixed>> $excludeKeys
     *
     * @return list<array<string,mixed>>
     */
    private function changesSince(string $table, int $userId, string $since, array $excludeKeys): array
    {
        $spec = SyncSchema::spec($table);
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

        return array_map(static fn (array $row): array => RowNormaliser::output($table, $row), $rows);
    }
}
