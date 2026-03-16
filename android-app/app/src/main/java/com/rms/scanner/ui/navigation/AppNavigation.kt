package com.rms.scanner.ui.navigation

import android.content.Context
import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import com.rms.scanner.data.repository.RmsRepository
import com.rms.scanner.rfid.RfidManager
import com.rms.scanner.ui.screens.CheckinScreen
import com.rms.scanner.ui.screens.CheckoutScreen
import com.rms.scanner.ui.screens.BoxScanScreen
import com.rms.scanner.ui.screens.CaseVerifyScreen
import com.rms.scanner.ui.screens.InventoryScreen
import com.rms.scanner.ui.screens.LocationScreen
import com.rms.scanner.ui.screens.LoginScreen
import com.rms.scanner.ui.screens.MainMenuScreen
import com.rms.scanner.ui.screens.SettingsScreen
import com.rms.scanner.ui.screens.TagWriteScreen
import com.rms.scanner.ui.screens.PackingListScreen
import com.rms.scanner.ui.screens.ExternalItemScreen
import com.rms.scanner.ui.viewmodels.BoxScanViewModel
import com.rms.scanner.ui.viewmodels.CaseVerifyViewModel
import com.rms.scanner.ui.viewmodels.InventoryViewModel
import com.rms.scanner.ui.viewmodels.LocationViewModel
import com.rms.scanner.ui.viewmodels.LoginViewModel
import com.rms.scanner.ui.viewmodels.ScanViewModel
import com.rms.scanner.ui.viewmodels.TagWriteViewModel
import com.rms.scanner.ui.viewmodels.PackingListViewModel
import com.rms.scanner.ui.viewmodels.ExternalItemViewModel

@Composable
fun AppNavigation(
    navController: NavHostController,
    context: Context,
    rfidManager: RfidManager,
    isLoggedIn: Boolean
) {
    val startDestination = if (isLoggedIn) "main_menu" else "login"

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        composable("login") {
            val viewModel = LoginViewModel(context)
            LoginScreen(
                viewModel = viewModel,
                onLoginSuccess = {
                    navController.navigate("main_menu") {
                        popUpTo("login") { inclusive = true }
                    }
                }
            )
        }

        composable("main_menu") {
            MainMenuScreen(
                onNavigate = { route ->
                    navController.navigate(route)
                }
            )
        }

        composable("checkout") {
            val viewModel = ScanViewModel(context, rfidManager)
            CheckoutScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("checkin") {
            val viewModel = ScanViewModel(context, rfidManager)
            CheckinScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("box_scan") {
            val viewModel = BoxScanViewModel(context, rfidManager)
            BoxScanScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("inventory") {
            val viewModel = InventoryViewModel(rfidManager)
            InventoryScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("tag_write") {
            val viewModel = TagWriteViewModel()
            TagWriteScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("settings") {
            SettingsScreen(
                context = context,
                onBack = { navController.popBackStack() }
            )
        }

        composable("relocate") {
            val repository = RmsRepository()
            val viewModel = LocationViewModel(repository, rfidManager)
            LocationScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("packing_list") {
            val viewModel = PackingListViewModel()
            PackingListScreen(
                viewModel = viewModel,
                rfidManager = rfidManager,
                onBack = { navController.popBackStack() }
            )
        }

        composable("external_items") {
            val viewModel = ExternalItemViewModel()
            ExternalItemScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }

        composable("case_verify/{caseAssetId}/{projectId}") { backStackEntry ->
            val caseAssetId = backStackEntry.arguments?.getString("caseAssetId")?.toIntOrNull() ?: 0
            val projectId = backStackEntry.arguments?.getString("projectId")?.toIntOrNull() ?: 0
            val viewModel = CaseVerifyViewModel(rfidManager)
            CaseVerifyScreen(
                viewModel = viewModel,
                caseAssetId = caseAssetId,
                projectId = projectId,
                rfidManager = rfidManager,
                onBack = { navController.popBackStack() },
                onSuccess = { navController.popBackStack() }
            )
        }
    }
}
