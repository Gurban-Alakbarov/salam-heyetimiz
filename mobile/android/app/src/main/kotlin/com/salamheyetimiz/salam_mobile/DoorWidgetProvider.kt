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
import es.antonborri.home_widget.HomeWidgetLaunchIntent
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
 * Tapping asks for confirmation first (GEOFENCE-4 D2/D5): the tap launches the transparent
 * [ConfirmOpenActivity] (`requestCode = AppWidgetId`), which on confirm fires the per-instance
 * background broadcast → home_widget WorkManager → Dart `doorWidgetOpenCallback`. The one exception
 * is the `location_required` state (a geofenced barrier answered "no location"): there the tap
 * launches the APP on that barrier (`salamwidget://foreground?deviceId=…`) for the in-app GPS open.
 * This provider performs NO auth / NO network; it only renders state and dispatches the click.
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

            // Confirm-first on every tap (D5); the one exception is `location_required`,
            // where the tap opens the app on this barrier for the in-app GPS open (D3).
            val tapIntent = if (statusCode == CODE_LOCATION_REQUIRED) {
                val deviceId = readDeviceId(widgetData, widgetId)
                if (deviceId != INVALID_ID) foregroundIntent(context, widgetId, deviceId)
                else confirmIntent(context, widgetId)
            } else {
                confirmIntent(context, widgetId)
            }
            views.setOnClickPendingIntent(R.id.door_widget_root, tapIntent)
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

    /** Confirm-first tap (D2/D5): launch the transparent [ConfirmOpenActivity] for this instance.
     *  requestCode = AppWidgetId + a per-instance data URI so the PendingIntents never collapse. */
    private fun confirmIntent(context: Context, widgetId: Int): PendingIntent {
        val intent = Intent(context, ConfirmOpenActivity::class.java).apply {
            putExtra(ConfirmOpenActivity.EXTRA_WIDGET_ID, widgetId)
            data = Uri.parse("salamwidget://confirm?widgetId=$widgetId")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        var flags = PendingIntent.FLAG_UPDATE_CURRENT
        if (Build.VERSION.SDK_INT >= 23) flags = flags or PendingIntent.FLAG_IMMUTABLE
        return PendingIntent.getActivity(context, widgetId, intent, flags)
    }

    /** GEOFENCE-4 (D3): in `location_required`, tap launches the APP on this barrier (foreground GPS
     *  open). deviceId comes from THIS widget's stored config; the app re-checks it server-side. */
    private fun foregroundIntent(context: Context, widgetId: Int, deviceId: Int): PendingIntent {
        val uri = Uri.parse("salamwidget://foreground?deviceId=$deviceId&widgetId=$widgetId")
        return HomeWidgetLaunchIntent.getActivity(context, MainActivity::class.java, uri)
    }

    /** This instance's stored deviceId (per-instance, legacy global fallback); [INVALID_ID] if none.
     *  home_widget stores an int via putInt, but a Long fallback keeps this crash-proof either way. */
    private fun readDeviceId(widgetData: SharedPreferences, widgetId: Int): Int {
        for (key in listOf(deviceIdKey(widgetId), LEGACY_DEVICE_ID)) {
            if (!widgetData.contains(key)) continue
            val id = try {
                widgetData.getInt(key, INVALID_ID)
            } catch (e: ClassCastException) {
                try {
                    widgetData.getLong(key, INVALID_ID.toLong()).toInt()
                } catch (e2: ClassCastException) {
                    INVALID_ID
                }
            }
            if (id != INVALID_ID) return id
        }
        return INVALID_ID
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
        "location_required" -> R.string.dw_location_required
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
        private const val CODE_LOCATION_REQUIRED = "location_required"
        private const val INVALID_ID = -1
        private const val LEGACY_DEVICE_ID = "door_widget_device_id"
        private const val LEGACY_DEVICE_LABEL = "door_widget_device_label"
        private const val LEGACY_STATUS_CODE = "door_widget_status_code"

        // Mirror DoorWidgetService's per-instance key builders.
        private fun deviceIdKey(id: Int) = "door_widget_device_id_$id"
        private fun deviceLabelKey(id: Int) = "door_widget_device_label_$id"
        private fun statusCodeKey(id: Int) = "door_widget_status_code_$id"
        private fun lastOpenMsKey(id: Int) = "door_widget_last_open_ms_$id"
    }
}
