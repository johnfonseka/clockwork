# Clockwork Life — Session Handoff

> Living document to resume work with full context. Covers **scope**, **current
> state**, **infrastructure**, **open items**, and **what's planned next**.
> Update it as things change. `clockwork-spec.md` remains the authoritative spec;
> this file tracks *where we are* against it. For the tick-as-you-go action-item
> checklist, see [`PROGRESS.md`](./PROGRESS.md).
>
> Last updated: 2026-07-22

---

## 1. What the project is

A minimalist, **local-first iOS habit tracker** that scores alignment with a
structured daily routine by **temporal precision** (how close to your target time
you act) rather than binary streaks. Habits live in **4 concentric rings**, in a
strict monospaced terminal aesthetic (`CCW_OS`, ASCII borders, `[ RUN_CLOCK_IN ]`).

The **4 rings** — Base / Health / Growth / Spirit — are **visual categories**
(grouping + accent color) and do **not** dictate scoring. Each habit's scoring mode
(*strictness type*) is chosen independently:

- **Strict** — tight decay (~30-min window). Early grace = 40 min flat.
- **Show-Up Bonus** — needs `completed`; 50% baseline + 50% over a 120-min window.
- **Flexible** — broad (~180-min window); any early clock-in = 100%.
- **Chained (CHN)** — *specified, not yet built.* Scored on the gap to a parent
  anchor habit, ignoring the clock; **Parent Grace Rule** detaches a child (to a
  Show-Up baseline) if the parent is missed. See `clockwork-spec.md` §1 + Phase 5.
- **Pause Engine** — `is_paused` days are fully excluded from trends/aggregates.

Scoring rules are the source of truth in `clockwork-spec.md` §1. The Swift
`ScoringEngine` mirrors the first three modes exactly; the Chained mode and the
ring/strictness decoupling are **planned (Phase 5)**, not yet in code.

## 2. Architecture

| Part | Stack | Location |
|------|-------|----------|
| **Client** | Swift / SwiftUI (iOS 17+), SwiftData (offline-first) | `ios/` |
| **Backend** | PHP 8.3 (stateless REST) + MariaDB, Docker | `backend/` |

- Auth: **Sign in with Apple** — client sends a verified JWT; PHP checks signature,
  `iss`, and `aud` (`aud` must equal `APPLE_CLIENT_ID`).
- Sync: **last-write-wins delta** via `POST /api/sync` (spec §5).
- The **native iOS app is the real client**; the marketing SPA is separate (see §5).

## 3. Current state (by phase)

Roadmap has 4 phases; **all four are implemented in the repo.**

- ✅ **Phase 1** — Docker, MariaDB schema, SIWA token verification, `/api/health`.
- ✅ **Phase 2** — SwiftData models (`Habit`, `HabitChecklistItem`, `DailyLog`,
  `HabitEntry`), onboarding presets, app shell. Builds for simulator.
- ✅ **Phase 3** — terminal-aesthetic 4-ring dashboard + checklist view
  (commit `ef41234`). *Note: `ios/README.md` still lists Phase 3 as ⬜ — stale.*
- ✅ **Phase 4** — backend `/api/sync` LWW engine complete & verified. iOS
  background sync worker: **not yet confirmed done** — verify before calling closed.

### Backend detail
- Endpoints: `GET /api/health`, `POST /api/auth`, `POST /api/sync`.
- Sync: `user_id` from session (never payload); per-record `updated_at` gates LWW;
  `changes` excludes rows written by the same payload; booleans/ints normalised,
  `checklist_state` returned as decoded JSON. `422` invalid, `401` unauth.
- `habit_checklists` is **not** synced (checklist defs travel with the habit).
- Pure sync logic extracted into `src/Sync/` (`SyncValidator`, `Timestamps`,
  `RowNormaliser`) for DB-free unit testing.

### iOS detail
- `ios/ScoringEngine` — standalone, dependency-free Swift package, **24 tests
  passing**. Drops unchanged into the app.
- `ios/ClockworkApp` — `.xcodeproj` is **generated from `project.yml` via XcodeGen**
  and **not committed**. Run `xcodegen generate` after adding/removing files.

## 4. Testing

- **Unit (fast, no DB):** `composer test` → `tests/Unit` (sync helpers + SIWA
  verifier with a locally generated RSA key, no network).
- **E2E (Docker):** `composer test:e2e` → `scripts/run-e2e.sh` spins up an
  **ephemeral stack** from `docker-compose.test.yml`: dedicated `clockwork_test` DB
  **on tmpfs** (fresh schema each run) + test API on **:8081**, runs the suite, then
  `docker compose down -v`. Dev stack (`:8080` / `clockwork`) is never touched. Tests
  auth via `X-Dev-User` bypass (no Apple token). **Preference: always a dedicated,
  ephemeral test DB — never per-test users in a shared/dev/prod DB.**
- **Swift:** `cd ios/ScoringEngine && swift test`.
- CI (GitHub Actions) not wired yet — see §7.

## 5. Infrastructure / deployment

Self-hosted on a home server behind a shared **nginx reverse proxy** on an
**external Docker network named `web-proxy-network`**. TLS via **LetsEncrypt**.

