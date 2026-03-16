package com.rms.scanner.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable

private val DarkColorScheme = darkColorScheme(
    primary = Primary,
    onPrimary = TextPrimary,
    primaryContainer = PrimaryDark,
    onPrimaryContainer = TextPrimary,
    secondary = Secondary,
    onSecondary = TextPrimary,
    secondaryContainer = SecondaryDark,
    onSecondaryContainer = TextPrimary,
    tertiary = Info,
    onTertiary = TextPrimary,
    tertiaryContainer = Info,
    onTertiaryContainer = TextPrimary,
    error = Error,
    errorContainer = Error,
    onError = TextPrimary,
    onErrorContainer = TextPrimary,
    background = SurfaceLight,
    onBackground = TextPrimary,
    surface = SurfaceDark,
    onSurface = TextPrimary,
    surfaceVariant = SurfaceVariant,
    onSurfaceVariant = TextSecondary,
    outline = TextTertiary,
    inverseOnSurface = SurfaceLight,
    inverseSurface = TextPrimary,
    inversePrimary = PrimaryLight
)

@Composable
fun RmsScannerTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = DarkColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        typography = RmsTypography,
        content = content
    )
}
