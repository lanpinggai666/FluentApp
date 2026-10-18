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
import com.lanping.admin.model.UserItem
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.StatusBadge
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.components.WatchConfirmDialog
import com.lanping.admin.ui.components.WatchErrorBanner
import com.lanping.admin.ui.components.WatchTopBar
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun UserOpsScreen(
    viewModel: AdminViewModel,
    onBack: () -> Unit
) {
    var searchQuery by remember { mutableStateOf("") }
    val userList by viewModel.userList.collectAsState()
    val isLoading by viewModel.isLoadingUsers.collectAsState()
    val usersError by viewModel.usersError.collectAsState()
    val retryCooldown by viewModel.retryCooldownSeconds.collectAsState()
    val view = LocalView.current

    var targetToPunish by remember { mutableStateOf<UserItem?>(null) }
    var targetToUnpunish by remember { mutableStateOf<UserItem?>(null) }

    LaunchedEffect(Unit) {
        viewModel.loadUsers()
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
                    title = "用户管控",
                    onBack = onBack,
                    onAction = {
                        WatchHaptics.click(view)
                        viewModel.loadUsers(searchQuery)
                    },
                    actionLabel = if (isLoading) "刷新中" else "刷新"
                )
            }

            if (usersError != null) {
                item {
                    WatchErrorBanner(
                        message = usersError ?: "加载用户失败",
                        onRetry = { viewModel.loadUsers(searchQuery) },
                        cooldownSeconds = retryCooldown
                    )
                }
            }

            item {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = {
                        searchQuery = it
                        viewModel.loadUsers(it)
                    },
                    placeholder = { Text("搜索用户名/邮箱", fontSize = 11.sp, color = WatchTextMuted) },
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

            items(userList, key = { it.id }) { item ->
                WatchCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = item.username,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = WatchTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        StatusBadge(
                            text = if (item.isBanned) "已封禁" else "正常",
                            color = if (item.isBanned) WatchError else WatchSuccess
                        )
                    }

                    if (item.email.isNotBlank()) {
                        Text(
                            text = item.email,
                            fontSize = 10.sp,
                            color = WatchTextMuted,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    if (item.banReason != null && item.banReason.isNotBlank()) {
                        Text(
                            text = "原因: ${item.banReason}",
                            fontSize = 10.sp,
                            color = WatchError,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }

                    Spacer(modifier = Modifier.height(6.dp))

                    if (item.isBanned) {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                targetToUnpunish = item
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchSuccessContainer,
                                contentColor = WatchSuccess
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("解除封禁", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    } else {
                        Button(
                            onClick = {
                                WatchHaptics.click(view)
                                targetToPunish = item
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(34.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = WatchErrorContainer,
                                contentColor = WatchError
                            ),
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("处置封禁", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }

        // Punish confirmation dialog
        if (targetToPunish != null) {
            val target = targetToPunish!!
            WatchConfirmDialog(
                title = "确认封禁用户？",
                message = "即将永久封禁「${target.username}」的发言与提交权力。",
                confirmText = "确认封禁",
                isDestructive = true,
                onConfirm = {
                    viewModel.punishUser(target.id, permanent = true, reason = "手表端违规处置")
                    targetToPunish = null
                },
                onDismiss = { targetToPunish = null }
            )
        }

        // Unpunish confirmation dialog
        if (targetToUnpunish != null) {
            val target = targetToUnpunish!!
            WatchConfirmDialog(
                title = "确认解封用户？",
                message = "将恢复「${target.username}」的全部账号权限。",
                confirmText = "确认解封",
                isDestructive = false,
                onConfirm = {
                    viewModel.unpunishUser(target.id)
                    targetToUnpunish = null
                },
                onDismiss = { targetToUnpunish = null }
            )
        }
    }
}

@Preview(name = "Users Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Users Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun UserOpsScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 26.dp, bottom = 48.dp, start = 12.dp, end = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        WatchTopBar(title = "用户管控", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("user123", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = WatchTextPrimary)
                StatusBadge("正常", WatchSuccess)
            }
            Text("user123@example.com", fontSize = 10.sp, color = WatchTextMuted)
            Spacer(modifier = Modifier.height(6.dp))
            Button(
                onClick = {},
                modifier = Modifier.fillMaxWidth().height(34.dp),
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = WatchErrorContainer, contentColor = WatchError)
            ) {
                Text("处置封禁", fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
