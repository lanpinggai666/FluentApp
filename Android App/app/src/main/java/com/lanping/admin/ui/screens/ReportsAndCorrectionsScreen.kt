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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.SoftwareDetailEditSheet
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun ReportsAndCorrectionsScreen(
    viewModel: AdminViewModel,
    onBack: () -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: 举报, 1: 纠错

    val reportList by viewModel.reportList.collectAsState()
    val correctionList by viewModel.correctionList.collectAsState()
    val isLoading by viewModel.isLoadingReports.collectAsState()
    val reportsError by viewModel.reportsError.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()

    val editingDetail by viewModel.editingSoftwareDetail.collectAsState()
    val isSavingDetail by viewModel.isSavingSoftwareDetail.collectAsState()
    val detailError by viewModel.softwareDetailError.collectAsState()
    val categories by viewModel.categoryList.collectAsState()

    val view = LocalView.current

    LaunchedEffect(Unit) {
        viewModel.loadReportsAndCorrections()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(top = 28.dp, bottom = 52.dp, start = 14.dp, end = 14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
        item {
            WatchTopBar(
                title = "举报与纠错",
                onBack = onBack,
                onAction = {
                    WatchHaptics.click(view)
                    viewModel.loadReportsAndCorrections()
                },
                actionLabel = if (isLoading) "刷新中" else "刷新"
            )
        }

        if (reportsError != null) {
            item {
                WatchErrorBanner(
                    message = reportsError ?: "加载待办失败",
                    onRetry = { viewModel.loadReportsAndCorrections() },
                    cooldownSeconds = retryCooldown
                )
            }
        }

        // Tab Selector Pills
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(WatchCardBg)
                    .padding(2.dp)
            ) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(10.dp))
                        .background(if (selectedTab == 0) WatchPrimaryContainer else WatchCardBg)
                        .clickable {
                            WatchHaptics.click(view)
                            selectedTab = 0
                        }
                        .padding(vertical = 6.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "举报 (${reportList.size})",
                        fontSize = 11.sp,
                        fontWeight = if (selectedTab == 0) FontWeight.Bold else FontWeight.Normal,
                        color = if (selectedTab == 0) WatchOnPrimaryContainer else WatchTextSecondary
                    )
                }

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(10.dp))
                        .background(if (selectedTab == 1) WatchPrimaryContainer else WatchCardBg)
                        .clickable {
                            WatchHaptics.click(view)
                            selectedTab = 1
                        }
                        .padding(vertical = 6.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "纠错 (${correctionList.size})",
                        fontSize = 11.sp,
                        fontWeight = if (selectedTab == 1) FontWeight.Bold else FontWeight.Normal,
                        color = if (selectedTab == 1) WatchOnPrimaryContainer else WatchTextSecondary
                    )
                }
            }
        }

        if (selectedTab == 0) {
            // Reports List
            if (reportList.isEmpty() && !isLoading && reportsError == null) {
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text("✓", fontSize = 24.sp, color = WatchSuccess)
                        Text("暂无待处理举报", fontSize = 12.sp, color = WatchTextSecondary)
                    }
                }
            }

            items(reportList, key = { it.id }) { item ->
                WatchCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = item.softwareName,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        StatusBadge(text = item.type.ifBlank { "举报" }, color = WatchError)
                    }

                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = item.reason,
                        fontSize = 11.sp,
                        color = WatchTextSecondary,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )

                    Spacer(modifier = Modifier.height(6.dp))

                    if (item.softwareId > 0) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.openSoftwareDetailEditor(item.softwareId)
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(32.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchPrimaryContainer,
                                contentColor = WatchOnPrimaryContainer
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("修改关联软件详情", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                        Spacer(modifier = Modifier.height(6.dp))
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.handleReport(item.id, "resolved", "已处理并修复")
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchSuccess,
                                contentColor = WatchOnSuccess
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("已解决", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }

                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.handleReport(item.id, "rejected", "举报不实或已核验正常")
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchCardBorder,
                                contentColor = WatchTextSecondary
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("驳回", fontSize = 11.sp)
                        }
                    }
                }
            }
        } else {
            // Corrections List
            if (correctionList.isEmpty() && !isLoading && reportsError == null) {
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text("✓", fontSize = 24.sp, color = WatchSuccess)
                        Text("暂无待处理纠错", fontSize = 12.sp, color = WatchTextSecondary)
                    }
                }
            }

            items(correctionList, key = { it.id }) { item ->
                WatchCard {
                    Text(
                        text = item.softwareName,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = WatchTextPrimary
                    )

                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = "拟改项: ${item.fieldName} -> ${item.suggestedValue}",
                        fontSize = 11.sp,
                        color = WatchPrimary,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )

                    if (item.reason.isNotBlank()) {
                        Text(
                            text = "原因: ${item.reason}",
                            fontSize = 10.sp,
                            color = WatchTextSecondary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    Spacer(modifier = Modifier.height(6.dp))

                    if (item.softwareId > 0) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.openSoftwareDetailEditor(item.softwareId)
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(32.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchPrimaryContainer,
                                contentColor = WatchOnPrimaryContainer
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("直接修改该软件详情", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                        Spacer(modifier = Modifier.height(6.dp))
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.handleCorrection(item.id, "approve")
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchSuccess,
                                contentColor = WatchOnSuccess
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("采纳", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }

                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.handleCorrection(item.id, "reject", "资料已是最新")
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchCardBorder,
                                contentColor = WatchTextSecondary
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("驳回", fontSize = 11.sp)
                        }
                    }
                }
            }
        }
    }

    // Software Detail Editing Sheet
    if (editingDetail != null) {
        SoftwareDetailEditSheet(
            detail = editingDetail!!,
            categories = categories,
            isSaving = isSavingDetail,
            errorMessage = detailError,
            onSave = { updated -> viewModel.saveSoftwareDetail(updated) },
            onDismiss = { viewModel.closeSoftwareDetailEditor() }
        )
    }
}
}

@Preview(name = "Reports Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Reports Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun ReportsScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 26.dp, bottom = 48.dp, start = 12.dp, end = 12.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        WatchTopBar(title = "举报与纠错", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Text("VS Code", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = WatchTextPrimary)
            Text("链接已失效打不开", fontSize = 11.sp, color = WatchTextSecondary)
            Spacer(modifier = Modifier.height(6.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f).height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = WatchSuccess)
                ) {
                    Text("已解决", fontSize = 11.sp)
                }
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f).height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = WatchCardBorder)
                ) {
                    Text("驳回", fontSize = 11.sp)
                }
            }
        }
    }
}
