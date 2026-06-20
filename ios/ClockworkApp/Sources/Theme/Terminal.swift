import SwiftUI

/// The strict minimalist terminal aesthetic (spec §2 "Visual Style Guide"):
/// absolute black, 100% monospaced, monochrome with single accent colors
/// reserved for the four rings.
enum Terminal {
    static let background = Color.black
    static let primary = Color.white
    static let secondary = Color(white: 0.55)
    static let dim = Color(white: 0.35)
    static let divider = Color(white: 0.22)

    static func mono(_ size: CGFloat, _ weight: Font.Weight = .regular) -> Font {
        .system(size: size, weight: weight, design: .monospaced)
    }
}

extension HabitCategory {
    /// The single accent color dedicated to this ring (spec §2).
    var accent: Color {
        switch self {
        case .base: Color.green
        case .health: Color.blue
        case .growth: Color.orange   // amber
        case .spirit: Color.purple
        }
    }
}
