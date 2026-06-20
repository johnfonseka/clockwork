import Foundation

/// Decides whether a habit is active on a given calendar date based on its
/// schedule (spec §3 `schedule_type` / `schedule_value`).
///
/// Schedule value formats:
/// - `weekly`: comma-separated ISO weekdays, `1`=Mon … `7`=Sun (e.g. `1,2,3,4,5`).
/// - `monthly_absolute`: day of month (e.g. `15`).
/// - `monthly_relative`: `<ordinal>:<weekday>` where ordinal is `first`…`fifth`
///   or `last` (e.g. `last:saturday`).
enum HabitScheduler {
    static func isActive(_ habit: Habit, on date: Date, calendar: Calendar = .current) -> Bool {
        guard habit.isActive else { return false }

        switch habit.scheduleType {
        case .weekly:
            let days = habit.scheduleValue
                .split(separator: ",")
                .compactMap { Int($0.trimmingCharacters(in: .whitespaces)) }
            return days.contains(isoWeekday(of: date, calendar))

        case .monthlyAbsolute:
            return Int(habit.scheduleValue) == calendar.component(.day, from: date)

        case .monthlyRelative:
            return matchesRelative(habit.scheduleValue, on: date, calendar: calendar)
        }
    }

    /// ISO weekday: 1=Monday … 7=Sunday.
    static func isoWeekday(of date: Date, _ calendar: Calendar) -> Int {
        let weekday = calendar.component(.weekday, from: date) // 1=Sun … 7=Sat
        return ((weekday + 5) % 7) + 1
    }

    private static func matchesRelative(_ value: String, on date: Date, calendar: Calendar) -> Bool {
        let parts = value.lowercased().split(separator: ":")
        guard parts.count == 2,
              let targetWeekday = calendarWeekday(String(parts[1])) else {
            return false
        }
        guard calendar.component(.weekday, from: date) == targetWeekday else {
            return false
        }

        let ordinal = String(parts[0])
        if ordinal == "last" {
            guard let nextWeek = calendar.date(byAdding: .day, value: 7, to: date) else {
                return false
            }
            return calendar.component(.month, from: nextWeek) != calendar.component(.month, from: date)
        }

        guard let n = ordinalNumber(ordinal) else { return false }
        let nthOccurrence = (calendar.component(.day, from: date) - 1) / 7 + 1
        return nthOccurrence == n
    }

    /// Calendar weekday: 1=Sunday … 7=Saturday.
    private static func calendarWeekday(_ name: String) -> Int? {
        switch name {
        case "sunday": 1
        case "monday": 2
        case "tuesday": 3
        case "wednesday": 4
        case "thursday": 5
        case "friday": 6
        case "saturday": 7
        default: nil
        }
    }

    private static func ordinalNumber(_ ordinal: String) -> Int? {
        switch ordinal {
        case "first", "1": 1
        case "second", "2": 2
        case "third", "3": 3
        case "fourth", "4": 4
        case "fifth", "5": 5
        default: nil
        }
    }
}
