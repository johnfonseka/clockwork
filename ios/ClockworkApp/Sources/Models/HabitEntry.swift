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
        return ScoringEngine.score(
            strictness: habit.strictness,
            targetMinutes: habit.targetStartMinutes,
            actualMinutes: actualStartMinutes,
            completed: completed
        )
    }
}
