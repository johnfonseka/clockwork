# ScoringEngine

A standalone, dependency-free Swift package implementing Clockwork Life's
temporal-precision scoring math. It is pure logic (no Foundation, no I/O), so it
is exhaustively unit-tested and drops unchanged into the SwiftUI app.

All rules mirror §1 "Dynamic Metric Scoring Rules" of
[`../../clockwork-spec.md`](../../clockwork-spec.md), the source of truth.

## API

```swift
import ScoringEngine

// From a target time, an optional actual clock-in, and a completion flag:
let score = ScoringEngine.score(
    strictness: .strict,
    targetMinutes: ScoringEngine.minutesSinceMidnight(hour: 6, minute: 30),
    actualMinutes: ScoringEngine.minutesSinceMidnight(hour: 6, minute: 32),
    completed: true
) // -> 0...100

// Or from a pre-computed signed variance (negative = early, positive = late):
ScoringEngine.score(strictness: .flexible, varianceMinutes: -30, completed: false) // 100

// Aggregate per-day scores, excluding paused days (the Pause Engine):
ScoringEngine.aggregate([(100, false), (0, true), (50, false)]) // 75
```

## Rules summary

| Type | Early | On time | Late |
|------|-------|---------|------|
| **Strict** | 100% within 40 min; then `100 − (Δ−40)·3.33` | 100 | `100 − Δ·3.33` (≈0 at 30 min) |
| **Flexible** | always 100% | 100 | `100 − Δ·0.55` (≈0 at 180 min) |
| **Show-Up Bonus** | 100% (if completed) | 100 (if completed) | `50 + 50·(120−Δ)/120` (if completed); 0 if not |

A missing clock-in scores 0 for Strict/Flexible; a completed Show-Up Bonus with no
recorded time earns the 50% baseline. Variance is normalised across midnight.

## Running tests

```sh
cd ios/ScoringEngine
swift test
```
