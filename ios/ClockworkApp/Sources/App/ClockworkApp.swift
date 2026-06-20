import SwiftUI
import SwiftData

@main
struct ClockworkApp: App {
    let container: ModelContainer

    init() {
        do {
            container = try ModelContainer(
                for: Habit.self,
                HabitChecklistItem.self,
                DailyLog.self,
                HabitEntry.self
            )
        } catch {
            fatalError("Failed to create the SwiftData ModelContainer: \(error)")
        }

        HabitPresets.seedIfNeeded(container.mainContext)
    }

    var body: some Scene {
        WindowGroup {
            DashboardView()
        }
        .modelContainer(container)
    }
}
