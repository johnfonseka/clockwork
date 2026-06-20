# Clockwork — iOS Client

Native Swift / SwiftUI app (iOS 17+) using SwiftData for local-first, offline
execution. See [`../clockwork-spec.md`](../clockwork-spec.md) for the full spec.

## Status

Not yet scaffolded. Planned structure:

- **ScoringEngine** — a standalone Swift package implementing the temporal
  precision math (Strict / Flexible / Show-Up Bonus, early vs. late grace, pause
  exclusion). Pure logic, fully unit-tested, no UI dependency.
- **ClockworkApp** — the SwiftUI app: SwiftData models, the 4-ring dashboard,
  checklist views, onboarding presets, and the sync worker.

The Xcode project will be generated reproducibly (e.g. via XcodeGen) so it can be
maintained from source rather than hand-edited `.pbxproj` files.

## Signing & capabilities (manual)

Sign in with Apple and provisioning must be configured in Xcode's
**Signing & Capabilities** panel with your Apple Developer account.
