import Foundation
import SwiftData
import ScoringEngine

/// A recurring routine the user tracks. Mirrors the `habits` table (spec §3).
///
/// Enum-typed columns are stored as their raw `String` values (matching the DB
/// ENUMs) and exposed through typed computed accessors, which keeps local
/// storage and server sync representations identical.
@Model
final class Habit {
    @Attribute(.unique) var id: String
    var name: String
    var categoryRaw: String
    var strictnessRaw: String
    var scheduleTypeRaw: String
    var scheduleValue: String
    /// Target start time as minutes since midnight (maps to the DB `TIME` column).
    var targetStartMinutes: Int
    var targetDurationMinutes: Int
    var hasChecklist: Bool
    var isActive: Bool
    var updatedAt: Date

    @Relationship(deleteRule: .cascade, inverse: \HabitChecklistItem.habit)
    var checklistItems: [HabitChecklistItem]

    @Relationship(deleteRule: .cascade, inverse: \HabitEntry.habit)
    var entries: [HabitEntry]

    init(
        id: String = UUID().uuidString,
        name: String,
        category: HabitCategory,
        strictness: Strictness,
        scheduleType: ScheduleType,
        scheduleValue: String,
        targetStartMinutes: Int,
        targetDurationMinutes: Int,
        hasChecklist: Bool = false,
        isActive: Bool = true,
        updatedAt: Date = .now
    ) {
        self.id = id
        self.name = name
        self.categoryRaw = category.rawValue
        self.strictnessRaw = strictness.rawValue
        self.scheduleTypeRaw = scheduleType.rawValue
        self.scheduleValue = scheduleValue
        self.targetStartMinutes = targetStartMinutes
        self.targetDurationMinutes = targetDurationMinutes
        self.hasChecklist = hasChecklist
        self.isActive = isActive
        self.updatedAt = updatedAt
        self.checklistItems = []
        self.entries = []
    }

    var category: HabitCategory {
        get { HabitCategory(rawValue: categoryRaw) ?? .base }
        set { categoryRaw = newValue.rawValue }
    }

    var strictness: Strictness {
        get { Strictness(rawValue: strictnessRaw) ?? .strict }
        set { strictnessRaw = newValue.rawValue }
    }

    var scheduleType: ScheduleType {
        get { ScheduleType(rawValue: scheduleTypeRaw) ?? .weekly }
        set { scheduleTypeRaw = newValue.rawValue }
    }

    /// Target start formatted as `HH:mm` for display.
    var targetTimeString: String {
        String(format: "%02d:%02d", targetStartMinutes / 60, targetStartMinutes % 60)
    }
}
