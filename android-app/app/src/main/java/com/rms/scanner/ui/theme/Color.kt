package com.rms.scanner.ui.theme

import androidx.compose.ui.graphics.Color

// ═══════════════════════════════════════════════════════
// RMS Scanner — Color Palette
// Matches AdminLTE 3 "sidebar-dark-pink" theme
// ═══════════════════════════════════════════════════════

// Primary — AdminLTE Bootstrap blue (#007bff)
val Primary = Color(0xFF007BFF)
val PrimaryDark = Color(0xFF0056B3)
val PrimaryLight = Color(0xFF3395FF)

// Accent — AdminLTE Pink (#e83e8c) — sidebar active, highlights
val Accent = Color(0xFFE83E8C)
val AccentLight = Color(0xFFF998B1)
val AccentDark = Color(0xFFBF2D6F)

// Secondary — AdminLTE Gray (#6c757d)
val Secondary = Color(0xFF6C757D)
val SecondaryDark = Color(0xFF545B62)
val SecondaryLight = Color(0xFF868E96)

// Status Colors — AdminLTE Bootstrap palette
val Success = Color(0xFF28A745)    // --success
val Warning = Color(0xFFFFC107)    // --warning
val Error = Color(0xFFDC3545)      // --danger
val Info = Color(0xFF17A2B8)       // --info

// Semantic Colors for Actions (each screen gets a distinct color)
val Checkout = Color(0xFF007BFF)    // Blue (Primary)
val Checkin = Color(0xFF28A745)     // Green (Success)
val BoxScan = Color(0xFFFD7E14)     // Orange (Bootstrap orange)
val Inventory = Color(0xFF6F42C1)   // Purple (Bootstrap purple)
val TagPair = Color(0xFFE83E8C)     // Pink (Accent)
val Packing = Color(0xFF17A2B8)     // Cyan (Info)
val External = Color(0xFFDC3545)    // Red (Danger)
val Relocate = Color(0xFF20C997)    // Teal (Bootstrap teal)
val SettingsColor = Color(0xFF6C757D) // Gray (Secondary)

// Background Colors — AdminLTE dark mode (#343a40)
val SurfaceDark = Color(0xFF343A40)      // --dark, sidebar bg, card bg
val SurfaceLight = Color(0xFF2C3038)     // slightly darker for main bg
val SurfaceVariant = Color(0xFF3E444A)   // elevated surfaces
val SurfaceCard = Color(0xFF3A4048)      // card backgrounds
val SurfaceBorder = Color(0xFF4B545C)    // subtle borders

// Text Colors — AdminLTE dark mode text
val TextPrimary = Color(0xFFF8F9FA)      // --light, main text
val TextSecondary = Color(0xFFC2C7D0)    // sidebar text, muted
val TextTertiary = Color(0xFF8A9099)     // very muted / placeholder
val TextOnAccent = Color(0xFFFFFFFF)     // white on pink/blue buttons

// Entity Type Colors
val AssetColor = Color(0xFF6F42C1)       // Purple
val StockColor = Color(0xFF28A745)       // Green
val UnknownColor = Color(0xFFDC3545)     // Red
