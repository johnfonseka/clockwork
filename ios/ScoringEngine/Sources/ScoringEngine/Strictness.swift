/// The scoring rule applied to a habit, mirroring the `strictness_type` column
/// in the database schema and §1 of `clockwork-spec.md`.
public enum Strictness: String, Codable, Sendable, CaseIterable {
    /// Base ring. Tight tolerance; decays quickly when late, 40-min early grace.
    case strict
    /// Growth & Spirit rings. Broad tolerance; any early clock-in scores 100%.
    case flexible
    /// Health ring. Requires completion to score; 50% baseline plus a time bonus.
    case showUpBonus = "show_up_bonus"
}
