# Clockwork — iOS Client

Native Swift / SwiftUI app (iOS 17+) using SwiftData for local-first, offline
execution. See [`../clockwork-spec.md`](../clockwork-spec.md) for the full spec.

## Structure

- **[ScoringEngine](./ScoringEngine)** — standalone Swift package implementing the
  temporal precision math (Strict / Flexible / Show-Up Bonus, early vs. late
  grace, pause exclusion). Pure logic, fully unit-tested, no UI dependency. ✅
  The **Chained (CHN)** mode and Parent Grace Rule are specified but **not yet
  implemented** (Phase 5 — see `../clockwork-spec.md` §1).
- **[ClockworkApp](./ClockworkApp)** — the SwiftUI app: SwiftData models,
  onboarding presets, and (later) the 4-ring dashboard and sync worker. The
  Xcode project is generated from `project.yml` via XcodeGen.

> The four rings are **visual categories** (grouping + color) — a habit's scoring
> mode is independent of its ring, so the habit editor lets the user pick Strict /
> Flexible / Show-Up Bonus / Chained for any habit.

## Status

- ✅ ScoringEngine (24 tests passing)
- ✅ Phase 2: SwiftData models + onboarding presets + app shell (builds for simulator)
- ⬜ Phase 3: the 4-ring dashboard + checklist UI
- ⬜ Phase 5: ring/strictness decoupling in the habit editor + Chained (CHN) scoring mode

## Signing & capabilities (manual)

Sign in with Apple and provisioning must be configured in Xcode's
**Signing & Capabilities** panel with your Apple Developer account.

## TODO: API environment switch

The app needs a way to switch its API base URL between the **test** and **prod**
backend tiers without editing code:

- Test: `https://test-api-clockwork.shan4max.com`
- Prod: `https://api-clockwork.shan4max.com`

Pick a mechanism — e.g. a build configuration / scheme with an `.xcconfig` value,
or an in-app developer toggle — and wire the sync worker to read from it.
