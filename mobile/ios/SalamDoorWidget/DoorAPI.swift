import Foundation

/// Opens a door the same way the Android widget does (performDoorWidgetOpen in door_widget_open.dart): one open, then
/// a bounded poll of the command. Returns a state code. "opened" only ever comes from a confirmed command, never from
/// a bare 202. The widget never refreshes the app session.
enum DoorAPI {
    private static let pollInterval: UInt64 = 1_200_000_000 // 1.2 s
    private static let maxPolls = 6
    private static let maxTransientErrors = 3

    static func open(_ deviceId: Int) async -> String {
        guard let token = DoorConfig.token, !token.isEmpty else { return "session_expired" }
        guard let base = DoorConfig.baseURL, let url = URL(string: "\(base)/v1/devices/\(deviceId)/open") else {
            return "error"
        }

        var request = authorized(URLRequest(url: url), token)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue(UUID().uuidString, forHTTPHeaderField: "Idempotency-Key")
        let version = Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String
        request.httpBody = try? JSONSerialization.data(withJSONObject: ["client_app_version": version ?? ""])

        let data: Data
        let status: Int
        do {
            (data, status) = try await send(request)
        } catch {
            return "network_error"
        }

        switch status {
        case 200..<300:
            break
        case 401:
            return "session_expired"
        case 403:
            return errorCode(data) == "location_required" ? "location_required" : "unauthorized"
        default:
            return "error"
        }

        guard let ack = json(data), let commandId = ack["command_id"] as? Int else { return "not_confirmed" }
        let expectedMs = ack["expected_completion_ms"] as? Int ?? 0
        return await poll(commandId, token: token, base: base, expectedMs: expectedMs)
    }

    private static func poll(_ commandId: Int, token: String, base: String, expectedMs: Int) async -> String {
        guard let url = URL(string: "\(base)/v1/commands/\(commandId)") else { return "not_confirmed" }
        let polls = min(Int((Double(expectedMs) / 1200).rounded(.up)) + 2, maxPolls)
        var transientErrors = 0

        for _ in 0..<polls {
            try? await Task.sleep(nanoseconds: pollInterval)
            do {
                let (data, status) = try await send(authorized(URLRequest(url: url), token))
                guard (200..<300).contains(status) else { return "not_confirmed" }
                transientErrors = 0
                switch json(data)?["state"] as? String {
                case "opened": return "opened"
                case "dispatched": return "sent"
                case "failed": return "failed"
                case "expired": return "no_response"
                default: continue
                }
            } catch {
                transientErrors += 1
                if transientErrors > maxTransientErrors { return "not_confirmed" }
            }
        }
        return "not_confirmed"
    }

    private static func authorized(_ request: URLRequest, _ token: String) -> URLRequest {
        var request = request
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue(DoorConfig.locale, forHTTPHeaderField: "Accept-Language")
        return request
    }

    private static func send(_ request: URLRequest) async throws -> (Data, Int) {
        let (data, response) = try await URLSession.shared.data(for: request)
        return (data, (response as? HTTPURLResponse)?.statusCode ?? 0)
    }

    private static func json(_ data: Data) -> [String: Any]? {
        try? JSONSerialization.jsonObject(with: data) as? [String: Any]
    }

    private static func errorCode(_ data: Data) -> String? {
        (json(data)?["error"] as? [String: Any])?["code"] as? String
    }
}
