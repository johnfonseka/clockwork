# Clockwork — Backend

Lightweight, stateless PHP 8.3+ REST API backed by MariaDB, running in Docker.
See [`../clockwork-spec.md`](../clockwork-spec.md) for the full spec.

## Layout

```
backend/
├── public/index.php          # Front controller (only web-exposed file)
├── src/
│   ├── Config.php            # Environment access
│   ├── Database.php          # Lazy PDO connection
│   ├── UserRepository.php    # users table upsert
│   ├── Http/                 # Router + JSON request/response helpers
│   ├── Auth/                 # Sign in with Apple token verification
│   └── Controllers/          # Health + Auth endpoints
├── db/schema.sql             # MariaDB schema (auto-loaded on first DB init)
├── docker/
│   ├── php/Dockerfile        # PHP 8.3 + Apache + pdo_mysql + composer
│   └── apache/000-default.conf
├── docker-compose.yml        # api + db services
└── composer.json
```

## Quick start

```sh
cd backend
cp .env.example .env          # then edit secrets + APPLE_CLIENT_ID
docker compose up --build
```

- API: <http://localhost:8080> (override with `API_PORT`)
- The schema in `db/schema.sql` is loaded automatically the first time the
  MariaDB data volume is created. To reload it after schema changes during
  development: `docker compose down -v && docker compose up --build`.

### Running without Docker (lint / route smoke test)

```sh
composer install
composer serve                # php -S 0.0.0.0:8080 -t public
```

(The database will report `error` until a MariaDB instance is reachable.)

## Endpoints

| Method | Path          | Description |
|--------|---------------|-------------|
| `GET`  | `/api/health` | Liveness + DB connectivity. `200` if DB reachable, else `503`. |
| `POST` | `/api/auth`   | Verify a Sign in with Apple identity token, upsert the user, return the record. |
| `POST` | `/api/sync`   | Last-write-wins delta sync (spec §5). |

### Authentication

Both `/api/auth` and `/api/sync` authenticate the caller. `/api/auth` accepts the
token as `Authorization: Bearer <token>` or a JSON body
`{ "identity_token": "<token>" }`; it verifies the JWT signature against Apple's
public keys and checks `iss` and `aud` (`aud` must equal `APPLE_CLIENT_ID`).

**Dev bypass:** when `APP_ENV=development`, requests may send
`X-Dev-User: <any-id>` instead of a real Apple token. The id is treated as an
`apple_user_id` (created on first use), so the API can be exercised — and the app
developed — without a live Apple token. This header is ignored in production.

### `POST /api/sync`

```jsonc
// request
{
  "last_sync_timestamp": "2026-06-26 08:00:00",   // or null on first sync
  "mutations": {
    "habits":        [ /* full records, client UUID `id` */ ],
    "daily_logs":    [ /* keyed by (user_id, log_date) */ ],
    "habit_entries": [ /* full records, client UUID `id` */ ]
  }
}
// response
{
  "server_timestamp": "2026-06-26 12:58:51",       // use as next last_sync_timestamp
  "changes": { "habits": [...], "daily_logs": [...], "habit_entries": [...] }
}
```

- `user_id` is taken from the authenticated session, never the payload.
- Each record must carry an `updated_at`; a mutation is applied only when its
  `updated_at` is `>=` the stored row's (last-write-wins).
- `changes` returns rows updated after `last_sync_timestamp`, excluding ones
  written by this same payload. Booleans/ints are normalised and
  `checklist_state` is returned as decoded JSON. Responses: `200`, `401`
  unauthenticated, `422` invalid payload.
- `habit_checklists` is not synced (the checklist definition travels with its
  habit). `users`/`daily_logs` use server `INT` ids; `habits`/`habit_entries`
  use client-generated UUIDs.

## Status

Backend complete end-to-end: Docker environment, MariaDB schema, Sign in with
Apple verification, health check, and the `/api/sync` delta-sync engine — all
verified against a live MariaDB (LWW conflicts, per-user isolation, validation).
