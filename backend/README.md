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

**`POST /api/auth`** accepts the token as `Authorization: Bearer <token>` or a
JSON body `{ "identity_token": "<token>" }`. It verifies the JWT signature
against Apple's public keys and checks `iss` and `aud` (`aud` must equal
`APPLE_CLIENT_ID`). Responses: `200` `{ "user": {...} }`, `400` missing token,
`401` invalid token.

## Phase 1 scope

Done: Docker environment, MariaDB schema, Sign in with Apple token verification,
health check. The `/api/sync` delta-sync endpoint (spec §5) is Phase 4.
