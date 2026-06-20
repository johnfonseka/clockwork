import SwiftUI
import SwiftData

/// Phase 2 shell: confirms the SwiftData stack and seeded presets render, and
/// that the shared ScoringEngine is wired in. The full terminal-aesthetic
/// 4-ring dashboard arrives in Phase 3.
struct ContentView: View {
    @Query(sort: \Habit.targetStartMinutes) private var habits: [Habit]

    var body: some View {
        NavigationStack {
            List {
                Section("ACTIVE_HABITS // \(habits.count)") {
                    ForEach(habits) { habit in
                        HabitRow(habit: habit)
                    }
                }
            }
            .listStyle(.plain)
            .navigationTitle("CCW_OS")
        }
    }
}

private struct HabitRow: View {
    let habit: Habit

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(habit.name)
                .font(.system(.headline, design: .monospaced))

            Text("[\(habit.category.ringLabel)] \(habit.strictness.rawValue.uppercased()) · TRGT \(habit.targetTimeString) · \(habit.targetDurationMinutes)m")
                .font(.system(.caption, design: .monospaced))
                .foregroundStyle(.secondary)

            if habit.hasChecklist {
                ForEach(habit.checklistItems.sorted(by: { $0.sortOrder < $1.sortOrder })) { item in
                    Text("  [ ] \(item.taskName)")
                        .font(.system(.caption2, design: .monospaced))
                        .foregroundStyle(.secondary)
                }
            }
        }
        .padding(.vertical, 2)
    }
}

#Preview {
    ContentView()
        .modelContainer(for: [Habit.self, HabitChecklistItem.self, DailyLog.self, HabitEntry.self], inMemory: true)
}
