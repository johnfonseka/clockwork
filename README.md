# Clockwork Life

A minimalist, local-first iOS habit tracker that scores your alignment with a
structured daily routine using **temporal precision** (how close to your target
time you act) rather than binary streaks.

See [`clockwork-spec.md`](./clockwork-spec.md) for the authoritative project
specification — it is the source of truth for the data model, scoring engine, and
UI design.

## Architecture

Clockwork is two cooperating parts:

| Part | Stack | Location |
|------|-------|----------|
| **Client** | Swift / SwiftUI (iOS 17+), SwiftData (local-first, offline) | [`ios/`](./ios/) |
| **Backend** | PHP 8.3+ (stateless REST API) + MariaDB, in Docker | [`backend/`](./backend/) |

Authentication is **Sign in with Apple**; the client passes a verified JWT to the
PHP layer. Data syncs via a last-write-wins delta protocol (`POST /api/sync`).

## Repository layout

```
clockwork/
├── clockwork-spec.md     # Source of truth: spec, scoring engine, schema, UI
├── ios/                  # SwiftUI app + scoring engine Swift package
├── backend/              # PHP REST API, MariaDB schema, Docker setup
│   ├── public/           # Front controller / API entry point
│   ├── src/              # Application code
│   ├── db/               # schema.sql and migrations
│   └── docker/           # Dockerfiles / compose config
└── docs/                 # Additional design notes (as the project grows)
```

## Roadmap

1. **Foundations** — Docker, Git, PHP API, MariaDB tables, Sign in with Apple token check.
2. **Models & Onboarding** — SwiftData entities + starter-habit presets.
3. **Dashboard** — 4-ring SwiftUI dashboard + checklist component.
4. **Sync & Math** — background delta sync + pause-day exclusion in metrics.
