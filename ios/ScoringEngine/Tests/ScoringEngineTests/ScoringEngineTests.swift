import Testing
@testable import ScoringEngine

/// Tolerance for floating-point score comparisons.
private func isClose(_ a: Double, _ b: Double, tol: Double = 0.05) -> Bool {
    abs(a - b) <= tol
}

// MARK: - Strict (Base ring)

@Suite("Strict scoring")
struct StrictTests {
    @Test("On time scores 100")
    func onTime() {
        #expect(ScoringEngine.score(strictness: .strict, varianceMinutes: 0, completed: false) == 100)
    }

    @Test("Late decays at 3.33/min", arguments: [
        (10, 66.7),   // 100 - 10 * 3.33
        (20, 33.4),   // 100 - 20 * 3.33
        (30, 0.1),    // 100 - 30 * 3.33 ≈ 0
    ])
    func lateDecay(minutesLate: Int, expected: Double) {
        let score = ScoringEngine.score(strictness: .strict, varianceMinutes: minutesLate, completed: false)
        #expect(isClose(score, expected, tol: 0.1))
    }

    @Test("Far late is clamped to 0")
    func farLateClamped() {
        #expect(ScoringEngine.score(strictness: .strict, varianceMinutes: 120, completed: false) == 0)
    }

    @Test("Within 40-min early grace scores 100", arguments: [-1, -20, -40])
    func earlyGrace(varianceMinutes: Int) {
        #expect(ScoringEngine.score(strictness: .strict, varianceMinutes: varianceMinutes, completed: false) == 100)
    }

    @Test("Beyond early grace decays from the reduced variance")
    func earlyBeyondGrace() {
        // 41 early -> ΔT = 1 -> 100 - 1*3.33 = 96.67
        #expect(isClose(ScoringEngine.score(strictness: .strict, varianceMinutes: -41, completed: false), 96.67, tol: 0.1))
        // 70 early -> ΔT = 30 -> 100 - 30*3.33 ≈ 0.1
        #expect(isClose(ScoringEngine.score(strictness: .strict, varianceMinutes: -70, completed: false), 0.1, tol: 0.1))
    }

    @Test("Early grace (40m) is wider than late tolerance (~30m)")
    func asymmetry() {
        // 35 early -> still 100 (within grace)
        #expect(ScoringEngine.score(strictness: .strict, varianceMinutes: -35, completed: false) == 100)
        // 35 late -> well below 0-ish, definitely not 100
        #expect(ScoringEngine.score(strictness: .strict, varianceMinutes: 35, completed: false) < 100)
    }
}

// MARK: - Flexible (Growth & Spirit rings)

@Suite("Flexible scoring")
struct FlexibleTests {
    @Test("Any early clock-in scores 100", arguments: [-1, -60, -300])
    func earlyAlways100(varianceMinutes: Int) {
        #expect(ScoringEngine.score(strictness: .flexible, varianceMinutes: varianceMinutes, completed: false) == 100)
    }

    @Test("On time scores 100")
    func onTime() {
        #expect(ScoringEngine.score(strictness: .flexible, varianceMinutes: 0, completed: false) == 100)
    }

    @Test("Late decays at 0.55/min", arguments: [
        (100, 45.0),  // 100 - 100 * 0.55
        (180, 1.0),   // 100 - 180 * 0.55
    ])
    func lateDecay(minutesLate: Int, expected: Double) {
        let score = ScoringEngine.score(strictness: .flexible, varianceMinutes: minutesLate, completed: false)
        #expect(isClose(score, expected, tol: 0.1))
    }

    @Test("Far late is clamped to 0")
    func farLateClamped() {
        #expect(ScoringEngine.score(strictness: .flexible, varianceMinutes: 400, completed: false) == 0)
    }
}

// MARK: - Show-Up Bonus (Health ring)

@Suite("Show-Up Bonus scoring")
struct ShowUpBonusTests {
    @Test("Not completed scores 0 regardless of timing", arguments: [-30, 0, 30, 200])
    func notCompletedIsZero(varianceMinutes: Int) {
        #expect(ScoringEngine.score(strictness: .showUpBonus, varianceMinutes: varianceMinutes, completed: false) == 0)
    }

    @Test("Completed on time scores 100")
    func completedOnTime() {
        #expect(ScoringEngine.score(strictness: .showUpBonus, varianceMinutes: 0, completed: true) == 100)
    }

