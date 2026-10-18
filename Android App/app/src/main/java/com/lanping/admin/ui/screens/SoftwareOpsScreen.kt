package com.lanping.admin.ui.screens

import androidx.compose.foundation.background
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
import com.lanping.admin.model.SoftwareItem
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.SoftwareDetailEditSheet
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchConfirmDialog
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun SoftwareOpsScreen(
    viewModel: AdminViewModel,
    onBack: () -> Unit
) {
    var searchQuery by remember { mutableStateOf("") }
    val softwareList by viewModel.softwareList.collectAsState()
    val isLoading by viewModel.isLoadingSoftwares.collectAsState()
    val softwaresError by viewModel.softwaresError.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()

    val editingDetail by viewModel.editingSoftwareDetail.collectAsState()
    val isSavingDetail by viewModel.isSavingSoftwareDetail.collectAsState()
    val detailError by viewModel.softwareDetailError.collectAsState()
    val categories by viewModel.categoryList.collectAsState()

    val view = LocalView.current

    var targetToToggle by remember { mutableStateOf<SoftwareItem?>(null) }

    LaunchedEffect(Unit) {
        viewModel.loadSoftwares()
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
                    title = "软件管控",
                    onBack = onBack,
                    onAction = {
                        WatchHaptics.click(view)
                        viewModel.loadSoftwares(searchQuery)
                    },
                    actionLabel = if (isLoading) "刷新中" else "刷新"
                )
            }

            if (softwaresError != null) {
                item {
                    WatchErrorBanner(
                        message = softwaresError ?: "加载软件失败",
                        onRetry = { viewModel.loadSoftwares(searchQuery) },
                        cooldownSeconds = retryCooldown
                    )
                }
            }

            // Compact search input
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    OutlinedTextField(
                        value = searchQuery,
                        onValueChange = {
                            searchQuery = it
                            viewModel.loadSoftwares(it)
                        },
                        placeholder = { Text("搜索名称/标签", fontSize = 11.sp, color = WatchTextMuted) },
                        singleLine = true,
                        textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(44.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = WatchPrimary,
                            unfocusedBorderColor = WatchCardBorder,
                            focusedContainerColor = WatchSurface,
                            unfocusedContainerColor = WatchSurface
                        )
                    )
                }
            }

            items(softwareList, key = { it.id }) { item ->
                val isPublished = item.status == "published"

                WatchCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = item.name,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        StatusBadge(
                            text = if (isPublished) "已上架" else "已下架",
                            color = if (isPublished) WatchSuccess else WatchError
                        )
                    }

                    if (item.summary.isNotBlank()) {
                        Spacer(modifier = Modifier.height(2.dp))
                        Text(
                            text = item.summary,
                            fontSize = 10.sp,
                            color = WatchTextMuted,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    Spacer(modifier = Modifier.height(6.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                viewModel.openSoftwareDetailEditor(item.id)
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchPrimaryContainer,
                                contentColor = WatchOnPrimaryContainer
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("修改详情", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }

                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                targetToToggle = item
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (isPublished) WatchErrorContainer else WatchSuccessContainer,
                                contentColor = if (isPublished) WatchError else WatchSuccess
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text(
                                text = if (isPublished) "应急下架" else "恢复上架",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
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

        // Confirmation Dialog to prevent accidental tap on watch
        if (targetToToggle != null) {
            val target = targetToToggle!!
            val isPublished = target.status == "published"
            WatchConfirmDialog(
                title = if (isPublished) "确认下架软件？" else "确认重新上架？",
                message = "即将把「${target.name}」状态改为 ${if (isPublished) "offline (下架)" else "published (上架)"}。",
                confirmText = if (isPublished) "确认下架" else "确认上架",
                isDestructive = isPublished,
                onConfirm = {
                    viewModel.toggleSoftwareStatus(target.id, target.status)
                    targetToToggle = null
                },
                onDismiss = { targetToToggle = null }
            )
        }
    }
}

@Preview(name = "Software Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Software Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun SoftwareOpsScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 26.dp, bottom = 48.dp, start = 12.dp, end = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        WatchTopBar(title = "软件管控", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Git", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = WatchTextPrimary)
                StatusBadge("已上架", WatchSuccess)
            }
            Text("分布式版本控制系统", fontSize = 10.sp, color = WatchTextMuted)
            Spacer(modifier = Modifier.height(6.dp))
            Button(
                onClick = {},
                modifier = Modifier.fillMaxWidth().height(34.dp),
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = WatchErrorContainer, contentColor = WatchError)
            ) {
                Text("应急下架", fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
