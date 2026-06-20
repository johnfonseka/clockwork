import Foundation
import ScoringEngine

enum TimeFormat {
    /// Minutes-since-midnight as `HH:mm`.
    static func clock(_ minutes: Int) -> String {
        String(format: "%02d:%02d", minutes / 60, minutes % 60)
    }

    /// Signed variance as `+02m` / `-15m`.
    static func variance(_ minutes: Int) -> String {
        let sign = minutes >= 0 ? "+" : "-"
        return "\(sign)\(String(format: "%02d", abs(minutes)))m"
    }

    static func dateStamp(_ date: Date, _ calendar: Calendar = .current) -> String {
        let c = calendar.dateComponents([.year, .month, .day], from: date)
        return String(format: "%04d.%02d.%02d", c.year ?? 0, c.month ?? 0, c.day ?? 0)
    }
}

extension Date {
    func minutesSinceMidnight(_ calendar: Calendar = .current) -> Int {
        let c = calendar.dateComponents([.hour, .minute], from: self)
        return (c.hour ?? 0) * 60 + (c.minute ?? 0)
    }
}

extension HabitEntry {
    /// Signed start-time variance in minutes, or `nil` if not yet clocked in.
    var variance: Int? {
        guard let actual = actualStartMinutes, let habit else { return nil }
        return ScoringEngine.signedVariance(
            targetMinutes: habit.targetStartMinutes,
            actualMinutes: actual
        )
    }

    var statusMarker: String { completed ? "[X]" : "[ ]" }
}
