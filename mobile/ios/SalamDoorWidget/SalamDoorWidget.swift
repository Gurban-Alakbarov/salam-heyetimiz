import AppIntents
import SwiftUI
import WidgetKit

struct DoorEntry: TimelineEntry {
    let date: Date
    let door: Door?
    let state: DoorState?
}

struct DoorProvider: AppIntentTimelineProvider {
    func placeholder(in context: Context) -> DoorEntry {
        DoorEntry(date: Date(), door: Door(id: 0, label: "Həyət qapısı"), state: nil)
    }

    func snapshot(for configuration: SelectDoorIntent, in context: Context) async -> DoorEntry {
        entry(for: configuration)
    }

    func timeline(for configuration: SelectDoorIntent, in context: Context) async -> Timeline<DoorEntry> {
        let now = entry(for: configuration)
        guard let state = now.state else {
            return Timeline(entries: [now], policy: .after(Date().addingTimeInterval(3600)))
        }
        // The state falls back to "Aç" by itself, without another tap.
        let rest = DoorEntry(date: state.expiresAt, door: now.door, state: nil)
        return Timeline(entries: [now, rest], policy: .after(state.expiresAt.addingTimeInterval(3600)))
    }

    private func entry(for configuration: SelectDoorIntent) -> DoorEntry {
        let doors = DoorConfig.doors
        // A door taken away from this person is not shown, and their token no longer opens it anyway.
        let door = doors.first { $0.id == configuration.door?.id } ?? doors.first
        return DoorEntry(date: Date(), door: door, state: door.flatMap { DoorStateStore.current($0.id) })
    }
}

/// Design B of the Android widget: a full coloured button with a white circle (DoorWidgetProvider.kt).
struct DoorWidgetView: View {
    @Environment(\.widgetFamily) private var family
    let entry: DoorEntry

    private var code: String { entry.state?.code ?? "open" }

    private var isSuccess: Bool { code == "opened" || code == "sent" }

    private var isResting: Bool { code == "open" || code == "armed" }

    private var color: Color {
        if code == "opening" { return Color(red: 0.827, green: 0.184, blue: 0.184) } // #D32F2F
        if isSuccess { return Color(red: 0.290, green: 0.573, blue: 0.086) } // #4A9216
        if isResting { return Color(red: 0.373, green: 0.686, blue: 0.114) } // #5FAF1D
        return Color(red: 0.706, green: 0.325, blue: 0.035) // #B45309
    }

    private var icon: String {
        switch code {
        case "open": return "power"
        case "armed": return "hand.tap"
        case "opening": return "arrow.triangle.2.circlepath"
        case "opened", "sent": return "checkmark"
        default: return "exclamationmark.circle"
        }
    }

    var body: some View {
        Group {
            if let door = entry.door {
                if code == "location_required" || code == "session_expired" {
                    // These need the app: for GPS, or to sign in again.
                    Link(destination: URL(string: "salam://widget/open?deviceId=\(door.id)")!) { content(door.label) }
                } else {
                    Button(intent: OpenDoorIntent(deviceId: door.id)) { content(door.label) }
                        .buttonStyle(.plain)
                }
            } else {
                content(nil)
            }
        }
        .containerBackground(color, for: .widget)
    }

    @ViewBuilder
    private func content(_ label: String?) -> some View {
        if family == .systemSmall {
            VStack(alignment: .leading, spacing: 8) {
                circle
                Spacer(minLength: 0)
                texts(label)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
        } else {
            HStack(spacing: 12) {
                circle
                texts(label)
                Spacer(minLength: 0)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        }
    }

    private var circle: some View {
        ZStack {
            Circle().fill(.white).frame(width: 44, height: 44)
            Image(systemName: icon).font(.system(size: 22, weight: .semibold)).foregroundStyle(color)
        }
    }

    private func texts(_ label: String?) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            if let label {
                Text(label).font(.system(size: 15, weight: .bold)).lineLimit(1)
                Text(DoorStrings.text(code)).font(.system(size: 13)).lineLimit(2)
            } else {
                Text(DoorStrings.text("no_device")).font(.system(size: 13)).lineLimit(2)
            }
        }
        .foregroundStyle(.white)
    }
}

struct SalamDoorWidget: Widget {
    static let kind = "SalamDoorWidget"

    var body: some WidgetConfiguration {
        AppIntentConfiguration(kind: Self.kind, intent: SelectDoorIntent.self, provider: DoorProvider()) { entry in
            DoorWidgetView(entry: entry)
        }
        .configurationDisplayName("Qapını aç")
        .description("Qapını ana ekrandan açın.")
        .supportedFamilies([.systemSmall, .systemMedium])
    }
}

@main
struct SalamDoorWidgetBundle: WidgetBundle {
    var body: some Widget {
        SalamDoorWidget()
    }
}
