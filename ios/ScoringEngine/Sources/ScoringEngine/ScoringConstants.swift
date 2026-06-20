/// Tunable constants for the precision scoring engine.
///
/// Values are taken verbatim from §1 "Dynamic Metric Scoring Rules" of
/// `clockwork-spec.md`, which is the source of truth. If the spec changes,
/// change these and the accompanying tests together.
public enum ScoringConstants {
    /// Strict late-side decay per minute of variance. At ~30 min the score
    /// reaches ~0 (30 × 3.33 = 99.9).
    public static let strictDecayPerMinute = 3.33

    /// Flexible late-side decay per minute of variance. At ~180 min the score
    /// reaches ~0 (180 × 0.55 = 99.0).
    public static let flexibleDecayPerMinute = 0.55

    /// Strict habits may clock in up to this many minutes early and still score
    /// 100%. Variance beyond this is reduced by the grace before decaying.
    public static let strictEarlyGraceMinutes = 40

    /// The instant credit a Show-Up Bonus habit earns simply for being completed.
    public static let showUpBaselineCredit = 50.0

    /// The window over which the remaining Show-Up Bonus credit decays to zero.
    public static let showUpVarianceWindowMinutes = 120.0

    /// Total minutes in a day, used to normalise variance across midnight.
    public static let minutesInDay = 1440
}
