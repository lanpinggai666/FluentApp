package com.lanping.admin.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchButton
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchConfirmDialog
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun LogsAndSettingsScreen(
    viewModel: AdminViewModel,
    onBack: () -> Unit,
    onLoggedOut: () -> Unit
) {
    val prefs = viewModel.prefs
    val logList by viewModel.logList.collectAsState()
    val isLoading by viewModel.isLoadingLogs.collectAsState()
    val logsError by viewModel.logsError.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()
    val view = LocalView.current

    var showLogoutConfirm by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        viewModel.loadLogs()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(top = 28.dp, bottom = 52.dp, start = 14.dp, end = 14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            item {
                WatchTopBar(
                    title = "审计与设置",
                    onBack = onBack,
                    onAction = {
                        WatchHaptics.click(view)
                        viewModel.loadLogs()
                    },
                    actionLabel = if (isLoading) "刷新中" else "刷新"
                )
            }

            if (logsError != null) {
                item {
                    WatchErrorBanner(
                        message = logsError ?: "加载日志失败",
                        onRetry = { viewModel.loadLogs() },
                        cooldownSeconds = retryCooldown
                    )
                }
            }

            // Connection Profile Card
            item {
                WatchCard {
                    Text("当前接入凭据", fontSize = 11.sp, color = WatchTextMuted)
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = prefs.baseUrl,
                        fontSize = 11.sp,
                        color = WatchPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = "管理员: ${prefs.username}",
                        fontSize = 11.sp,
                        color = WatchTextSecondary
                    )
                    val maskedKey = if (prefs.apiKey.length > 10) {
                        prefs.apiKey.take(6) + "..." + prefs.apiKey.takeLast(4)
                    } else "●●●"
                    Text(
                        text = "密钥: $maskedKey",
                        fontSize = 10.sp,
                        color = WatchTextMuted
                    )

                    var queryRouting by remember { mutableStateOf(prefs.useQueryRouting) }
                    Text(
                        text = "路由格式: " + if (queryRouting) "查询串 (?r=api/v1/)" else "伪静态 (/api/v1/)",
                        fontSize = 10.sp,
                        color = WatchPrimary,
                        modifier = Modifier.clickable {
                            val next = !queryRouting
                            queryRouting = next
                            prefs.useQueryRouting = next
                            viewModel.showWarning(if (next) "已切换为查询串模式" else "已切换为伪静态模式")
                        }
                    )
                }
            }

            // Section Header
            item {
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = "最近接口调用 (${logList.size})",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = WatchTextPrimary
                )
            }

            items(logList, key = { it.id }) { log ->
                val isOk = log.httpCode in 200..299

                WatchCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "${log.method} ${log.endpoint}",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        StatusBadge(
                            text = "${log.httpCode}",
                            color = if (isOk) WatchSuccess else WatchError
                        )
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = log.ip.ifBlank { "内网" },
                            fontSize = 10.sp,
                            color = WatchTextMuted
                        )
                        Text(
                            text = log.createdAt.takeLast(8),
                            fontSize = 10.sp,
                            color = WatchTextMuted
                        )
                    }
                }
            }

            item {
                Spacer(modifier = Modifier.height(8.dp))
                WatchButton(
                    text = "退出登录与清空凭据",
                    containerColor = WatchErrorContainer,
                    contentColor = WatchError,
                    onClick = {
                        WatchHaptics.click(view)
                        showLogoutConfirm = true
                    }
                )
            }
        }

        if (showLogoutConfirm) {
            WatchConfirmDialog(
                title = "确认退出登录？",
                message = "将清空本地保存的 API 密钥与登录口令。",
                confirmText = "确认退出",
                isDestructive = true,
                onConfirm = {
                    viewModel.logout()
                    showLogoutConfirm = false
                    onLoggedOut()
                },
                onDismiss = { showLogoutConfirm = false }
            )
        }
    }
}

@Preview(name = "Logs Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Logs Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun LogsAndSettingsScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 26.dp, bottom = 48.dp, start = 12.dp, end = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        WatchTopBar(title = "审计与设置", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Text("当前接入凭据", fontSize = 11.sp, color = WatchTextMuted)
            Text("https://app.lanpinggai.top", fontSize = 11.sp, color = WatchPrimary)
            Text("管理员: admin", fontSize = 11.sp, color = WatchTextSecondary)
        }
    }
}
