<?php

declare(strict_types=1);

namespace Clockwork\Sync;

final class SyncValidator
{
    /**
     * Ensures every record carries the columns required to insert a new row.
     *
     * @param array<string,mixed> $mutations
     *
     * @throws SyncValidationException listing all problems found
     */
    public static function validate(array $mutations): void
    {
        $errors = [];

        foreach (SyncSchema::TABLES as $table => $spec) {
            foreach (SyncSchema::records($mutations, $table) as $index => $record) {
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
}
