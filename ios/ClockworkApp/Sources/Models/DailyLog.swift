import Foundation
import SwiftData

/// Per-day metadata, primarily the Pause Engine flag. Mirrors the `daily_logs`
/// table. Keyed locally by `logDate` (one log per calendar day).
@Model
final class DailyLog {
    @Attribute(.unique) var id: String
    @Attribute(.unique) var logDate: Date
    var isPaused: Bool
    var pauseReasonRaw: String?
    var updatedAt: Date

    init(
        id: String = UUID().uuidString,
        logDate: Date,
        isPaused: Bool = false,
        pauseReason: PauseReason? = nil,
        updatedAt: Date = .now
    ) {
        self.id = id
        self.logDate = logDate
        self.isPaused = isPaused
        self.pauseReasonRaw = pauseReason?.rawValue
        self.updatedAt = updatedAt
    }

    var pauseReason: PauseReason? {
        get { pauseReasonRaw.flatMap(PauseReason.init(rawValue:)) }
        set { pauseReasonRaw = newValue?.rawValue }
    }
}
