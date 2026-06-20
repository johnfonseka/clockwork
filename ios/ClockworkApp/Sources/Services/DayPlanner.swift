import Foundation
import SwiftData

/// Materialises the day: ensures a `HabitEntry` exists for every habit scheduled
/// on a given date, so the timeline has rows to display and clock into.
enum DayPlanner {
    /// Creates missing entries for `date`. Idempotent — safe to call on every
    /// appearance.
    static func ensureEntries(for date: Date, context: ModelContext, calendar: Calendar = .current) {
        let day = calendar.startOfDay(for: date)

        let habits = (try? context.fetch(FetchDescriptor<Habit>())) ?? []
        let activeHabits = habits.filter { HabitScheduler.isActive($0, on: day, calendar: calendar) }

        let allEntries = (try? context.fetch(FetchDescriptor<HabitEntry>())) ?? []
        let todaysHabitIDs = Set(
            allEntries
                .filter { calendar.isDate($0.logDate, inSameDayAs: day) }
                .compactMap { $0.habit?.id }
        )

        var created = false
        for habit in activeHabits where !todaysHabitIDs.contains(habit.id) {
            context.insert(HabitEntry(logDate: day, habit: habit))
            created = true
        }

        if created {
            try? context.save()
        }
    }

    /// Fetches or creates today's `DailyLog` (carries the Pause Engine flag).
    static func dailyLog(for date: Date, context: ModelContext, calendar: Calendar = .current) -> DailyLog {
        let day = calendar.startOfDay(for: date)
        let logs = (try? context.fetch(FetchDescriptor<DailyLog>())) ?? []
        if let existing = logs.first(where: { calendar.isDate($0.logDate, inSameDayAs: day) }) {
            return existing
        }
        let log = DailyLog(logDate: day)
        context.insert(log)
        try? context.save()
        return log
    }
}
