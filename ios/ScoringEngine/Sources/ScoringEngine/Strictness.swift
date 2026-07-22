/// The scoring rule applied to a habit, mirroring the `strictness_type` column
/// in the database schema and §1 of `clockwork-spec.md`.
///
/// The scoring rule is chosen per habit and is **independent of the habit's ring
/// (category)** — any habit in any ring may use any of these.
public enum Strictness: String, Codable, Sendable, CaseIterable {
    /// Tight tolerance; decays quickly when late, 40-min early grace.
    case strict
    /// Broad tolerance; any early clock-in scores 100%.
    case flexible
    /// Requires completion to score; 50% baseline plus a time bonus.
    case showUpBonus = "show_up_bonus"
    /// Contextual habit stacking: scored on the gap to a parent (anchor) habit,
    /// ignoring the clock. Real scoring requires parent context, so use
    /// `ScoringEngine.scoreChained(...)`; the clock-based `score(...)` overloads
    /// fall back to the Parent-Grace baseline for this case.
    case chained
}
