# Clockwork — Backend

Lightweight, stateless PHP 8.3+ REST API backed by MariaDB, intended to run in a
Docker container on a self-hosted home server. See
[`../clockwork-spec.md`](../clockwork-spec.md) for the full spec.

## Layout

```
backend/
├── public/   # Front controller — the only web-exposed directory
├── src/      # Application code (routing, sync engine, SIWA verification)
├── db/       # schema.sql and future migrations
└── docker/   # Dockerfile(s) and docker-compose config
```

## Responsibilities

- Verify Sign in with Apple identity tokens (JWT) and resolve internal `user_id`.
- Serve `POST /api/sync` — a last-write-wins delta sync over HTTPS.
- Persist `users`, `habits`, `habit_checklists`, `daily_logs`, `habit_entries`.

## Status

Not yet implemented. The relational schema is defined in
[`db/schema.sql`](./db/schema.sql).
