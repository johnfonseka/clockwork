import SwiftUI
import SwiftData
import ScoringEngine

/// The main dashboard — Wireframe 1. System metrics (the four rings) over a
/// chronological timeline of today's habits, in the terminal aesthetic.
struct DashboardView: View {
    @Environment(\.modelContext) private var context
    @Query private var entries: [HabitEntry]
    @Query private var habits: [Habit]
    @Query private var dailyLogs: [DailyLog]

    private let calendar = Calendar.current
    private var today: Date { calendar.startOfDay(for: .now) }

    var body: some View {
        NavigationStack {
            ZStack {
                Terminal.background.ignoresSafeArea()
                ScrollView {
                    VStack(alignment: .leading, spacing: 16) {
                        header
                        metricsPanel
                        pauseCommand
                        timelineHeader
                        timeline
                        footer
                    }
                    .padding(16)
                }
            }
            .navigationBarHidden(true)
            .toolbar { ToolbarItem(placement: .topBarTrailing) { configLink } }
            .preferredColorScheme(.dark)
        }
        .tint(.white)
        .onAppear {
            DayPlanner.ensureEntries(for: .now, context: context)
        }
    }

    // MARK: - Sections

    private var header: some View {
        panelBorder {
            HStack {
                Text("CCW_OS v0.1.0")
                Spacer()
                Text("[SYS_STATUS: \(isPaused ? "PAUSED" : "ACTIVE")]")
                    .foregroundStyle(isPaused ? Color.orange : Color.green)
            }
            .font(Terminal.mono(13, .semibold))
            .foregroundStyle(Terminal.primary)
        }
    }

    private var metricsPanel: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("SYS_METRICS:")
                .font(Terminal.mono(12, .semibold))
                .foregroundStyle(Terminal.secondary)

            HStack(alignment: .center, spacing: 18) {
                RingChart(scores: categoryScores)
                    .frame(width: 150, height: 150)

                VStack(alignment: .leading, spacing: 8) {
                    ForEach(HabitCategory.allCases, id: \.self) { category in
                        legendRow(category)
                    }
                }
            }
        }
    }

    private func legendRow(_ category: HabitCategory) -> some View {
        let score = categoryScores[category] ?? nil
        return HStack(spacing: 8) {
            Circle().fill(category.accent).frame(width: 8, height: 8)
            Text("[\(category.ringLabel)]")
                .foregroundStyle(category.accent)
            Spacer(minLength: 4)
            Text(score.map { "\(Int($0.rounded()))%" } ?? "--%")
                .foregroundStyle(Terminal.primary)
        }
        .font(Terminal.mono(12, .medium))
        .frame(width: 150)
    }

    private var pauseCommand: some View {
        Button(action: togglePause) {
            Text("[!] COMMAND: >> /exec/\(isPaused ? "resume_today" : "pause_today")")
                .font(Terminal.mono(12, .semibold))
                .foregroundStyle(isPaused ? Color.green : Color.orange)
        }
        .buttonStyle(.plain)
    }

    private var timelineHeader: some View {
        panelBorder {
            Text("LOGGED_TIMELINE // \(TimeFormat.dateStamp(today))")
                .font(Terminal.mono(13, .semibold))
                .foregroundStyle(Terminal.primary)
        }
    }

    private var timeline: some View {
        VStack(alignment: .leading, spacing: 0) {
            if todaysEntries.isEmpty {
                Text("NO_ACTIVE_TASKS // 0 scheduled today")
                    .font(Terminal.mono(12))
                    .foregroundStyle(Terminal.dim)
                    .padding(.vertical, 8)
            } else {
                ForEach(todaysEntries) { entry in
                    NavigationLink {
                        if let habit = entry.habit { HabitDetailView(habit: habit, entry: entry) }
                    } label: {
                        TimelineRow(entry: entry)
                    }
                    .buttonStyle(.plain)
                    Divider().overlay(Terminal.divider)
                }
            }
        }
    }

    private var footer: some View {
        Text("SYS_LOG: \(stampNow) UTC // SYNC_STATUS: LOCAL")
            .font(Terminal.mono(11))
            .foregroundStyle(Terminal.dim)
            .padding(.top, 4)
    }

    private var configLink: some View {
        NavigationLink { ConfigListView() } label: {
            Text("CONFIG").font(Terminal.mono(12, .semibold))
        }
    }

    // MARK: - Derived data

    private var todaysEntries: [HabitEntry] {
        entries
            .filter { calendar.isDate($0.logDate, inSameDayAs: today) }
            .sorted { ($0.habit?.targetStartMinutes ?? 0) < ($1.habit?.targetStartMinutes ?? 0) }
    }

    /// Average score per category over today's entries; `nil` if the ring has no
    /// active habits today.
    private var categoryScores: [HabitCategory: Double?] {
        var result: [HabitCategory: Double?] = [:]
        for category in HabitCategory.allCases {
            let scores = todaysEntries
                .filter { $0.habit?.category == category }
                .map(\.score)
            result[category] = scores.isEmpty ? nil : scores.reduce(0, +) / Double(scores.count)
        }
        return result
    }

    private var todaysLog: DailyLog? {
        dailyLogs.first { calendar.isDate($0.logDate, inSameDayAs: today) }
    }

    private var isPaused: Bool { todaysLog?.isPaused ?? false }

    private var stampNow: String {
        let c = calendar.dateComponents([.hour, .minute, .second], from: .now)
        return String(format: "%02d:%02d:%02d", c.hour ?? 0, c.minute ?? 0, c.second ?? 0)
    }

    // MARK: - Actions

    private func togglePause() {
        let log = DayPlanner.dailyLog(for: .now, context: context)
        log.isPaused.toggle()
        log.pauseReason = log.isPaused ? .holiday : nil
        log.updatedAt = .now
        try? context.save()
    }

    // MARK: - Helpers

    private func panelBorder<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        content()
            .padding(10)
            .frame(maxWidth: .infinity, alignment: .leading)
            .overlay(
                RoundedRectangle(cornerRadius: 2)
                    .stroke(Terminal.divider, lineWidth: 1)
            )
    }
}
