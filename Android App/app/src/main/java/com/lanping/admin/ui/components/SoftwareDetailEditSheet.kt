package com.lanping.admin.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.lanping.admin.model.CategoryItem
import com.lanping.admin.model.SoftwareDetailData
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun SoftwareDetailEditSheet(
    detail: SoftwareDetailData,
    categories: List<CategoryItem>,
    isSaving: Boolean,
    errorMessage: String? = null,
    onSave: (SoftwareDetailData) -> Unit,
    onDismiss: () -> Unit
) {
    val view = LocalView.current

    var name by remember { mutableStateOf(detail.name) }
    var status by remember { mutableStateOf(detail.status) }
    var selectedCategoryId by remember { mutableStateOf(detail.categoryId) }
    var officialUrl by remember { mutableStateOf(detail.officialUrl) }
    var githubUrl by remember { mutableStateOf(detail.githubUrl) }
    var summary by remember { mutableStateOf(detail.summary) }
    var specialNotes by remember { mutableStateOf(detail.specialNotes) }
    var tags by remember { mutableStateOf(detail.tags) }
    var developer by remember { mutableStateOf(detail.developer) }
    var isFeatured by remember { mutableStateOf(detail.isFeatured) }

    Dialog(
        onDismissRequest = {
            if (!isSaving) onDismiss()
        },
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(WatchBlack)
        ) {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(top = 26.dp, bottom = 48.dp, start = 14.dp, end = 14.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                item {
                    WatchTopBar(
                        title = "修改软件详情",
                        onBack = {
                            if (!isSaving) {
                                WatchHaptics.click(view)
                                onDismiss()
                            }
                        }
                    )
                }

                if (errorMessage != null) {
                    item {
                        WatchErrorBanner(
                            message = errorMessage,
                            onRetry = {}
                        )
                    }
                }

                // 软件名称
                item {
                    WatchCard {
                        Text("软件名称", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = name,
                            onValueChange = { name = it },
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(fontSize = 12.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )
                    }
                }

                // 状态单选 Chip
                item {
                    WatchCard {
                        Text("状态设置", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(4.dp))
                        val statusOptions = listOf(
                            "published" to ("上架" to WatchSuccess),
                            "offline" to ("下架" to WatchError),
                            "pending" to ("待审" to WatchWarning),
                            "rejected" to ("驳回" to WatchTextMuted)
                        )
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            statusOptions.forEach { (code, pair) ->
                                val (label, color) = pair
                                val isSelected = status == code
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(if (isSelected) color.copy(alpha = 0.25f) else WatchSurface)
                                        .border(
                                            width = 1.dp,
                                            color = if (isSelected) color else WatchCardBorder,
                                            shape = RoundedCornerShape(8.dp)
                                        )
                                        .clickable {
                                            WatchHaptics.click(view)
                                            status = code
                                        }
                                        .padding(horizontal = 8.dp, vertical = 6.dp)
                                ) {
                                    Text(
                                        text = label,
                                        fontSize = 11.sp,
                                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                        color = if (isSelected) color else WatchTextSecondary
                                    )
                                }
                            }
                        }
                    }
                }

                // 分类单选
                if (categories.isNotEmpty()) {
                    item {
                        WatchCard {
                            Text("所属分类", fontSize = 11.sp, color = WatchTextMuted)
                            Spacer(modifier = Modifier.height(4.dp))
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .horizontalScroll(rememberScrollState()),
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                categories.forEach { cat ->
                                    val isSelected = selectedCategoryId == cat.id
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (isSelected) WatchPrimaryContainer else WatchSurface)
                                            .border(
                                                width = 1.dp,
                                                color = if (isSelected) WatchPrimary else WatchCardBorder,
                                                shape = RoundedCornerShape(8.dp)
                                            )
                                            .clickable {
                                                WatchHaptics.click(view)
                                                selectedCategoryId = cat.id
                                            }
                                            .padding(horizontal = 8.dp, vertical = 6.dp)
                                    ) {
                                        Text(
                                            text = cat.name,
                                            fontSize = 11.sp,
                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                            color = if (isSelected) WatchOnPrimaryContainer else WatchTextSecondary
                                        )
                                    }
                                }
                            }
                        }
                    }
                }

                // 官方地址
                item {
                    WatchCard {
                        Text("官网链接", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = officialUrl,
                            onValueChange = { officialUrl = it },
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )
                    }
                }

                // 源码链接
                item {
                    WatchCard {
                        Text("GitHub/源码链接", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = githubUrl,
                            onValueChange = { githubUrl = it },
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )
                    }
                }

                // 一句话简介
                item {
                    WatchCard {
                        Text("一句话简介", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = summary,
                            onValueChange = { summary = it },
                            maxLines = 3,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(56.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )
                    }
                }

                // 特别说明
                item {
                    WatchCard {
                        Text("特别说明/提示", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = specialNotes,
                            onValueChange = { specialNotes = it },
                            maxLines = 3,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(56.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )
                    }
                }

                // 标签与开发者
                item {
                    WatchCard {
                        Text("标签 (英文/中文逗号隔开)", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = tags,
                            onValueChange = { tags = it },
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )

                        Spacer(modifier = Modifier.height(6.dp))
                        Text("开发者", fontSize = 11.sp, color = WatchTextMuted)
                        Spacer(modifier = Modifier.height(3.dp))
                        OutlinedTextField(
                            value = developer,
                            onValueChange = { developer = it },
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(fontSize = 11.sp, color = WatchTextPrimary),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = WatchPrimary,
                                unfocusedBorderColor = WatchCardBorder,
                                focusedContainerColor = WatchSurface,
                                unfocusedContainerColor = WatchSurface
                            )
                        )

                        Spacer(modifier = Modifier.height(8.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text("首页精选推荐", fontSize = 12.sp, color = WatchTextPrimary)
                            Switch(
                                checked = isFeatured,
                                onCheckedChange = {
                                    WatchHaptics.click(view)
                                    isFeatured = it
                                },
                                colors = SwitchDefaults.colors(
                                    checkedThumbColor = WatchPrimary,
                                    checkedTrackColor = WatchPrimaryContainer
                                )
                            )
                        }
                    }
                }

                // 底部操作按钮
                item {
                    Spacer(modifier = Modifier.height(4.dp))
                    Button(
                        onClick = {
                            WatchHaptics.click(view)
                            val updated = detail.copy(
                                name = name.trim(),
                                status = status,
                                categoryId = selectedCategoryId,
                                officialUrl = officialUrl.trim(),
                                githubUrl = githubUrl.trim(),
                                summary = summary.trim(),
                                specialNotes = specialNotes.trim(),
                                tags = tags.trim(),
                                developer = developer.trim(),
                                isFeatured = isFeatured
                            )
                            onSave(updated)
                        },
                        enabled = !isSaving && name.isNotBlank(),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(44.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = WatchPrimary,
                            contentColor = WatchOnPrimary
                        )
                    ) {
                        if (isSaving) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                color = WatchOnPrimary,
                                strokeWidth = 2.dp
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("保存中...", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        } else {
                            Text("保存修改", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    Spacer(modifier = Modifier.height(4.dp))
                    OutlinedButton(
                        onClick = {
                            WatchHaptics.click(view)
                            onDismiss()
                        },
                        enabled = !isSaving,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(40.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.outlinedButtonColors(
                            contentColor = WatchTextSecondary
                        )
                    ) {
                        Text("取消", fontSize = 12.sp)
                    }
                }
            }
        }
    }
}
