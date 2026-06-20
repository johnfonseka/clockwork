import SwiftUI

/// The four concentric precision rings (spec §1 / Wireframe 1). Each ring's fill
/// is its category's aggregate score for the day; `nil` means no active habits
/// in that ring today (drawn as an empty track).
struct RingChart: View {
    /// Score 0...100 per category, or `nil` when the ring has no active habits.
    let scores: [HabitCategory: Double?]

    private let lineWidth: CGFloat = 9
    private let spacing: CGFloat = 7

    var body: some View {
        ZStack {
            ForEach(Array(HabitCategory.allCases.enumerated()), id: \.element) { index, category in
                let inset = CGFloat(index) * (lineWidth + spacing)

                Circle()
                    .stroke(Terminal.divider, lineWidth: lineWidth)
                    .padding(inset)

                Circle()
                    .trim(from: 0, to: progress(for: category))
                    .stroke(
                        category.accent,
                        style: StrokeStyle(lineWidth: lineWidth, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))
                    .padding(inset)
            }
        }
        .animation(.easeOut(duration: 0.4), value: scores.mapValues { $0 ?? -1 })
    }

    private func progress(for category: HabitCategory) -> CGFloat {
        guard let score = scores[category] ?? nil else { return 0 }
        return CGFloat(max(0, min(100, score)) / 100)
    }
}
