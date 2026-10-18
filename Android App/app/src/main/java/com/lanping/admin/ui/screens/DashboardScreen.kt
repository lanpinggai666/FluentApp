package com.lanping.admin.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun DashboardScreen(
    viewModel: AdminViewModel,
    onNavigatePending: () -> Unit,
    onNavigateReports: () -> Unit,
    onNavigateSoftware: () -> Unit,
    onNavigateUsers: () -> Unit,
    onNavigateLogs: () -> Unit
) {
    val overview by viewModel.overview.collectAsState()
    val isLoading by viewModel.isLoadingOverview.collectAsState()
    val overviewError by viewModel.overviewError.collectAsState()
    val pingResult by viewModel.pingResult.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()
    val view = LocalView.current

    LaunchedEffect(Unit) {
        viewModel.loadOverview()
    }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack),
        contentPadding = PaddingValues(top = 28.dp, bottom = 52.dp, start = 14.dp, end = 14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        item {
            WatchTopBar(
                title = pingResult?.siteName?.takeIf { it.isNotBlank() } ?: "清源管理",
                onAction = {
                    WatchHaptics.click(view)
                    viewModel.loadOverview()
                },
                actionLabel = if (isLoading) "刷新中" else "刷新"
            )
        }

        if (overviewError != null) {
            item {
                WatchErrorBanner(
                    message = overviewError ?: "获取概览失败",
                    onRetry = { viewModel.loadOverview() },
                    cooldownSeconds = retryCooldown
                )
            }
        }

        // Summary Metric Highlight
        item {
            val pendingCount = overview?.softwarePending ?: 0
            val reportCount = overview?.reportPending ?: 0
            val correctionCount = overview?.correctionPending ?: 0
            val totalPending = pendingCount + reportCount + correctionCount

            WatchCard(
                backgroundColor = if (totalPending > 0) WatchPrimaryContainer else WatchCardBg
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "待处理事项",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                        Text(
                            text = "$totalPending 项待办",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (totalPending > 0) WatchWarning else WatchSuccess
                        )
                    }

                    StatusBadge(
                        text = if (totalPending > 0) "需处理" else "全清空",
                        color = if (totalPending > 0) WatchWarning else WatchSuccess
                    )
                }

                Spacer(modifier = Modifier.height(8.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("软件: $pendingCount", fontSize = 11.sp, color = WatchTextSecondary)
                    Text("举报: $reportCount", fontSize = 11.sp, color = WatchTextSecondary)
                    Text("纠错: $correctionCount", fontSize = 11.sp, color = WatchTextSecondary)
                }
            }
        }

        // Action 1: Pending Software Reviews
        item {
            val pendingCount = overview?.softwarePending ?: 0
            WatchCard(
                onClick = {
                    WatchHaptics.click(view)
                    onNavigatePending()
                }
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "软件审核",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary
                        )
                        Text(
                            text = "提交入库与快速批复",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                    }
                    if (pendingCount > 0) {
                        StatusBadge(
                            text = "$pendingCount 待审",
                            color = WatchWarning
                        )
                    } else {
                        Text("▶", fontSize = 13.sp, color = WatchTextMuted)
                    }
                }
            }
        }

        // Action 2: Reports & Corrections
        item {
            val reportsCount = (overview?.reportPending ?: 0) + (overview?.correctionPending ?: 0)
            WatchCard(
                onClick = {
                    WatchHaptics.click(view)
                    onNavigateReports()
                }
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "举报与纠错",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary
                        )
                        Text(
                            text = "链接失效与违规处置",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                    }
                    if (reportsCount > 0) {
                        StatusBadge(
                            text = "$reportsCount 条",
                            color = WatchError
                        )
                    } else {
                        Text("▶", fontSize = 13.sp, color = WatchTextMuted)
                    }
                }
            }
        }

        // Action 3: Software Management (Emergency Switch)
        item {
            WatchCard(
                onClick = {
                    WatchHaptics.click(view)
                    onNavigateSoftware()
                }
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "软件管控",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary
                        )
                        Text(
                            text = "共 ${overview?.softwareTotal ?: 0} 款 (已发 ${overview?.softwarePublished ?: 0})",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                    }
                    Text("▶", fontSize = 13.sp, color = WatchTextMuted)
                }
            }
        }

        // Action 4: User Moderation
        item {
            WatchCard(
                onClick = {
                    WatchHaptics.click(view)
                    onNavigateUsers()
                }
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "用户管理",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary
                        )
                        Text(
                            text = "全站 ${overview?.userTotal ?: 0} 名用户",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                    }
                    Text("▶", fontSize = 13.sp, color = WatchTextMuted)
                }
            }
        }

        // Action 5: Logs & System
        item {
            WatchCard(
                onClick = {
                    WatchHaptics.click(view)
                    onNavigateLogs()
                }
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "日志与设置",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary
                        )
                        Text(
                            text = "调用量: ${overview?.apiCallsTotal ?: 0} / 拦截: ${overview?.apiCallsDenied ?: 0}",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                    }
                    Text("⚙", fontSize = 14.sp, color = WatchTextMuted)
                }
            }
        }
    }
}

@Preview(name = "Dashboard Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Dashboard Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun DashboardScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 26.dp, bottom = 48.dp, start = 12.dp, end = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        WatchTopBar(title = "清源导航管理", onAction = {}, actionLabel = "刷新")
        WatchCard(backgroundColor = WatchPrimaryContainer) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("待处理事项", fontSize = 11.sp, color = WatchTextMuted)
                    Text("3 项待办", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = WatchWarning)
                }
                StatusBadge("需处理", WatchWarning)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("软件: 2", fontSize = 11.sp, color = WatchTextSecondary)
                Text("举报: 1", fontSize = 11.sp, color = WatchTextSecondary)
                Text("纠错: 0", fontSize = 11.sp, color = WatchTextSecondary)
            }
        }
    }
}
