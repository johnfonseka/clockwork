import SwiftUI
import SwiftData

/// Lists all configured habits (active and scheduled-for-other-days), each
/// linking to its detail/checklist view. Reached via the dashboard CONFIG link.
struct ConfigListView: View {
    @Query(sort: \Habit.targetStartMinutes) private var habits: [Habit]

    var body: some View {
        ZStack {
            Terminal.background.ignoresSafeArea()
            ScrollView {
                VStack(alignment: .leading, spacing: 0) {
                    Text("ALL_HABITS // \(habits.count)")
                        .font(Terminal.mono(12, .semibold))
                        .foregroundStyle(Terminal.secondary)
                        .padding(.bottom, 8)

                    ForEach(habits) { habit in
                        NavigationLink {
                            HabitDetailView(habit: habit)
                        } label: {
                            HStack(spacing: 8) {
                                Text("[\(habit.category.ringLabel)]")
                                    .foregroundStyle(habit.category.accent)
                                Text(habit.name)
                                    .foregroundStyle(Terminal.primary)
                                Spacer()
                                Text(TimeFormat.clock(habit.targetStartMinutes))
                                    .foregroundStyle(Terminal.secondary)
                            }
                            .font(Terminal.mono(13))
                            .padding(.vertical, 8)
                        }
                        .buttonStyle(.plain)
                        Divider().overlay(Terminal.divider)
                    }
                }
                .padding(16)
            }
        }
        .navigationTitle("CONFIG_MODE")
        .navigationBarTitleDisplayMode(.inline)
        .preferredColorScheme(.dark)
    }
}
