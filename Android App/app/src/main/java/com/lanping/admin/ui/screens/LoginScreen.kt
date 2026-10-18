package com.lanping.admin.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.lanping.admin.ui.AdminViewModel
import com.lanping.admin.ui.components.WatchButton
import com.lanping.admin.ui.components.WatchCard
import com.lanping.admin.ui.theme.*

@Composable
fun LoginScreen(
    viewModel: AdminViewModel,
    onSuccess: () -> Unit
) {
    val prefs = viewModel.prefs
    var url by remember { mutableStateOf(prefs.baseUrl) }
    var apiKey by remember { mutableStateOf(prefs.apiKey) }
    var username by remember { mutableStateOf(prefs.username) }
    var password by remember { mutableStateOf(prefs.password) }
    var useQueryRouting by remember { mutableStateOf(prefs.useQueryRouting) }

    val isTesting by viewModel.isTestingPing.collectAsState()
    val pingError by viewModel.pingError.collectAsState()
    val pingResult by viewModel.pingResult.collectAsState()
    val isLoggedIn by viewModel.isLoggedIn.collectAsState()

    LaunchedEffect(isLoggedIn) {
        if (isLoggedIn && pingResult != null) {
            onSuccess()
        }
    }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack),
        contentPadding = PaddingValues(top = 28.dp, bottom = 48.dp, start = 14.dp, end = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        item {
            Text(
                text = "清源导航管理",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = WatchPrimary,
                textAlign = TextAlign.Center
            )
            Text(
                text = "手表后台凭据接入",
                fontSize = 11.sp,
                color = WatchTextMuted,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(4.dp))
        }

        item {
            WatchCard {
                Text("接口地址", fontSize = 11.sp, color = WatchTextMuted)
                OutlinedTextField(
                    value = url,
                    onValueChange = { url = it },
                    singleLine = true,
                    textStyle = LocalTextStyle.current.copy(fontSize = 12.sp, color = WatchTextPrimary),
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = WatchPrimary,
                        unfocusedBorderColor = WatchCardBorder,
                        focusedContainerColor = WatchSurface,
                        unfocusedContainerColor = WatchSurface
                    )
                )

                Spacer(modifier = Modifier.height(6.dp))

                Text("API 密钥 (nav_...)", fontSize = 11.sp, color = WatchTextMuted)
                OutlinedTextField(
                    value = apiKey,
                    onValueChange = { apiKey = it },
                    singleLine = true,
                    textStyle = LocalTextStyle.current.copy(fontSize = 12.sp, color = WatchTextPrimary),
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = WatchPrimary,
                        unfocusedBorderColor = WatchCardBorder,
                        focusedContainerColor = WatchSurface,
                        unfocusedContainerColor = WatchSurface
                    )
                )

                Spacer(modifier = Modifier.height(8.dp))

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .background(WatchSurface)
                        .clickable { useQueryRouting = !useQueryRouting }
                        .padding(horizontal = 8.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("路由格式", fontSize = 10.sp, color = WatchTextMuted)
                    Text(
                        text = if (useQueryRouting) "兼容查询串 (?r=)" else "伪静态 (/api/v1/)",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = WatchPrimary
                    )
                }
            }
        }

        item {
            WatchCard {
                Text("管理员账号", fontSize = 11.sp, color = WatchTextMuted)
                OutlinedTextField(
                    value = username,
                    onValueChange = { username = it },
                    singleLine = true,
                    textStyle = LocalTextStyle.current.copy(fontSize = 12.sp, color = WatchTextPrimary),
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = WatchPrimary,
                        unfocusedBorderColor = WatchCardBorder,
                        focusedContainerColor = WatchSurface,
                        unfocusedContainerColor = WatchSurface
                    )
                )

                Spacer(modifier = Modifier.height(6.dp))

                Text("管理员口令", fontSize = 11.sp, color = WatchTextMuted)
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    textStyle = LocalTextStyle.current.copy(fontSize = 12.sp, color = WatchTextPrimary),
                    modifier = Modifier.fillMaxWidth(),
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

        if (pingError != null) {
            item {
                Text(
                    text = pingError ?: "",
                    color = WatchError,
                    fontSize = 12.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(horizontal = 8.dp)
                )
            }
        }

        item {
            WatchButton(
                text = if (isTesting) "正在验证..." else "连接测试并登录",
                enabled = !isTesting && apiKey.isNotBlank() && username.isNotBlank(),
                onClick = {
                    viewModel.testConnection(url, apiKey, username, password, useQueryRouting)
                }
            )
        }
    }
}

@Preview(name = "Login Round (400x400)", widthDp = 400, heightDp = 400, showBackground = true, backgroundColor = 0xFF000000)
@Preview(name = "Login Square (320x320)", widthDp = 320, heightDp = 320, showBackground = true, backgroundColor = 0xFF000000)
@Composable
fun LoginScreenPreview() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(WatchBlack)
            .padding(top = 28.dp, bottom = 48.dp, start = 14.dp, end = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Text("清源导航管理", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = WatchPrimary)
        Text("手表后台凭据接入", fontSize = 11.sp, color = WatchTextMuted)
        WatchCard {
            Text("接口地址", fontSize = 11.sp, color = WatchTextMuted)
            Text("https://app.lanpinggai.top", fontSize = 12.sp, color = WatchTextPrimary)
        }
        WatchButton(text = "连接测试并登录", onClick = {})
    }
}
