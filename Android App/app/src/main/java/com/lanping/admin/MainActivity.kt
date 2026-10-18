package com.lanping.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.HapticFeedbackType
import com.lanping.admin.ui.screens.*
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics
import kotlinx.coroutines.delay

enum class WatchScreen {
    LOGIN,
    DASHBOARD,
    PENDING,
    REPORTS,
    SOFTWARE,
    USERS,
    LOGS
}

class MainActivity : ComponentActivity() {

    private val viewModel: AdminViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            清源导航网管理Theme {
                WatchAdminApp(viewModel = viewModel)
            }
        }
    }
}

@Composable
fun WatchAdminApp(viewModel: AdminViewModel) {
    val isLoggedIn by viewModel.isLoggedIn.collectAsState()
    var currentScreen by remember {
        mutableStateOf(if (isLoggedIn) WatchScreen.DASHBOARD else WatchScreen.LOGIN)
    }

    LaunchedEffect(isLoggedIn) {
        if (!isLoggedIn) {
            currentScreen = WatchScreen.LOGIN
        }
    }

    val message by viewModel.message.collectAsState()
    val view = LocalView.current
    val context = LocalContext.current

    // Collect haptic feedback events triggered by ViewModel
    LaunchedEffect(Unit) {
        viewModel.hapticEvent.collect { type ->
            when (type) {
                HapticFeedbackType.CLICK -> WatchHaptics.click(view, context)
                HapticFeedbackType.SUCCESS -> WatchHaptics.success(view, context)
                HapticFeedbackType.REJECT -> WatchHaptics.reject(view, context)
                HapticFeedbackType.ERROR -> WatchHaptics.error(view, context)
            }
        }
    }

    // Screen Router
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
    ) {
        when (currentScreen) {
            WatchScreen.LOGIN -> {
                LoginScreen(
                    viewModel = viewModel,
                    onSuccess = { currentScreen = WatchScreen.DASHBOARD }
                )
            }
            WatchScreen.DASHBOARD -> {
                DashboardScreen(
                    viewModel = viewModel,
                    onNavigatePending = { currentScreen = WatchScreen.PENDING },
                    onNavigateReports = { currentScreen = WatchScreen.REPORTS },
                    onNavigateSoftware = { currentScreen = WatchScreen.SOFTWARE },
                    onNavigateUsers = { currentScreen = WatchScreen.USERS },
                    onNavigateLogs = { currentScreen = WatchScreen.LOGS }
                )
            }
            WatchScreen.PENDING -> {
                PendingScreen(
                    viewModel = viewModel,
                    onBack = { currentScreen = WatchScreen.DASHBOARD }
                )
            }
            WatchScreen.REPORTS -> {
                ReportsAndCorrectionsScreen(
                    viewModel = viewModel,
                    onBack = { currentScreen = WatchScreen.DASHBOARD }
                )
            }
            WatchScreen.SOFTWARE -> {
                SoftwareOpsScreen(
                    viewModel = viewModel,
                    onBack = { currentScreen = WatchScreen.DASHBOARD }
                )
            }
            WatchScreen.USERS -> {
                UserOpsScreen(
                    viewModel = viewModel,
                    onBack = { currentScreen = WatchScreen.DASHBOARD }
                )
            }
            WatchScreen.LOGS -> {
                LogsAndSettingsScreen(
                    viewModel = viewModel,
                    onBack = { currentScreen = WatchScreen.DASHBOARD },
                    onLoggedOut = { currentScreen = WatchScreen.LOGIN }
                )
            }
        }

        // Floating Toast Message for Watch
        message?.let { text ->
            LaunchedEffect(text) {
                delay(2400)
                viewModel.clearMessage()
            }

            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(bottom = 20.dp),
                contentAlignment = Alignment.BottomCenter
            ) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(16.dp))
                        .background(WatchPrimaryContainer)
                        .padding(horizontal = 14.dp, vertical = 8.dp)
                ) {
                    Text(
                        text = text,
                        color = WatchOnPrimaryContainer,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

@Preview(name = "App Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "App Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun WatchAdminAppPreview() {
    清源导航网管理Theme {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(WatchBlack)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(top = 28.dp, bottom = 48.dp, start = 14.dp, end = 14.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text("清源导航管理", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = WatchPrimary)
                Text("系统就绪，待命运行中", fontSize = 11.sp, color = WatchTextSecondary)
            }
        }
    }
}