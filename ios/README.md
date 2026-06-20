# Clockwork — iOS Client

Native Swift / SwiftUI app (iOS 17+) using SwiftData for local-first, offline
execution. See [`../clockwork-spec.md`](../clockwork-spec.md) for the full spec.

## Structure

- **[ScoringEngine](./ScoringEngine)** — standalone Swift package implementing the
  temporal precision math (Strict / Flexible / Show-Up Bonus, early vs. late
  grace, pause exclusion). Pure logic, fully unit-tested, no UI dependency. ✅
- **[ClockworkApp](./ClockworkApp)** — the SwiftUI app: SwiftData models,
  onboarding presets, and (later) the 4-ring dashboard and sync worker. The
  Xcode project is generated from `project.yml` via XcodeGen.

## Status

- ✅ ScoringEngine (24 tests passing)
- ✅ Phase 2: SwiftData models + onboarding presets + app shell (builds for simulator)
- ⬜ Phase 3: the 4-ring dashboard + checklist UI

## Signing & capabilities (manual)

Sign in with Apple and provisioning must be configured in Xcode's
**Signing & Capabilities** panel with your Apple Developer account.
