import SwiftUI
import SwiftData

/// Detail / checklist view — Wireframe 2. Shows a habit's configuration, its
/// sub-task checklist (toggleable when an entry exists), and the current
/// compliance index from the ScoringEngine.
struct HabitDetailView: View {
    @Environment(\.modelContext) private var context
    let habit: Habit
    /// The day's entry, if the habit is scheduled today (enables checklist toggles).
    let entry: HabitEntry?

    init(habit: Habit, entry: HabitEntry? = nil) {
        self.habit = habit
        self.entry = entry
    }

    var body: some View {
        ZStack {
            Terminal.background.ignoresSafeArea()
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    config
                    if habit.hasChecklist { checklist }
                    if let entry { metric(entry) }
                }
                .padding(16)
            }
        }
        .navigationTitle(habit.name)
        .navigationBarTitleDisplayMode(.inline)
        .preferredColorScheme(.dark)
    }

    private var config: some View {
        VStack(alignment: .leading, spacing: 6) {
            field("IDENTIFIER", habit.name)
            field("CADENCE", cadenceText)
            field("RING_GROUP", "[ \(habit.category.displayName) ]")
            field("METRIC", "[ \(habit.strictness.rawValue.uppercased()) ]")
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private var checklist: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("ROUTINE_SUB_CHECKLIST")
                .font(Terminal.mono(12, .semibold))
                .foregroundStyle(Terminal.secondary)

            ForEach(habit.checklistItems.sorted(by: { $0.sortOrder < $1.sortOrder })) { item in
                Button {
                    toggle(item)
                } label: {
                    HStack(spacing: 8) {
                        Text(isChecked(item) ? "[X]" : "[ ]")
                            .foregroundStyle(isChecked(item) ? habit.category.accent : Terminal.secondary)
                        Text(item.taskName)
                            .foregroundStyle(Terminal.primary)
                        Spacer()
                    }
                    .font(Terminal.mono(13))
                }
                .buttonStyle(.plain)
                .disabled(entry == nil)
            }
        }
    }

    private func metric(_ entry: HabitEntry) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("METRIC_COMPUTATION")
                .font(Terminal.mono(12, .semibold))
                .foregroundStyle(Terminal.secondary)
            field("Target Start", TimeFormat.clock(habit.targetStartMinutes))
            field("Actual Start", entry.actualStartMinutes.map(TimeFormat.clock) ?? "--:--")
            HStack {
                Text("CURRENT_COMPLIANCE_INDEX:")
                Text("[ \(Int(entry.score.rounded()))% ]")
                    .foregroundStyle(habit.category.accent)
            }
            .font(Terminal.mono(13, .semibold))
            .foregroundStyle(Terminal.primary)
            .padding(.top, 4)
        }
    }

    private func field(_ label: String, _ value: String) -> some View {
        HStack(alignment: .top, spacing: 8) {
            Text(label)
                .foregroundStyle(Terminal.secondary)
                .frame(width: 120, alignment: .leading)
            Text(value)
                .foregroundStyle(Terminal.primary)
        }
        .font(Terminal.mono(13))
    }

    private var cadenceText: String {
        switch habit.scheduleType {
        case .weekly: "Weekly · \(habit.scheduleValue)"
        case .monthlyAbsolute: "Day \(habit.scheduleValue) of the month"
        case .monthlyRelative: habit.scheduleValue.replacingOccurrences(of: ":", with: " ").capitalized
        }
    }

    private func isChecked(_ item: HabitChecklistItem) -> Bool {
        entry?.checklistState[item.id] ?? false
    }

    private func toggle(_ item: HabitChecklistItem) {
        guard let entry else { return }
        entry.checklistState[item.id, default: false].toggle()
        entry.updatedAt = .now
        try? context.save()
    }
}