    @Test("Completed early scores 100")
    func completedEarly() {
        #expect(ScoringEngine.score(strictness: .showUpBonus, varianceMinutes: -90, completed: true) == 100)
    }

    @Test("Completed late: 50 baseline + linear time bonus", arguments: [
        (60, 75.0),    // 50 + 50 * (60/120)
        (120, 50.0),   // 50 + 0
        (240, 50.0),   // floor at baseline
    ])
    func completedLate(minutesLate: Int, expected: Double) {
        let score = ScoringEngine.score(strictness: .showUpBonus, varianceMinutes: minutesLate, completed: true)
        #expect(isClose(score, expected))
    }
}

// MARK: - Missing clock-in (nil actual)

@Suite("No clock-in recorded")
struct MissingClockInTests {
    @Test("Strict / Flexible with no actual time score 0", arguments: [Strictness.strict, .flexible])
    func strictFlexibleNil(strictness: Strictness) {
        #expect(ScoringEngine.score(strictness: strictness, targetMinutes: 600, actualMinutes: nil, completed: false) == 0)
        // completed flag does not rescue strict/flexible
        #expect(ScoringEngine.score(strictness: strictness, targetMinutes: 600, actualMinutes: nil, completed: true) == 0)
    }

    @Test("Show-Up Bonus completed without a time earns the baseline")
    func showUpNilCompleted() {
        #expect(ScoringEngine.score(strictness: .showUpBonus, targetMinutes: 600, actualMinutes: nil, completed: true) == 50)
    }

    @Test("Show-Up Bonus not completed without a time scores 0")
    func showUpNilNotCompleted() {
        #expect(ScoringEngine.score(strictness: .showUpBonus, targetMinutes: 600, actualMinutes: nil, completed: false) == 0)
    }
}

// MARK: - Variance helpers

@Suite("Variance computation")
struct VarianceTests {
    @Test("Simple late and early")
    func simple() {
        let target = ScoringEngine.minutesSinceMidnight(hour: 6, minute: 30)   // 390
        #expect(ScoringEngine.signedVariance(targetMinutes: target, actualMinutes: 392) == 2)
        #expect(ScoringEngine.signedVariance(targetMinutes: target, actualMinutes: 380) == -10)
    }

    @Test("Bedtime logged after midnight reads as small positive variance")
    func wrapPastMidnight() {
        let bedtime = ScoringEngine.minutesSinceMidnight(hour: 22, minute: 30)  // 1350
        let loggedAt = ScoringEngine.minutesSinceMidnight(hour: 0, minute: 15)  // 15
        #expect(ScoringEngine.signedVariance(targetMinutes: bedtime, actualMinutes: loggedAt) == 105)
    }

    @Test("Early morning target logged just before midnight reads as negative")
    func wrapBeforeMidnight() {
        let target = ScoringEngine.minutesSinceMidnight(hour: 0, minute: 15)    // 15
        let loggedAt = ScoringEngine.minutesSinceMidnight(hour: 23, minute: 45) // 1425
        #expect(ScoringEngine.signedVariance(targetMinutes: target, actualMinutes: loggedAt) == -30)
    }

    @Test("End-to-end: bedtime logged 105 min late as a strict habit scores 0")
    func endToEnd() {
        let bedtime = ScoringEngine.minutesSinceMidnight(hour: 22, minute: 30)
        let loggedAt = ScoringEngine.minutesSinceMidnight(hour: 0, minute: 15)
        let score = ScoringEngine.score(strictness: .strict, targetMinutes: bedtime, actualMinutes: loggedAt, completed: true)
        #expect(score == 0) // 105 min late, far beyond the strict window
    }
}

// MARK: - Pause Engine aggregation

@Suite("Aggregation and Pause Engine")
struct AggregationTests {
    @Test("Paused days are excluded from the average")
    func excludesPaused() {
        let days: [(score: Double, isPaused: Bool)] = [
            (100, false),
            (0, true),    // paused: must be ignored
            (50, false),
        ]
        #expect(ScoringEngine.aggregate(days) == 75) // mean of 100 and 50
    }

    @Test("All-paused returns nil")
    func allPaused() {
        let days: [(score: Double, isPaused: Bool)] = [(80, true), (90, true)]
        #expect(ScoringEngine.aggregate(days) == nil)
    }

    @Test("Empty input returns nil")
    func empty() {
        #expect(ScoringEngine.aggregate([]) == nil)
    }
}
