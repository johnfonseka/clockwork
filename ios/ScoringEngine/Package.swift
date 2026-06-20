// swift-tools-version: 6.0
import PackageDescription

let package = Package(
    name: "ScoringEngine",
    platforms: [
        .iOS(.v17),
        .macOS(.v13),
    ],
    products: [
        .library(name: "ScoringEngine", targets: ["ScoringEngine"]),
    ],
    targets: [
        .target(name: "ScoringEngine"),
        .testTarget(
            name: "ScoringEngineTests",
            dependencies: ["ScoringEngine"]
        ),
    ]
)
