package com.lanping.admin.ui.util

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.view.HapticFeedbackConstants
import android.view.View

object WatchHaptics {

    private fun getVibrator(context: Context?): Vibrator? {
        return context?.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
    }

    private fun vibrateFallback(context: Context?, pattern: LongArray) {
        val vibrator = getVibrator(context) ?: return
        if (!vibrator.hasVibrator()) return
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator.vibrate(VibrationEffect.createWaveform(pattern, -1))
            } else {
                @Suppress("DEPRECATION")
                vibrator.vibrate(pattern, -1)
            }
        } catch (_: Exception) {
            // Silently ignore if device doesn't support vibration
        }
    }

    fun click(view: View?, context: Context? = view?.context) {
        val flags = HapticFeedbackConstants.FLAG_IGNORE_VIEW_SETTING
        val handled = view?.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY, flags) ?: false
        if (!handled) {
            vibrateFallback(context, longArrayOf(0, 15))
        }
    }

    fun success(view: View?, context: Context? = view?.context) {
        val flags = HapticFeedbackConstants.FLAG_IGNORE_VIEW_SETTING
        val constant = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            HapticFeedbackConstants.CONFIRM
        } else {
            HapticFeedbackConstants.LONG_PRESS
        }
        val handled = view?.performHapticFeedback(constant, flags) ?: false
        if (!handled) {
            vibrateFallback(context, longArrayOf(0, 45))
        }
    }

    fun reject(view: View?, context: Context? = view?.context) {
        val flags = HapticFeedbackConstants.FLAG_IGNORE_VIEW_SETTING
        val constant = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            HapticFeedbackConstants.REJECT
        } else {
            HapticFeedbackConstants.LONG_PRESS
        }
        val handled = view?.performHapticFeedback(constant, flags) ?: false
        if (!handled) {
            vibrateFallback(context, longArrayOf(0, 60, 40, 60))
        }
    }

    fun error(view: View?, context: Context? = view?.context) {
        val flags = HapticFeedbackConstants.FLAG_IGNORE_VIEW_SETTING
        val handled = view?.performHapticFeedback(HapticFeedbackConstants.LONG_PRESS, flags) ?: false
        if (!handled) {
            vibrateFallback(context, longArrayOf(0, 80, 50, 80))
        }
    }
}

