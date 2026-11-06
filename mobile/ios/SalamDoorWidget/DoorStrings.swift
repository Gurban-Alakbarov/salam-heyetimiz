import Foundation

/// Widget texts in the app's language, the same as the Android widget's dw_* strings (values*/strings.xml).
enum DoorStrings {
    private static let az = [
        "open": "Aç", "armed": "Təsdiq üçün yenə toxunun", "opening": "Açılır...", "sent": "Sorğu göndərildi",
        "opened": "Açıldı", "failed": "Qapı açıla bilmədi", "not_confirmed": "Nəticə təsdiqlənmədi",
        "no_response": "Cavab alınmadı", "no_device": "Qapı seçilməyib",
        "location_required": "Məkan tələb olunur — tətbiqi açın", "unauthorized": "İcazə yoxdur",
        "session_expired": "Sessiya bitib — tətbiqi açın", "network_error": "Şəbəkə xətası", "error": "Xəta baş verdi",
    ]
    private static let en = [
        "open": "Open", "armed": "Tap again to confirm", "opening": "Opening...", "sent": "Request sent",
        "opened": "Opened", "failed": "Door could not be opened", "not_confirmed": "Result not confirmed",
        "no_response": "No response received", "no_device": "No door selected",
        "location_required": "Location required — open the app", "unauthorized": "Access denied",
        "session_expired": "Session expired — open the app", "network_error": "Network error",
        "error": "An error occurred",
    ]
    private static let ru = [
        "open": "Открыть", "armed": "Нажмите ещё раз для подтверждения", "opening": "Открывается...",
        "sent": "Запрос отправлен", "opened": "Открыто", "failed": "Не удалось открыть дверь",
        "not_confirmed": "Результат не подтверждён", "no_response": "Ответ не получен", "no_device": "Дверь не выбрана",
        "location_required": "Требуется геолокация — откройте приложение", "unauthorized": "Нет доступа",
        "session_expired": "Сессия истекла — откройте приложение", "network_error": "Ошибка сети",
        "error": "Произошла ошибка",
    ]

    static func text(_ code: String) -> String {
        let table: [String: String]
        switch DoorConfig.locale {
        case "en": table = en
        case "ru": table = ru
        default: table = az
        }
        return table[code] ?? table["error"] ?? code
    }
}
