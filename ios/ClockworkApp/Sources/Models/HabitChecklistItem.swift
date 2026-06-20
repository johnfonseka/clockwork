import Foundation
import SwiftData

/// A sub-task within a habit's checklist. Mirrors the `habit_checklists` table.
@Model
final class HabitChecklistItem {
    @Attribute(.unique) var id: String
    var taskName: String
    var sortOrder: Int
    var habit: Habit?

    init(
        id: String = UUID().uuidString,
        taskName: String,
        sortOrder: Int = 0
    ) {
        self.id = id
        self.taskName = taskName
        self.sortOrder = sortOrder
    }
}
