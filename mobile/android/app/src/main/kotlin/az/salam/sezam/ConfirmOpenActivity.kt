package com.salamheyetimiz.salam_mobile

import android.app.Activity
import android.app.AlertDialog
import android.content.Context
import android.content.Intent
import android.content.res.Configuration
import android.net.Uri
import android.os.Bundle
import es.antonborri.home_widget.HomeWidgetBackgroundReceiver
import java.util.Locale

/**
 * Confirmation prompt shown before a home-screen widget fires a door open (GEOFENCE-4, D2/D5).
 *
 * AppWidget RemoteViews cannot host a dialog, so the widget tap launches THIS transparent activity
 * instead of firing the open directly. It carries ONLY the AppWidgetId — never a deviceId: the
 * device is resolved server-side from that widget's own stored config in the background isolate, so
 * a tampered launch can never point one widget at another widget's barrier.
 *
 *  • Confirm ("Qapını aç") → the EXACT W1–W5 background-open broadcast (`salamwidget://open?widgetId=N`).
 *  • Cancel / back / tap-outside → finish, NO broadcast, NO /open request.
 *
 * No biometrics, no network, no token — it only gates the existing background dispatch. A single
 * [submitted] latch makes a double-tap on "Qapını aç" fire exactly one open (server idempotency /
 * cooldown still apply downstream).
 */
class ConfirmOpenActivity : Activity() {
    private var submitted = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val widgetId = intent?.getIntExtra(EXTRA_WIDGET_ID, INVALID_ID) ?: INVALID_ID
        if (widgetId == INVALID_ID) {
            finish()
            return
        }

        val loc = localizedContext()
        AlertDialog.Builder(this, android.R.style.Theme_Material_Light_Dialog_Alert)
            .setTitle(loc.getString(R.string.dw_confirm_title))
            .setPositiveButton(loc.getString(R.string.dw_confirm_open)) { _, _ -> confirm(widgetId) }
            .setNegativeButton(loc.getString(R.string.dw_cancel)) { _, _ -> finish() }
            .setOnCancelListener { finish() } // back button / tap outside → cancel, no open
            .show()
    }

    /** Fire the same background-open broadcast W1–W5 uses; guarded so it dispatches at most once. */
    private fun confirm(widgetId: Int) {
        if (submitted) return
        submitted = true
        val broadcast = Intent(this, HomeWidgetBackgroundReceiver::class.java).apply {
            action = BACKGROUND_ACTION
            data = Uri.parse("salamwidget://open?widgetId=$widgetId")
        }
        sendBroadcast(broadcast)
        finish()
    }

    /** Localize dialog text to the persisted APP locale (W5 mechanism), not the device locale. */
    private fun localizedContext(): Context {
        val prefs = getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val code = prefs.getString(KEY_LOCALE, DEFAULT_LOCALE) ?: DEFAULT_LOCALE
        val config = Configuration(resources.configuration)
        config.setLocale(Locale(code))
        return createConfigurationContext(config)
    }

    companion object {
        const val EXTRA_WIDGET_ID = "widget_id"
        private const val INVALID_ID = -1
        private const val PREFS = "HomeWidgetPreferences"
        private const val KEY_LOCALE = "door_widget_locale"
        private const val DEFAULT_LOCALE = "az"
        private const val BACKGROUND_ACTION = "es.antonborri.home_widget.action.BACKGROUND"
    }
}
