import Foundation
import Security

/// A gate this person can open. The app writes the list; see ios_widget_bridge.dart.
struct Door: Codable, Hashable {
    let id: Int
    let label: String
}

/// What the app shares with the widget, through a shared Keychain group: the doors, the API address, the language
/// and the widget's own open-only token. A Keychain group needs no App Group registered at Apple.
enum SharedKeychain {
    private static let group = "2654395QYQ.az.salam.sezam.shared"

    static func read(_ key: String) -> String? {
        let query: [CFString: Any] = [
            kSecClass: kSecClassGenericPassword,
            kSecAttrAccessGroup: group,
            kSecAttrAccount: key,
            kSecReturnData: true,
            kSecMatchLimit: kSecMatchLimitOne,
        ]
        var item: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &item) == errSecSuccess, let data = item as? Data else {
            return nil
        }
        return String(data: data, encoding: .utf8)
    }
}

enum DoorConfig {
    static var token: String? { SharedKeychain.read("widget_token") }
    static var baseURL: String? { SharedKeychain.read("widget_base_url") }
    static var locale: String { SharedKeychain.read("widget_locale") ?? "az" }

    static var doors: [Door] {
        guard let json = SharedKeychain.read("widget_doors"), let data = json.data(using: .utf8) else { return [] }
        return (try? JSONDecoder().decode([Door].self, from: data)) ?? []
    }
}

/// The result on one door's widget. Codes match the Android widget (door_widget_open.dart), plus `armed`: iOS cannot
/// show a confirmation dialog from a widget, so the first tap arms it and the second one opens.
struct DoorState: Codable {
    let code: String
    let at: Date

    /// How long a state stays on the widget before it falls back to "Aç".
    var lifetime: TimeInterval {
        switch code {
        case "armed": return 6
        case "opening": return 30
        case "opened", "sent": return 4
        default: return 8
        }
    }

    var expiresAt: Date { at.addingTimeInterval(lifetime) }
}

/// The extension's own storage: the timeline and the open intent run in the same extension.
enum DoorStateStore {
    private static func key(_ deviceId: Int) -> String { "door_state_\(deviceId)" }

    static func current(_ deviceId: Int) -> DoorState? {
        guard let data = UserDefaults.standard.data(forKey: key(deviceId)),
              let state = try? JSONDecoder().decode(DoorState.self, from: data),
              state.expiresAt > Date()
        else { return nil }
        return state
    }

    static func set(_ code: String, for deviceId: Int) {
        let state = DoorState(code: code, at: Date())
        UserDefaults.standard.set(try? JSONEncoder().encode(state), forKey: key(deviceId))
    }
}
