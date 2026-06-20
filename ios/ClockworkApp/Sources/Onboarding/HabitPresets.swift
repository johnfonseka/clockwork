import Foundation
import SwiftData
import ScoringEngine

/// Starter habits seeded on first launch so the user never faces a blank UI
/// (spec §4 "Onboarding & Initialization Presets").
enum HabitPresets {
    private static let everyDay = "1,2,3,4,5,6,7"

    /// Seeds the preset habits if the store has no habits yet. Idempotent.
    static func seedIfNeeded(_ context: ModelContext) {
        let existing = (try? context.fetchCount(FetchDescriptor<Habit>())) ?? 0
        guard existing == 0 else { return }

        for habit in foundations() {
            context.insert(habit)
        }
        context.insert(maintenance())

        try? context.save()
    }

    /// "The Foundations Preset": Wake Up and Bedtime, both Base / Strict.
    static func foundations() -> [Habit] {
        [
            Habit(
                name: "Wake Up",
                category: .base,
                strictness: .strict,
                scheduleType: .weekly,
                scheduleValue: everyDay,
                targetStartMinutes: 6 * 60 + 30,   // 06:30
                targetDurationMinutes: 5
            ),
            Habit(
                name: "Bedtime",
                category: .base,
                strictness: .strict,
                scheduleType: .weekly,
                scheduleValue: everyDay,
                targetStartMinutes: 22 * 60 + 30,  // 22:30
                targetDurationMinutes: 30
            ),
        ]
    }

    /// "The Maintenance Preset": a monthly checklist-driven block on the last
    /// Saturday of the month (Base / Flexible, checklist enabled).
    static func maintenance() -> Habit {
        let habit = Habit(
            name: "Deep Maintenance Block",
            category: .base,
            strictness: .flexible,
            scheduleType: .monthlyRelative,
            scheduleValue: "last:saturday",
            targetStartMinutes: 10 * 60,           // 10:00
            targetDurationMinutes: 180,
            hasChecklist: true
        )

        let tasks = [
            "Deep clean bathroom",
            "Sharpen kitchen knives",
            "Scrub stainless steel pots (Baking Soda)",
            "Fertilize balcony plants",
        ]
        for (index, name) in tasks.enumerated() {
            habit.checklistItems.append(
                HabitChecklistItem(taskName: name, sortOrder: index)
            )
        }

        return habit
    }
}
