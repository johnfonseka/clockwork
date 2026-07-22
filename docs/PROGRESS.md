# Clockwork — Progress & Action Items

> Single-glance tracker for **what's done** and **what's next**. Tick items as they
> land. Keep items at a workable altitude (a half-day-ish chunk each) — not "finish
> Phase 1", not every one-line commit.
>
> Legend: `[x]` done · `[ ]` todo · `[~]` in progress / unverified.
> Authoritative spec: [`../clockwork-spec.md`](../clockwork-spec.md). Context & rationale: [`HANDOFF.md`](./HANDOFF.md).
>
> Last updated: 2026-07-22

---

## Phase 1 — Backend foundations ✅

- [x] Docker environment (PHP 8.3 + Apache, MariaDB) and Git repo
- [x] MariaDB schema (`backend/db/schema.sql`), auto-loaded on first DB init
- [x] Sign in with Apple token verification (signature, `iss`, `aud`)
- [x] `GET /api/health` liveness + DB connectivity check
- [x] Dev auth bypass (`X-Dev-User`) for local development

## Phase 2 — iOS models & onboarding ✅

- [x] SwiftData entities: `Habit`, `HabitChecklistItem`, `DailyLog`, `HabitEntry`
- [x] Enum columns stored as raw strings with typed computed accessors
- [x] Onboarding presets (Foundations, Maintenance) seeded on first launch
- [x] App shell (`ClockworkApp`, `ModelContainer`) — builds for simulator
- [x] `ScoringEngine` Swift package — Strict / Flexible / Show-Up + grace + pause (24 tests)

## Phase 3 — Dashboard & checklist UI ✅

- [x] Terminal-aesthetic chronological daily timeline (`CCW_OS`, ASCII borders)
- [x] 4 concentric category rings (Base / Health / Growth / Spirit) with accent colors
- [x] Per-habit row: target vs. actual, variance, status, `[ RUN_CLOCK_IN ]`
- [x] Sub-task checklist view (Wireframe 2)

## Phase 4 — Sync engine & exclusion math

- [x] Backend `/api/sync` last-write-wins delta engine
- [x] Per-record `updated_at` gating; `user_id` from session, never payload
- [x] `changes` excludes rows written by the same payload; bool/int/JSON normalisation
- [x] Pure sync logic extracted to `src/Sync/` for DB-free unit testing
- [x] Backend unit tests (`composer test`) + ephemeral E2E stack (`composer test:e2e`)
- [x] Pause-day exclusion verified in the scoring/aggregate math
- [ ] **iOS background sync worker** — `URLSession` client-side sync ⚠ *not confirmed built; verify before closing Phase 4*

## Phase 5 — Master-spec reconciliation (decoupling + CHN)

> Reconciles the code with the plain-English master spec. See spec §1 + §6.

**Decouple ring (grouping) from strictness (scoring)** — schema already stores them
separately, so this is UI/preset work, no migration.
- [ ] Habit editor lets the user pick any `strictness_type` per habit, independent of ring
- [ ] Update onboarding presets to express strictness explicitly (not implied by ring)

**Chained (CHN) scoring mode**
- [ ] Additive migration: `'chained'` enum value + `chain_parent_id` + `chain_target_gap_minutes` (soft ref, **no** SQL FK)
- [ ] Extend `SyncSchema` (PHP) to carry the two new columns
- [x] `ScoringEngine`: gap-based score (`100%` at/under target gap, flexible decay beyond)
- [x] `ScoringEngine`: Parent Grace Rule (parent missing/incomplete → child detaches to Show-Up baseline) + `.chained` enum case
- [ ] `Habit` SwiftData model: `chainParentId`, `chainTargetGapMinutes`, `.chained` case
- [ ] Momentum preset (Chained "Morning Workout" anchored to "Wake Up", 15-min gap)
- [x] Tests for gap scoring + Parent Grace (`swift test` — 33 passing)

**Client backlog (from the master spec — lower priority)**
- [ ] Siri voice logging ("mark … done in Clockwork")
- [ ] Retrospective `[-]`/`[+]` 5-minute step editing
- [ ] One-tap Reset-to-Target macro
- [ ] Single-focus interactive home-screen widget

## Infrastructure / Ops

- [ ] Enable test URLs on the server (`test-clockwork`, `test-api-clockwork`)
- [ ] Commit the working-tree `docker-compose.yml` hosting changes (proxy-oriented)
- [ ] **Close the prod auth bypass** — prod `api-clockwork` → `APP_ENV=production`; test tier stays `development` ⚠ *must land before real users*
- [ ] Harden so a misconfigured `APP_ENV` can't fail open
- [ ] Mobile app test/prod API base-URL switch (xcconfig/scheme or in-app dev toggle)
- [ ] GitHub Actions CI (run `composer test`, `composer test:e2e`, `swift test` on push/PR)
- [ ] GitHub Actions CD (build/deploy → test tier first, then promote to prod)

## Docs hygiene

- [ ] `ios/README.md` — Phase 3 was marked ⬜; confirm/refresh status labels
- [ ] `backend/README.md` — stale service name (`api`) and `localhost:8080` references
