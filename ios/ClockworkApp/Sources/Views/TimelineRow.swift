import SwiftUI
import SwiftData

/// A single habit's row in the logged timeline (Wireframe 1).
struct TimelineRow: View {
    @Environment(\.modelContext) private var context
    let entry: HabitEntry

    var body: some View {
        let habit = entry.habit
        VStack(alignment: .leading, spacing: 4) {
            HStack(spacing: 8) {
                Text(TimeFormat.clock(habit?.targetStartMinutes ?? 0))
                    .foregroundStyle(Terminal.secondary)
                Text("|").foregroundStyle(Terminal.dim)
                Text(entry.statusMarker)
                    .foregroundStyle(entry.completed ? (habit?.category.accent ?? Terminal.primary) : Terminal.secondary)
                Text(habit?.name.uppercased() ?? "—")
                    .foregroundStyle(Terminal.primary)
            }
            .font(Terminal.mono(14, .semibold))

            HStack(spacing: 6) {
                Text("TRGT \(TimeFormat.clock(habit?.targetStartMinutes ?? 0))")
                Text("|").foregroundStyle(Terminal.dim)
                Text("ACTL \(entry.actualStartMinutes.map(TimeFormat.clock) ?? "--:--")")
                Text("|").foregroundStyle(Terminal.dim)
                Text("VAR \(entry.variance.map(TimeFormat.variance) ?? "--")")
            }
            .font(Terminal.mono(11))
            .foregroundStyle(Terminal.secondary)

            if entry.completed {
                Text("STAT COMPLETED [\(habit?.strictness.rawValue.uppercased() ?? "") \(Int(entry.score.rounded()))%]")
                    .font(Terminal.mono(11, .medium))
                    .foregroundStyle(habit?.category.accent ?? Terminal.primary)
            } else {
                Button(action: clockIn) {
                    Text("[ RUN_CLOCK_IN ]")
                        .font(Terminal.mono(12, .semibold))
                        .foregroundStyle(habit?.category.accent ?? Terminal.primary)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.vertical, 6)
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func clockIn() {
        entry.actualStartMinutes = Date().minutesSinceMidnight()
        entry.completed = true
        entry.updatedAt = .now
        try? context.save()
    }
}
