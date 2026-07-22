/// The temporal-precision scoring engine for Clockwork Life.
///
/// All logic mirrors §1 of `clockwork-spec.md`. Scores are on a 0...100 scale.
/// The engine is intentionally pure (no Foundation, no I/O) so it can be unit
/// tested exhaustively and reused unchanged inside the SwiftUI app.
public enum ScoringEngine {

    // MARK: - High-level scoring

    /// Scores a habit entry from its target time, optional actual clock-in time,
    /// and completion flag.
    ///
    /// - Parameters:
    ///   - strictness: The scoring rule for the habit.
    ///   - targetMinutes: Target start time as minutes since midnight (0...1439).
    ///   - actualMinutes: Actual clock-in time as minutes since midnight, or
    ///     `nil` if the habit was never clocked in.
    ///   - completed: Whether the habit was marked complete. Only affects
    ///     Show-Up Bonus habits.
    /// - Returns: A score in the range 0...100.
    public static func score(
        strictness: Strictness,
        targetMinutes: Int,
        actualMinutes: Int?,
        completed: Bool
    ) -> Double {
        guard let actualMinutes else {
            // No clock-in recorded for the day.
            switch strictness {
            case .showUpBonus:
                // Completion alone earns the baseline; no time means no time bonus.
                return completed ? ScoringConstants.showUpBaselineCredit : 0
            case .chained:
                // No parent context here → Parent-Grace baseline (see scoreChained).
                return detachedBaseline(completed: completed)
            case .strict, .flexible:
                return 0
            }
        }

        let variance = signedVariance(
            targetMinutes: targetMinutes,
            actualMinutes: actualMinutes
        )
        return score(
            strictness: strictness,
            varianceMinutes: variance,
            completed: completed
        )
    }

    /// Scores a habit entry from a pre-computed signed variance.
    ///
    /// - Parameters:
    ///   - strictness: The scoring rule for the habit.
    ///   - varianceMinutes: Signed variance in minutes. Negative is early,
    ///     positive is late, zero is exactly on time.
    ///   - completed: Whether the habit was marked complete. Only affects
    ///     Show-Up Bonus habits.
    /// - Returns: A score in the range 0...100.
    public static func score(
        strictness: Strictness,
        varianceMinutes: Int,
        completed: Bool
    ) -> Double {
        let isEarly = varianceMinutes < 0
        let magnitude = Double(abs(varianceMinutes))

        switch strictness {
        case .strict:
            if isEarly {
                if magnitude <= Double(ScoringConstants.strictEarlyGraceMinutes) {
                    return 100
                }
                let beyondGrace = magnitude - Double(ScoringConstants.strictEarlyGraceMinutes)
                return clampToScore(100 - beyondGrace * ScoringConstants.strictDecayPerMinute)
            }
            return clampToScore(100 - magnitude * ScoringConstants.strictDecayPerMinute)

        case .flexible:
            if isEarly { return 100 }
            return clampToScore(100 - magnitude * ScoringConstants.flexibleDecayPerMinute)

        case .showUpBonus:
            // Show-Up Bonus earns nothing without completion.
            guard completed else { return 0 }
            if isEarly { return 100 }
            let window = ScoringConstants.showUpVarianceWindowMinutes
            let timeBonus = ScoringConstants.showUpBaselineCredit
                * max(0, (window - magnitude) / window)
            return ScoringConstants.showUpBaselineCredit + timeBonus

        case .chained:
            // A chain cannot be scored from clock variance alone — it needs the
            // parent's finish time. Fall back to the Parent-Grace baseline; callers
            // with parent context must use `scoreChained(...)` instead.
            return detachedBaseline(completed: completed)
        }
    }

    // MARK: - Chained (CHN) scoring

