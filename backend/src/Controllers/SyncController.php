<?php

declare(strict_types=1);

namespace Clockwork\Controllers;

use Clockwork\Auth\RequestAuthenticator;
use Clockwork\Auth\TokenVerificationException;
use Clockwork\Database;
use Clockwork\Http\Json;
use Clockwork\Sync\SyncEngine;
use Clockwork\Sync\SyncValidationException;

/**
 * POST /api/sync — last-write-wins delta sync (spec §5).
 *
 * Request body:
 *   {
 *     "last_sync_timestamp": "2026-06-20 08:00:00" | null,
 *     "mutations": { "habits": [], "daily_logs": [], "habit_entries": [] }
 *   }
 *
 * Response: { "server_timestamp": "...", "changes": { ...per table... } }
 */
final class SyncController
{
    public function sync(): void
    {
        $db = Database::connection();

        try {
            $userId = (new RequestAuthenticator($db))->resolveUserId();
        } catch (TokenVerificationException $e) {
            Json::error($e->getMessage(), 401);

            return;
        }

        $body = Json::body();
        $lastSync = $body['last_sync_timestamp'] ?? null;
        $mutations = $body['mutations'] ?? [];
        if (!is_array($mutations)) {
            Json::error("'mutations' must be an object", 422);

            return;
        }

        try {
            $result = (new SyncEngine($db))->sync(
                $userId,
                is_string($lastSync) ? $lastSync : null,
                $mutations
            );
        } catch (SyncValidationException $e) {
            Json::error($e->getMessage(), 422);

            return;
        }

        Json::send($result);
    }
}
