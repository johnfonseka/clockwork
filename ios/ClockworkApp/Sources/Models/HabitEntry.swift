import Foundation
import SwiftData
import ScoringEngine

/// A single day's record for a habit: when it was actually done, whether it was
/// completed, and per-task checklist state. Mirrors the `habit_entries` table.
@Model
final class HabitEntry {
    @Attribute(.unique) var id: String
    var logDate: Date
    var habit: Habit?
    /// Actual start time as minutes since midnight, or `nil` if never clocked in.
    var actualStartMinutes: Int?
    var actualDurationMinutes: Int?
    var completed: Bool
    /// Per-checklist-item completion, keyed by `HabitChecklistItem.id`
    /// (maps to the DB `checklist_state` JSON column).
    var checklistState: [String: Bool]
    var externalSource: String?
    var externalId: String?
    var updatedAt: Date

    init(
        id: String = UUID().uuidString,
        logDate: Date,
        habit: Habit? = nil,
        actualStartMinutes: Int? = nil,
        actualDurationMinutes: Int? = nil,
        completed: Bool = false,
        checklistState: [String: Bool] = [:],
        externalSource: String? = nil,
        externalId: String? = nil,
        updatedAt: Date = .now
    ) {
        self.id = id
        self.logDate = logDate
        self.habit = habit
        self.actualStartMinutes = actualStartMinutes
        self.actualDurationMinutes = actualDurationMinutes
        self.completed = completed
        self.checklistState = checklistState
        self.externalSource = externalSource
        self.externalId = externalId
        self.updatedAt = updatedAt
    }

    /// The precision score for this entry, computed via the shared ScoringEngine.
    /// Returns 0 if the entry is orphaned from its habit.
    var score: Double {
        guard let habit else { return 0 }
        if habit.strictness == .chained {
            return ScoringEngine.scoreChained(
                parentFinishMinutes: parentFinishMinutes(for: habit),
                childStartMinutes: actualStartMinutes,
                targetGapMinutes: habit.chainTargetGapMinutes ?? 0,
                completed: completed
            )
        }
        return ScoringEngine.score(
            strictness: habit.strictness,
            targetMinutes: habit.targetStartMinutes,
            actualMinutes: actualStartMinutes,
            completed: completed
        )
    }

    /// Resolves the finish time (start + duration, minutes since midnight) of the
    /// parent habit's entry for this same day, or `nil` if the chain has no parent,
    /// the parent has no entry today, or the parent is not completed — each of
    /// which triggers the Parent Grace Rule (spec §1).
    private func parentFinishMinutes(for habit: Habit) -> Int? {
        guard
            let parentId = habit.chainParentId,
            let context = modelContext
        else { return nil }

        let calendar = Calendar.current
        let day = logDate
        let entries = (try? context.fetch(FetchDescriptor<HabitEntry>())) ?? []
        let parentEntry = entries.first {
            $0.habit?.id == parentId && calendar.isDate($0.logDate, inSameDayAs: day)
        }

        guard
            let parentEntry,
            parentEntry.completed,
            let start = parentEntry.actualStartMinutes,
            let duration = parentEntry.actualDurationMinutes
        else { return nil }

        return start + duration
    }
}