    /// Scores a Chained (CHN) habit against its parent (anchor) habit, ignoring the
    /// clock entirely. See §1 "Chained Habits" of `clockwork-spec.md`.
    ///
    /// - Parameters:
    ///   - parentFinishMinutes: The parent entry's finish time (actual start +
    ///     actual duration) as minutes since midnight, or `nil` when the parent has
    ///     no entry for the day / is not completed. `nil` triggers the **Parent
    ///     Grace Rule**: the child detaches and falls back to the Show-Up baseline.
    ///   - childStartMinutes: This habit's actual clock-in, or `nil` if never
    ///     clocked in (the gap is then undefined; falls back to the baseline).
    ///   - targetGapMinutes: The desired gap between the parent finishing and this
    ///     habit starting.
    ///   - completed: Completion flag, used only by the grace / no-time fallbacks.
    /// - Returns: A score in the range 0...100.
    public static func scoreChained(
        parentFinishMinutes: Int?,
        childStartMinutes: Int?,
        targetGapMinutes: Int,
        completed: Bool
    ) -> Double {
        // Parent Grace Rule: no usable parent → detach to the Show-Up baseline so a
        // broken chain never unfairly penalises the child.
        guard let parentFinishMinutes else {
            return detachedBaseline(completed: completed)
        }
        // Parent present but the child never clocked in → gap undefined; treat like a
        // completion baseline (mirrors the Show-Up "no time" branch).
        guard let childStartMinutes else {
            return detachedBaseline(completed: completed)
        }

        // Normalise across midnight (e.g. parent finishes 23:50, child starts 00:10).
        let gap = signedVariance(
            targetMinutes: parentFinishMinutes,
            actualMinutes: childStartMinutes
        )
        // On or under the target gap = prompt = full credit (promptness is lenient,
        // matching the early-clock-in asymmetry elsewhere).
        if gap <= targetGapMinutes { return 100 }
        let overshoot = Double(gap - targetGapMinutes)
        return clampToScore(100 - overshoot * ScoringConstants.chainedDecayPerMinute)
    }

    // MARK: - Helpers

    /// Converts a wall-clock time to minutes since midnight.
    public static func minutesSinceMidnight(hour: Int, minute: Int) -> Int {
        hour * 60 + minute
    }

    /// Computes signed variance (actual − target) in minutes, normalised across
    /// midnight so a habit late into the next day (e.g. a 22:30 bedtime logged at
    /// 00:15) reads as a small positive variance rather than a huge negative one.
    /// The result is always in the range −720...720.
    public static func signedVariance(targetMinutes: Int, actualMinutes: Int) -> Int {
        var delta = actualMinutes - targetMinutes
        let half = ScoringConstants.minutesInDay / 2
        if delta > half {
            delta -= ScoringConstants.minutesInDay
        } else if delta < -half {
            delta += ScoringConstants.minutesInDay
        }
        return delta
    }

    // MARK: - Aggregation (Pause Engine)

    /// Averages a set of per-day scores while honouring the Pause Engine: any day
    /// flagged `isPaused` is omitted entirely so holidays and treks do not skew
    /// aggregate metrics (see §1 "The Pause Engine").
    ///
    /// - Returns: The mean of the non-paused scores, or `nil` if there are no
    ///   active (non-paused) days to average.
    public static func aggregate(_ days: [(score: Double, isPaused: Bool)]) -> Double? {
        let active = days.lazy.filter { !$0.isPaused }.map(\.score)
        var total = 0.0
        var count = 0
        for value in active {
            total += value
            count += 1
        }
        guard count > 0 else { return nil }
        return total / Double(count)
    }

    // MARK: - Private

    private static func clampToScore(_ value: Double) -> Double {
        min(100, max(0, value))
    }

    /// The Parent-Grace fallback: a detached chained habit (or one lacking the data
    /// to measure a gap) earns the Show-Up baseline on completion, otherwise 0.
    private static func detachedBaseline(completed: Bool) -> Double {
        completed ? ScoringConstants.showUpBaselineCredit : 0
    }
}
