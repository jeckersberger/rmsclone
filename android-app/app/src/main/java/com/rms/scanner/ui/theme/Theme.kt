package com.rms.scanner.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable

private val RmsColorScheme = darkColorScheme(
    primary = Primary,
    onPrimary = TextOnAccent,
    primaryContainer = PrimaryDark,
    onPrimaryContainer = TextPrimary,
    secondary = Accent,
    onSecondary = TextOnAccent,
    secondaryContainer = AccentDark,
    onSecondaryContainer = TextPrimary,
    tertiary = Info,
    onTertiary = TextOnAccent,
    tertiaryContainer = Info,
    onTertiaryContainer = TextPrimary,
    error = Error,
    errorContainer = Error,
    onError = TextOnAccent,
    onErrorContainer = TextPrimary,
    background = SurfaceLight,
    onBackground = TextPrimary,
    surface = SurfaceDark,
    onSurface = TextPrimary,
    surfaceVariant = SurfaceVariant,
    onSurfaceVariant = TextSecondary,
    outline = SurfaceBorder,
    inverseOnSurface = SurfaceLight,
    inverseSurface = TextPrimary,
    inversePrimary = AccentLight
)

@Composable
fun RmsScannerTheme(
    content: @Composable () -> Unit
) {
    MaterialTheme(
        colorScheme = RmsColorScheme,
        typography = RmsTypography,
        content = content
    )
}
