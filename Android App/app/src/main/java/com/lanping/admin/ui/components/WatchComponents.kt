package com.lanping.admin.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.tooling.preview.Preview
import com.lanping.admin.ui.theme.*
import com.lanping.admin.ui.util.WatchHaptics

@Composable
fun WatchTopBar(
    title: String,
    onBack: (() -> Unit)? = null,
    onAction: (() -> Unit)? = null,
    actionLabel: String? = null
) {
    val view = LocalView.current
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 8.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        if (onBack != null) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(WatchCardBg)
                    .clickable {
                        WatchHaptics.click(view)
                        onBack()
                    },
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "◀",
                    color = WatchPrimary,
                    fontSize = 14.sp
                )
            }
            Spacer(modifier = Modifier.width(6.dp))
        }

        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
            color = WatchTextPrimary,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f)
        )

        if (onAction != null && actionLabel != null) {
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(WatchPrimaryContainer)
                    .clickable {
                        WatchHaptics.click(view)
                        onAction()
                    }
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            ) {
                Text(
                    text = actionLabel,
                    color = WatchOnPrimaryContainer,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@Composable
fun WatchCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    border: BorderStroke? = BorderStroke(1.dp, WatchCardBorder),
    backgroundColor: Color = WatchCardBg,
    content: @Composable ColumnScope.() -> Unit
) {
    val view = LocalView.current
    Card(
        modifier = modifier
            .fillMaxWidth()
            .then(
                if (onClick != null) {
                    Modifier.clickable {
                        WatchHaptics.click(view)
                        onClick()
                    }
                } else Modifier
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = backgroundColor),
        border = border
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(10.dp),
            content = content
        )
    }
}

@Composable
fun StatusBadge(
    text: String,
    color: Color = WatchPrimary,
    bgColor: Color = color.copy(alpha = 0.2f)
) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(bgColor)
            .padding(horizontal = 6.dp, vertical = 2.dp)
    ) {
        Text(
            text = text,
            color = color,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium
        )
    }
}

@Composable
fun WatchButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    containerColor: Color = WatchPrimary,
    contentColor: Color = WatchOnPrimary,
    enabled: Boolean = true
) {
    val view = LocalView.current
    Button(
        onClick = {
            WatchHaptics.click(view)
            onClick()
        },
        modifier = modifier
            .fillMaxWidth()
            .height(44.dp),
        shape = RoundedCornerShape(22.dp),
        enabled = enabled,
        colors = ButtonDefaults.buttonColors(
            containerColor = containerColor,
            contentColor = contentColor,
            disabledContainerColor = WatchCardBorder,
            disabledContentColor = WatchTextMuted
        )
    ) {
        Text(
            text = text,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
fun WatchErrorBanner(
    message: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    cooldownSeconds: Int = 0
) {
    val view = LocalView.current
    WatchCard(
        modifier = modifier,
        border = BorderStroke(1.dp, WatchError.copy(alpha = 0.6f)),
        backgroundColor = WatchErrorContainer.copy(alpha = 0.45f)
    ) {
        Column(
            modifier = Modifier.fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "网络或接口异常",
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                color = WatchError
            )
            Spacer(modifier = Modifier.height(2.dp))
            Text(
                text = message,
                fontSize = 10.sp,
                color = WatchTextSecondary,
                textAlign = TextAlign.Center,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis
            )
            Spacer(modifier = Modifier.height(6.dp))
            Button(
                onClick = {
                    WatchHaptics.click(view)
                    onRetry()
                },
                enabled = cooldownSeconds <= 0,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = WatchError,
                    contentColor = WatchOnError,
                    disabledContainerColor = WatchCardBorder,
                    disabledContentColor = WatchTextMuted
                ),
                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 2.dp),
                modifier = Modifier.height(30.dp)
            ) {
                Text(
                    text = if (cooldownSeconds > 0) "重试 (${cooldownSeconds}s)" else "重试",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
fun WatchConfirmDialog(
    title: String,
    message: String,
    confirmText: String = "确认",
    cancelText: String = "取消",
    isDestructive: Boolean = false,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit
) {
    val view = LocalView.current
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                color = WatchTextPrimary,
                fontWeight = FontWeight.Bold,
                fontSize = 13.sp
            )
        },
        text = {
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = WatchTextSecondary,
                fontSize = 11.sp
            )
        },
        confirmButton = {
            Button(
                onClick = {
                    if (isDestructive) {
                        WatchHaptics.reject(view)
                    } else {
                        WatchHaptics.success(view)
                    }
                    onConfirm()
                },
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (isDestructive) WatchError else WatchPrimary,
                    contentColor = if (isDestructive) WatchOnError else WatchOnPrimary
                ),
                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                modifier = Modifier.height(32.dp)
            ) {
                Text(confirmText, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(
                onClick = {
                    WatchHaptics.click(view)
                    onDismiss()
                },
                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
                modifier = Modifier.height(32.dp)
            ) {
                Text(cancelText, color = WatchTextMuted, fontSize = 11.sp)
            }
        },
        containerColor = WatchSurface,
        shape = RoundedCornerShape(18.dp)
    )
}

@Preview(name = "Round Watch (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Square Watch (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun WatchComponentsPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        WatchTopBar(title = "清源管理", onBack = {}, onAction = {}, actionLabel = "刷新")
        WatchCard {
            Text("仪表盘卡片", color = WatchTextPrimary, fontSize = 12.sp)
            StatusBadge("正常运行", WatchSuccess)
        }
        WatchErrorBanner(message = "接口调用过于频繁，请 45 秒后再试", onRetry = {})
        WatchButton(text = "确认提交", onClick = {})
    }
}

