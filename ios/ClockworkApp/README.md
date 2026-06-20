# Clockwork — iOS App

The SwiftUI app (iOS 17+) using SwiftData for local-first storage. Depends on the
local [`ScoringEngine`](../ScoringEngine) package for all precision math.

## Project generation

The `.xcodeproj` is **generated** from [`project.yml`](./project.yml) with
[XcodeGen](https://github.com/yonaskolb/XcodeGen) and is **not** committed.
`project.yml` is the source of truth.

```sh
brew install xcodegen          # one-time
cd ios/ClockworkApp
xcodegen generate              # creates Clockwork.xcodeproj
open Clockwork.xcodeproj       # or just build from the CLI below
```

Re-run `xcodegen generate` after adding/removing source files or editing
`project.yml`.

## Build & run

```sh
xcodebuild build \
  -project Clockwork.xcodeproj -scheme Clockwork \
  -sdk iphonesimulator -destination 'platform=iOS Simulator,name=iPhone 16' \
  CODE_SIGNING_ALLOWED=NO
```

Or press Run ▶ in Xcode against a simulator.

## What's here (Phase 2)

- **`Sources/Models`** — SwiftData entities mirroring the schema: `Habit`,
  `HabitChecklistItem`, `DailyLog`, `HabitEntry`. Enum columns are stored as raw
  strings (matching the DB ENUMs) with typed computed accessors.
- **`Sources/Onboarding/HabitPresets.swift`** — seeds the Foundations and
  Maintenance presets on first launch (spec §4).
- **`Sources/App/ClockworkApp.swift`** — app entry; configures the
  `ModelContainer` and seeds presets.
- **`Sources/Views/ContentView.swift`** — Phase 2 shell listing seeded habits.
  `HabitEntry.score` demonstrates the ScoringEngine integration.

The full terminal-aesthetic 4-ring dashboard is Phase 3.

> Signing & Sign in with Apple are configured manually in Xcode's
> **Signing & Capabilities** with your Apple Developer account.
