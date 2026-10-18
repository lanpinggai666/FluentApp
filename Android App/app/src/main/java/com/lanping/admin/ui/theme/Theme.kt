package com.lanping.admin.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val WatchDarkColorScheme = darkColorScheme(
    primary = WatchPrimary,
    onPrimary = WatchOnPrimary,
    primaryContainer = WatchPrimaryContainer,
    onPrimaryContainer = WatchOnPrimaryContainer,
    secondary = WatchSuccess,
    onSecondary = WatchOnSuccess,
    secondaryContainer = WatchSuccessContainer,
    onSecondaryContainer = WatchSuccess,
    tertiary = WatchWarning,
    onTertiary = WatchOnWarning,
    tertiaryContainer = WatchWarningContainer,
    onTertiaryContainer = WatchWarning,
    error = WatchError,
    onError = WatchOnError,
    errorContainer = WatchErrorContainer,
    onErrorContainer = WatchError,
    background = WatchBlack,
    onBackground = WatchTextPrimary,
    surface = WatchSurface,
    onSurface = WatchTextPrimary,
    surfaceVariant = WatchCardBg,
    onSurfaceVariant = WatchTextSecondary,
    outline = WatchCardBorder
)

@Composable
fun 清源导航网管理Theme(
    content: @Composable () -> Unit
) {
    val colorScheme = WatchDarkColorScheme

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as? Activity)?.window
            if (window != null) {
                window.statusBarColor = WatchBlack.toArgb()
                window.navigationBarColor = WatchBlack.toArgb()
                val controller = WindowCompat.getInsetsController(window, view)
                controller.isAppearanceLightStatusBars = false
                controller.isAppearanceLightNavigationBars = false
            }
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}