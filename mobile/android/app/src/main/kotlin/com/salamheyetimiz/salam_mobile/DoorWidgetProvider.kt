package com.salamheyetimiz.salam_mobile

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.content.res.Configuration
import android.net.Uri
import android.os.Build
import android.view.View
import android.widget.RemoteViews
import es.antonborri.home_widget.HomeWidgetBackgroundReceiver
import es.antonborri.home_widget.HomeWidgetProvider
import java.util.Locale

/**
 * Home-screen "Qapını aç" widget provider (W5: multi-instance + localized).
 *
 * Each widget instance (a real Android AppWidgetId) is rendered from ITS OWN per-instance config
 * (`door_widget_*_<id>`), falling back to the legacy pre-W5 global keys so old widgets keep working.
 * All text is localized here from a locale-specific context built from the persisted app locale
 * (`door_widget_locale`), so the widget follows the APP language, not the device locale. The stored
 * status is a non-localized state CODE — the background isolate stays locale-agnostic.
 *
 * Tapping fires a per-instance background broadcast (`requestCode = AppWidgetId`, URI carries the id)
 * → home_widget WorkManager → Dart `doorWidgetOpenCallback`. This provider performs NO auth / NO
 * network; it only renders state and dispatches the click.
 */
class DoorWidgetProvider : HomeWidgetProvider() {
    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray,
        widgetData: SharedPreferences,
    ) {
        val loc = localized(context, widgetData.getString(KEY_LOCALE, DEFAULT_LOCALE) ?: DEFAULT_LOCALE)

        for (widgetId in appWidgetIds) {
            val label = (widgetData.getString(deviceLabelKey(widgetId), null)
                ?: widgetData.getString(LEGACY_DEVICE_LABEL, null))?.trim()
            val statusCode = (widgetData.getString(statusCodeKey(widgetId), null)
                ?: widgetData.getString(LEGACY_STATUS_CODE, null))?.trim()

            val views = RemoteViews(context.packageName, R.layout.door_widget)
            views.setTextViewText(R.id.door_widget_title, loc.getString(R.string.dw_open))

            if (label.isNullOrEmpty()) {
                // No configured device → hide label, show the localized "no door" hint.
                views.setViewVisibility(R.id.door_widget_device_label, View.GONE)
                views.setTextViewText(R.id.door_widget_status, loc.getString(R.string.dw_no_device))
                views.setViewVisibility(R.id.door_widget_status, View.VISIBLE)
            } else {
                views.setTextViewText(R.id.door_widget_device_label, label)
                views.setViewVisibility(R.id.door_widget_device_label, View.VISIBLE)
                // Open-status only. A configured widget never shows the "no door" hint (guard).
                val resId = if (statusCode == null || statusCode == CODE_NO_DEVICE) 0 else statusResId(statusCode)
                if (resId == 0) {
                    views.setViewVisibility(R.id.door_widget_status, View.GONE)
                } else {
                    views.setTextViewText(R.id.door_widget_status, loc.getString(resId))
                    views.setViewVisibility(R.id.door_widget_status, View.VISIBLE)
                }
            }

            views.setOnClickPendingIntent(R.id.door_widget_root, openIntent(context, widgetId))
            appWidgetManager.updateAppWidget(widgetId, views)
        }
    }

    /** Widget removed from the home screen → drop ONLY that instance's config. */
    override fun onDeleted(context: Context, appWidgetIds: IntArray) {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
        for (widgetId in appWidgetIds) {
            prefs.remove(deviceIdKey(widgetId))
            prefs.remove(deviceLabelKey(widgetId))
            prefs.remove(statusCodeKey(widgetId))
            prefs.remove(lastOpenMsKey(widgetId))
        }
        prefs.apply()
    }

    /** Per-instance background-open PendingIntent — requestCode = AppWidgetId (W5 D5). */
    private fun openIntent(context: Context, widgetId: Int): PendingIntent {
        val intent = Intent(context, HomeWidgetBackgroundReceiver::class.java).apply {
            action = BACKGROUND_ACTION
            data = Uri.parse("salamwidget://open?widgetId=$widgetId")
        }
        var flags = PendingIntent.FLAG_UPDATE_CURRENT
        if (Build.VERSION.SDK_INT >= 23) flags = flags or PendingIntent.FLAG_IMMUTABLE
        return PendingIntent.getBroadcast(context, widgetId, intent, flags)
    }

    private fun localized(context: Context, code: String): Context {
        val config = Configuration(context.resources.configuration)
        config.setLocale(Locale(code))
        return context.createConfigurationContext(config)
    }

    private fun statusResId(code: String): Int = when (code) {
        "opening" -> R.string.dw_opening
        "sent" -> R.string.dw_sent
        "opened" -> R.string.dw_opened
        "failed" -> R.string.dw_failed
        "not_confirmed" -> R.string.dw_not_confirmed
        "no_response" -> R.string.dw_no_response
        "no_device" -> R.string.dw_no_device
        "unauthorized" -> R.string.dw_unauthorized
        "session_expired" -> R.string.dw_session_expired
        "network_error" -> R.string.dw_network_error
        "error" -> R.string.dw_error
        else -> 0
    }

    companion object {
        private const val PREFS = "HomeWidgetPreferences"
        private const val KEY_LOCALE = "door_widget_locale"
        private const val DEFAULT_LOCALE = "az"
        private const val CODE_NO_DEVICE = "no_device"
        private const val LEGACY_DEVICE_LABEL = "door_widget_device_label"
        private const val LEGACY_STATUS_CODE = "door_widget_status_code"
        private const val BACKGROUND_ACTION = "es.antonborri.home_widget.action.BACKGROUND"

        // Mirror DoorWidgetService's per-instance key builders.
        private fun deviceIdKey(id: Int) = "door_widget_device_id_$id"
        private fun deviceLabelKey(id: Int) = "door_widget_device_label_$id"
        private fun statusCodeKey(id: Int) = "door_widget_status_code_$id"
        private fun lastOpenMsKey(id: Int) = "door_widget_last_open_ms_$id"
    }
}
