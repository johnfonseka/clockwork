import Foundation

/// The four concentric rings (spec §1). Stored as the `category` ENUM in the
/// database schema.
enum HabitCategory: String, Codable, CaseIterable, Sendable {
    case base
    case health
    case growth
    case spirit

    /// Short uppercase label used in the terminal UI (e.g. `[BASE]`).
    var ringLabel: String {
        switch self {
        case .base: "BASE"
        case .health: "HLTH"
        case .growth: "GRW"
        case .spirit: "SPRT"
        }
    }

    var displayName: String {
        switch self {
        case .base: "Base — The Core"
        case .health: "Health — The Body"
        case .growth: "Growth — The Mind"
        case .spirit: "Spirit — The Soul"
        }
    }
}

/// How a habit recurs. Mirrors the `schedule_type` ENUM.
enum ScheduleType: String, Codable, Sendable {
    case weekly
    case monthlyRelative = "monthly_relative"
    case monthlyAbsolute = "monthly_absolute"
}

/// Why a day is paused and excluded from metrics (spec "Pause Engine"). Mirrors
/// the `pause_reason` ENUM.
enum PauseReason: String, Codable, Sendable {
    case hike = "Hike"
    case trek = "Trek"
    case holiday = "Holiday"
    case other = "Other"
}
