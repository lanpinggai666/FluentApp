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
import com.lanping.admin.model.CategoryItem
import com.lanping.admin.model.SoftwareItem
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun PendingScreen(
    viewModel: AdminViewModel,
    onBack: () -> Unit
) {
    val pendingList by viewModel.pendingList.collectAsState()
    val categoryList by viewModel.categoryList.collectAsState()
    val isLoading by viewModel.isLoadingPending.collectAsState()
    val pendingError by viewModel.pendingError.collectAsState()
    val actionId by viewModel.pendingActionId.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()

    var rejectTargetItem by remember { mutableStateOf<SoftwareItem?>(null) }
    var customRejectReason by remember { mutableStateOf("") }
    var categorySelectTargetItem by remember { mutableStateOf<SoftwareItem?>(null) }
    // Map to track modified category selections per software item before approval
    val selectedCategoryMap = remember { mutableStateMapOf<Int, CategoryItem>() }

    val view = LocalView.current

    LaunchedEffect(Unit) {
        viewModel.loadPendingList()
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
                    title = "待审软件 (${pendingList.size})",
                    onBack = onBack,
                    onAction = {
                        WatchHaptics.click(view)
                        viewModel.loadPendingList()
                    },
                    actionLabel = if (isLoading) "刷新中" else "刷新"
                )
            }

            if (pendingError != null) {
                item {
                    WatchErrorBanner(
                        message = pendingError ?: "网络错误",
                        onRetry = { viewModel.loadPendingList() },
                        cooldownSeconds = retryCooldown
                    )
                }
            }

            if (pendingList.isEmpty() && !isLoading && pendingError == null) {
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 32.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text("✓", fontSize = 28.sp, color = WatchSuccess)
                        Spacer(modifier = Modifier.height(4.dp))
                        Text("当前没有待审软件", fontSize = 13.sp, color = WatchTextSecondary)
                    }
                }
            }

            items(pendingList, key = { it.id }) { item ->
                val isProcessing = actionId == item.id
                val chosenCategory = selectedCategoryMap[item.id]
                val currentCategoryName = chosenCategory?.name ?: item.categoryName.ifBlank { "未分类" }
                val targetCategoryId = chosenCategory?.id ?: item.categoryId.takeIf { it > 0 }

                WatchCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = item.name,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        // Clickable Category Badge to change target category
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(WatchPrimaryContainer)
                                .clickable {
                                    WatchHaptics.click(view)
                                    categorySelectTargetItem = item
                                }
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text(
                                text = "$currentCategoryName ▾",
                                color = WatchOnPrimaryContainer,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }

                    if (item.officialUrl.isNotBlank()) {
                        Spacer(modifier = Modifier.height(2.dp))
                        Text(
                            text = item.officialUrl,
                            fontSize = 10.sp,
                            color = WatchPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    if (item.summary.isNotBlank()) {
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = item.summary,
                            fontSize = 11.sp,
                            color = WatchTextSecondary,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    // Moderation Action Row
                    if (isProcessing) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Center
                        ) {
                            Text("处理中...", fontSize = 12.sp, color = WatchWarning)
                        }
                    } else {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            // Approve Button
                            Button(
                                onClick = {
                                    WatchHaptics.click(view)
                                    if (targetCategoryId == null || targetCategoryId <= 0) {
                                        categorySelectTargetItem = item
                                        viewModel.showWarning("请先为该软件选择入库分类")
                                    } else {
                                        viewModel.reviewSoftware(
                                            id = item.id,
                                            action = "approve",
                                            categoryId = targetCategoryId
                                        )
                                    }
                                },
                                modifier = Modifier
                                    .weight(1f)
                                    .height(36.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = WatchSuccess,
                                    contentColor = WatchOnSuccess
                                ),
                                contentPadding = PaddingValues(0.dp)
                            ) {
                                Text("通过", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }

                            // AI Approve Button
                            Button(
                                onClick = {
                                    WatchHaptics.click(view)
                                    if (targetCategoryId == null || targetCategoryId <= 0) {
                                        categorySelectTargetItem = item
                                        viewModel.showWarning("请先为该软件选择入库分类")
                                    } else {
                                        viewModel.reviewSoftware(
                                            id = item.id,
                                            action = "approve_ai",
                                            categoryId = targetCategoryId
                                        )
                                    }
                                },
                                modifier = Modifier
                                    .weight(1f)
                                    .height(36.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = WatchPrimary,
                                    contentColor = WatchOnPrimary
                                ),
                                contentPadding = PaddingValues(0.dp)
                            ) {
                                Text("AI通过", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }

                            // Reject Button
                            Button(
                                onClick = {
                                    WatchHaptics.click(view)
                                    customRejectReason = ""
                                    rejectTargetItem = item
                                },
                                modifier = Modifier
                                    .weight(1f)
                                    .height(36.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = WatchError,
                                    contentColor = WatchOnError
                                ),
                                contentPadding = PaddingValues(0.dp)
                            ) {
                                Text("驳回", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }

        // Category Picker Dialog (手表快速分类选择器)
        if (categorySelectTargetItem != null) {
            val target = categorySelectTargetItem!!
            AlertDialog(
                onDismissRequest = { categorySelectTargetItem = null },
                title = {
                    Text(
                        text = "选择入库分类",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = WatchTextPrimary
                    )
                },
                text = {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(max = 200.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(
                            text = "目标: ${target.name}",
                            fontSize = 11.sp,
                            color = WatchTextMuted
                        )
                        if (categoryList.isEmpty()) {
                            Text("正在加载分类...", fontSize = 11.sp, color = WatchTextSecondary)
                            Spacer(modifier = Modifier.height(4.dp))
                            Button(
                                onClick = { viewModel.loadCategories() },
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = WatchPrimary),
                                modifier = Modifier.height(30.dp),
                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                            ) {
                                Text("重试加载", fontSize = 11.sp)
                            }
                        } else {
                            LazyColumn(
                                modifier = Modifier.fillMaxWidth(),
                                verticalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                items(categoryList, key = { it.id }) { cat ->
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(WatchCardBg)
                                            .clickable {
                                                WatchHaptics.click(view)
                                                selectedCategoryMap[target.id] = cat
                                                categorySelectTargetItem = null
                                            }
                                            .padding(horizontal = 8.dp, vertical = 6.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = cat.name,
                                                fontSize = 11.sp,
                                                color = WatchTextPrimary
                                            )
                                            Text(
                                                text = "${cat.softwareCount} 款",
                                                fontSize = 10.sp,
                                                color = WatchTextMuted
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                confirmButton = {},
                dismissButton = {
                    TextButton(onClick = { categorySelectTargetItem = null }) {
                        Text("取消", color = WatchTextMuted, fontSize = 11.sp)
                    }
                },
                containerColor = WatchSurface,
                shape = RoundedCornerShape(16.dp)
            )
        }

        // Canned Reject Reason Dialog (手表免打字/快捷驳回选择器)
        if (rejectTargetItem != null) {
            val target = rejectTargetItem!!
            val cannedReasons = listOf(
                "官网链接无法访问或已失效",
                "软件信息不完整或描述不实",
                "不符合本站收录标准",
                "包含违规、恶意风险或版权争议",
                "平台已存在相同软件收录"
            )

            AlertDialog(
                onDismissRequest = { rejectTargetItem = null },
                title = {
                    Text(
                        text = "选择驳回原因",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = WatchTextPrimary
                    )
                },
                text = {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(max = 210.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        item {
                            Text(
                                text = "目标: ${target.name}",
                                fontSize = 11.sp,
                                color = WatchTextMuted
                            )
                        }

                        items(cannedReasons) { reason ->
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(WatchCardBg)
                                    .clickable {
                                        WatchHaptics.click(view)
                                        viewModel.reviewSoftware(target.id, "reject", reason)
                                        rejectTargetItem = null
                                    }
                                    .padding(horizontal = 8.dp, vertical = 6.dp)
                            ) {
                                Text(
                                    text = reason,
                                    fontSize = 11.sp,
                                    color = WatchTextPrimary
                                )
                            }
                        }

                        // Custom reason input
                        item {
                            Spacer(modifier = Modifier.height(4.dp))
                            OutlinedTextField(
                                value = customRejectReason,
                                onValueChange = { customRejectReason = it },
                                placeholder = { Text("输入自定义理由...", fontSize = 10.sp, color = WatchTextMuted) },
                                singleLine = true,
                                textStyle = LocalTextStyle.current.copy(fontSize = 10.sp, color = WatchTextPrimary),
                                modifier = Modifier.fillMaxWidth().height(42.dp),
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = WatchPrimary,
                                    unfocusedBorderColor = WatchCardBorder,
                                    focusedContainerColor = WatchCardBg,
                                    unfocusedContainerColor = WatchCardBg
                                )
                            )
                            if (customRejectReason.isNotBlank()) {
                                Spacer(modifier = Modifier.height(4.dp))
                                Button(
                                    onClick = {
                                        WatchHaptics.click(view)
                                        viewModel.reviewSoftware(target.id, "reject", customRejectReason.trim())
                                        rejectTargetItem = null
                                    },
                                    modifier = Modifier.fillMaxWidth().height(32.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = WatchError)
                                ) {
                                    Text("使用自定义理由驳回", fontSize = 10.sp)
                                }
                            }
                        }
                    }
                },
                confirmButton = {},
                dismissButton = {
                    TextButton(onClick = { rejectTargetItem = null }) {
                        Text("取消", color = WatchTextMuted, fontSize = 11.sp)
                    }
                },
                containerColor = WatchSurface,
                shape = RoundedCornerShape(16.dp)
            )
        }
    }
}

@Preview(name = "Pending Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Pending Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun PendingScreenLayoutPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 28.dp, bottom = 52.dp, start = 14.dp, end = 14.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        WatchTopBar(title = "待审软件 (1)", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Notepad3", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = WatchTextPrimary)
                StatusBadge("实用工具", WatchPrimary)
            }
            Text("https://www.rizonesoft.com", fontSize = 10.sp, color = WatchPrimary)
            Text("轻量级开源文本编辑器", fontSize = 11.sp, color = WatchTextSecondary)
            Spacer(modifier = Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f).height(36.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = WatchSuccess)
                ) {
                    Text("通过", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f).height(36.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = WatchPrimary)
                ) {
                    Text("AI通过", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f).height(36.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = WatchError)
                ) {
                    Text("驳回", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}
