import AppIntents
import WidgetKit

/// A door in the widget's edit screen (long-press the widget › Edit Widget).
struct DoorEntity: AppEntity {
    static var typeDisplayRepresentation: TypeDisplayRepresentation = "Qapı"
    static var defaultQuery = DoorQuery()

    let id: Int
    let label: String

    var displayRepresentation: DisplayRepresentation { DisplayRepresentation(title: "\(label)") }

    init(_ door: Door) {
        id = door.id
        label = door.label
    }
}

struct DoorQuery: EntityQuery {
    func entities(for identifiers: [Int]) async throws -> [DoorEntity] {
        DoorConfig.doors.filter { identifiers.contains($0.id) }.map(DoorEntity.init)
    }

    func suggestedEntities() async throws -> [DoorEntity] {
        DoorConfig.doors.map(DoorEntity.init)
    }

    func defaultResult() async -> DoorEntity? {
        DoorConfig.doors.first.map(DoorEntity.init)
    }
}

struct SelectDoorIntent: WidgetConfigurationIntent {
    static var title: LocalizedStringResource = "Qapı"
    static var description = IntentDescription("Vidjetin açacağı qapı.")

    @Parameter(title: "Qapı")
    var door: DoorEntity?
}

/// Runs in the extension, without opening the app. First tap arms, second tap opens: see [DoorState].
struct OpenDoorIntent: AppIntent {
    static var title: LocalizedStringResource = "Qapını aç"
    static var isDiscoverable = false

    @Parameter(title: "Qapı")
    var deviceId: Int

    init() {}

    init(deviceId: Int) {
        self.deviceId = deviceId
    }

    func perform() async throws -> some IntentResult {
        switch DoorStateStore.current(deviceId)?.code {
        case "opening":
            return .result() // a command is already on its way
        case "armed":
            DoorStateStore.set("opening", for: deviceId)
            reload()
            DoorStateStore.set(await DoorAPI.open(deviceId), for: deviceId)
        default:
            DoorStateStore.set("armed", for: deviceId)
        }
        reload()
        return .result()
    }

    private func reload() {
        WidgetCenter.shared.reloadTimelines(ofKind: SalamDoorWidget.kind)
    }
}