**4 hostnames (flat dash scheme — deliberately, to avoid extra DNS/cert config):**

| Host | Role | Env tier |
|------|------|----------|
| `clockwork.shan4max.com` | SPA (describes app functionality) | prod |
| `api-clockwork.shan4max.com` | REST API | prod |
| `test-clockwork.shan4max.com` | SPA | test/staging |
| `test-api-clockwork.shan4max.com` | REST API | test/staging |

- SPA and API are split by **hostname** (not path) → clean TLS/CORS/caching
  boundary, no nginx `location` gymnastics.
- **CORS only matters if the SPA calls the API.** The native iOS app has no CORS
  concept. If the SPA is purely informational, no CORS headers are needed.

### docker-compose changes for hosting (uncommitted, in working tree)
`backend/docker-compose.yml` was modified to run behind the proxy:
- Renamed service `api` → **`clockwork-api`**, added `container_name` for both
  services (proxy routes to `clockwork-api:80` by name).
- **Removed host port publishes** for db and api (DB never exposed to host/proxy;
  API reached only via the proxy network).
- Added networks: **`internal`** (external: false) for db↔api, and
  **`web-proxy-network`** (external: true) so nginx can see the API.
- Consequence: `API_PORT` / the DB host-port in `.env` are now **unused by this
  file**; `DB_PORT: 3306` env is still used internally by `Database.php`.
- Consequence: no direct `localhost:8080` API access in local dev anymore — would
  need a `docker-compose.override.yml` re-publishing `${API_PORT}:80` if wanted.
- `backend/README.md` lines ~22 and ~34 are now **stale** (mention service `api`
  and `localhost:8080`).

## 6. Open items / known issues

1. **⚠️ Prod auth bypass (deferred, must close before real users).** The
   `X-Dev-User` header bypass is active whenever `APP_ENV=development` — it lets
   anyone authenticate as any user with no Apple token. The new 4-URL topology gives
   this a clean resolution: set **`api-clockwork` (prod) → `APP_ENV=production`**
   (bypass off) while **`test-api-clockwork` stays `APP_ENV=development`** (bypass on,
   legitimate for dev). A prior `force-recreate` on the server reportedly did **not**
   take effect — diagnose (wrong cwd / container not replaced) when doing this.
   Consider hardening so a misconfigured `APP_ENV` can't fail open.
2. **Uncommitted `docker-compose.yml`** hosting changes (see §5) — not yet committed.
3. **Stale docs:** `ios/README.md` (Phase 3 marked ⬜) and `backend/README.md`
   (service name / `localhost:8080`).
4. **iOS sync worker** — confirm Phase 4 client side is actually implemented/tested.
5. **Mobile app needs a test/prod API switch.** The app must be able to target the
   test API (`test-api-clockwork.shan4max.com`) vs prod (`api-clockwork.shan4max.com`)
   — the API base URL is not yet configurable. Decide the mechanism (e.g. build
   config / scheme / xcconfig, or an in-app developer toggle) so builds can point at
   the right tier without code edits.

## 7. What's planned next (in order)

1. **Enable the test URLs on the server** (`test-clockwork` + `test-api-clockwork`)
   — user is doing this now.
2. **GitHub Actions CI/CD.** Current deploy is **manual file upload to the server**,
   which is not ideal. Goals:
   - CI: run `composer test` (unit) + `composer test:e2e` (ephemeral Docker stack) +
     `swift test` on push/PR.
   - CD: automated deploy to the server (build image / pull repo / recreate
     containers) — likely deploy to **test tier first**, then promote to prod.
   - Fold in the **close-the-bypass** step: prod deploy sets `APP_ENV=production`.
3. **Close the prod auth bypass** (item 6.1) — natural to land with the CD pipeline.
4. **Phase 5 — Master-spec reconciliation** (new, agreed 2026-07-22). Reconciles the
   codebase with the plain-English master spec
   (`clockwork_plain-english_product_specification.pdf`). Two architecture changes:
   - **Decouple ring from strictness** — `category` is grouping only; the habit editor
     picks any `strictness_type` per habit. The schema already stores them separately,
     so this is a UI/preset change, no migration.
   - **Add the Chained (CHN) scoring mode** — additive migration for `chain_parent_id`
     + `chain_target_gap_minutes` and the `'chained'` enum value; extend `SyncSchema`;
     implement gap scoring + Parent Grace in `ScoringEngine`. `chain_parent_id` is a
     **soft reference** (no SQL FK) to keep sync batches order-independent.
   - **Client backlog** (also from the master spec): Siri voice logging, retrospective
     `[-]`/`[+]` 5-min editing, Reset-to-Target macro, single-focus home-screen widget.

## 8. Handy commands

```sh
# Backend
cd backend
composer test           # unit (no DB)
composer test:e2e       # ephemeral e2e stack (Docker)
docker compose up --build   # dev stack (now proxy-oriented; see §5)

# iOS
cd ios/ScoringEngine && swift test
cd ios/ClockworkApp && xcodegen generate   # regen .xcodeproj after file changes
```

## 9. Related memory

- `prod-auth-bypass-open` — the deferred auth bypass (item 6.1).
- `testing-prefers-dedicated-test-db` — the ephemeral test-DB preference (§4).
