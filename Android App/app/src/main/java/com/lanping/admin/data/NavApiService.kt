package com.lanping.admin.data

import com.lanping.admin.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.util.concurrent.TimeUnit

class NavApiService(private val prefs: AdminCredentialsProvider) {

    private val client: OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .writeTimeout(20, TimeUnit.SECONDS)
        .build()

    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    internal fun buildUrl(action: String, queryParams: Map<String, String> = emptyMap()): String {
        val base = prefs.baseUrl
        val sb = StringBuilder(base)
        // 遵循 API.md 2.0: 默认使用最干净的伪静态路径 /api/v1/<action>，未配重写时使用查询串
        if (prefs.useQueryRouting) {
            sb.append("/index.php?r=api/v1/").append(action)
        } else {
            sb.append("/api/v1/").append(action)
        }
        val sensitiveKeys = setOf("api_key", "key", "password", "token", "secret", "admin_password")
        var hasQuery = sb.contains('?')
        for ((k, v) in queryParams) {
            if (k.lowercase() in sensitiveKeys) {
                // Defense-in-depth: Never append sensitive credentials to query string
                continue
            }
            if (v.isNotBlank()) {
                sb.append(if (hasQuery) "&" else "?")
                    .append(k).append("=")
                    .append(java.net.URLEncoder.encode(v, "UTF-8"))
                hasQuery = true
            }
        }
        return sb.toString()
    }

    internal fun newRequestBuilder(url: String): Request.Builder {
        return Request.Builder()
            .url(url)
            .header("X-Api-Key", prefs.apiKey)
            .header("X-Admin-Username", prefs.username)
            .header("X-Admin-Password", prefs.password)
            .header("Accept", "application/json")
    }

    private suspend fun executeRequest(request: Request): JSONObject = withContext(Dispatchers.IO) {
        try {
            val response = client.newCall(request).execute()
            val httpCode = response.code
            val retryAfterHeader = response.header("Retry-After")?.toIntOrNull() ?: 0
            val bodyString = response.body?.string() ?: ""

            if (bodyString.isBlank()) {
                if (httpCode == 429) {
                    throw ApiException(
                        "rate_limited",
                        if (retryAfterHeader > 0) "接口调用过于频繁，请 $retryAfterHeader 秒后再试" else "接口调用过于频繁，请稍候再试",
                        retryAfterHeader
                    )
                }
                throw IOException("服务端未返回内容 (HTTP $httpCode)")
            }

            val json = try {
                JSONObject(bodyString)
            } catch (e: Exception) {
                if (httpCode == 429) {
                    throw ApiException(
                        "rate_limited",
                        if (retryAfterHeader > 0) "接口调用过于频繁，请 $retryAfterHeader 秒后再试" else "接口调用过于频繁，请稍候再试",
                        retryAfterHeader
                    )
                } else if (httpCode == 503) {
                    throw ApiException("api_disabled", "站点服务暂时不可用或已关闭开放 API (HTTP 503)")
                }
                throw IOException("响应格式解析失败 (HTTP $httpCode)")
            }

            // Fallback retry_after from HTTP response header if JSON error didn't specify it
            if (!json.optBoolean("success", false)) {
                val errObj = json.optJSONObject("error")
                if (errObj != null && errObj.optInt("retry_after", 0) <= 0 && retryAfterHeader > 0) {
                    errObj.put("retry_after", retryAfterHeader)
                }
            }

            json
        } catch (e: java.net.SocketTimeoutException) {
            throw IOException("请求超时，请检查手表网络或蓝牙连接", e)
        } catch (e: java.net.UnknownHostException) {
            throw IOException("无法解析服务器地址，请检查接口配置", e)
        } catch (e: java.net.ConnectException) {
            throw IOException("无法连接到服务器，请检查服务端是否正常", e)
        } catch (e: ApiException) {
            throw e
        } catch (e: IOException) {
            throw e
        } catch (e: Exception) {
            throw IOException(e.message ?: "未知网络错误", e)
        }
    }

    fun checkError(json: JSONObject) {
        if (!json.optBoolean("success", false)) {
            val errObj = json.optJSONObject("error")
            val code = errObj?.optString("code") ?: "unknown_error"
            val rawMsg = errObj?.optString("message") ?: "操作失败"
            val retryAfter = errObj?.optInt("retry_after", 0) ?: 0
            val friendlyMsg = mapErrorCode(code, rawMsg, retryAfter)
            throw ApiException(code, friendlyMsg, retryAfter)
        }
    }

    companion object {
        fun mapErrorCode(code: String, rawMsg: String = "", retryAfter: Int = 0): String {
            return when (code) {
                "missing_key" -> "缺少 API 密钥"
                "key_invalid" -> "API 密钥不存在或已失效"
                "key_revoked" -> "API 密钥已在后台被吊销"
                "key_in_query" -> "安全策略限制：密钥不能置于网址中"
                "missing_credentials" -> "缺少管理员用户名或口令"
                "invalid_credentials" -> "管理员用户名或口令不正确"
                "auth_throttled" -> if (retryAfter > 0) "凭据错误过多，请 $retryAfter 秒后再试" else "凭据错误过多，请稍后再试"
                "insufficient_scope" -> "当前密钥权限不足，需要相应读/写权限"
                "rate_limited" -> if (retryAfter > 0) "接口调用过于频繁，请 $retryAfter 秒后再试" else "接口调用过于频繁，请稍候再试"
                "insecure_transport" -> "必须使用 HTTPS 安全传输"
                "api_disabled" -> "站点后台已关闭开放 API"
                "invalid_param" -> rawMsg.ifBlank { "缺少或非法的请求参数" }
                "method_not_allowed" -> "接口请求方法错误"
                "confirm_required" -> "敏感操作需要确认"
                "not_found" -> "目标条目不存在"
                "unknown_endpoint" -> "未找到该接口端点"
                "invalid_software" -> rawMsg.ifBlank { "软件数据校验未通过" }
                "invalid_mirror" -> rawMsg.ifBlank { "镜像源配置校验未通过" }
                "invalid_notice" -> rawMsg.ifBlank { "公告内容校验未通过" }
                "invalid_base_url" -> rawMsg.ifBlank { "站点地址格式校验未通过" }
                "mirror_not_learned" -> rawMsg.ifBlank { "镜像模板自动学习失败" }
                "mirror_mismatch" -> rawMsg.ifBlank { "镜像模板与样例地址不匹配" }
                "not_pending", "already_handled" -> "该条目已被处理，请刷新列表"
                "category_in_use" -> "分类下仍有软件，无法删除"
                "admin_target", "self_target" -> "无法对管理员或自身账号执行处置"
                "invalid_user_id" -> "目标用户 ID 无效"
                "invalid_permission" -> "限制权限项参数不合法"
                "invalid_period" -> "封禁到期时间格式无效或已过期"
                "invalid_reason" -> "处置原因格式不合法"
                "empty_punish" -> "请至少选择一项封禁或限制措施"
                "invalid_badge" -> "用户身份标签格式不合法"
                "too_many_badges" -> "每个用户最多设置 5 个身份标签"
                "review_note_required" -> rawMsg.ifBlank { "驳回时必须填写驳回理由" }
                else -> rawMsg.ifBlank { "操作失败 ($code)" }
            }
        }
    }


    suspend fun ping(): PingData {
        var url = buildUrl("ping")
        var req = newRequestBuilder(url).get().build()
        val json = try {
            val res = executeRequest(req)
            checkError(res)
            res
        } catch (e: Exception) {
            // 如果伪静态 /api/v1/ping 遇到 404 (服务器未配置伪静态重写)，自动无感降级到查询串 ?r=api/v1/ping
            if (!prefs.useQueryRouting && (e is ApiException && e.code == "not_found" || e.message?.contains("404") == true)) {
                if (prefs is AdminPreferences) {
                    prefs.useQueryRouting = true
                }
                url = buildUrl("ping")
                req = newRequestBuilder(url).get().build()
                val fallbackRes = executeRequest(req)
                checkError(fallbackRes)
                fallbackRes
            } else {
                throw e
            }
        }

        val data = json.optJSONObject("data") ?: JSONObject()
        val scopesArr = data.optJSONArray("granted_scopes") ?: JSONArray()
        val scopes = mutableListOf<String>()
        for (i in 0 until scopesArr.length()) {
            scopes.add(scopesArr.optString(i))
        }

        return PingData(
            siteName = data.optString("site_name", "导航网"),
            serverTime = data.optString("server_time", ""),
            schemaVersion = data.optInt("schema_version", 0),
            grantedScopes = scopes
        )
    }

    suspend fun overview(): OverviewData {
        val url = buildUrl("overview")
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val data = json.optJSONObject("data") ?: JSONObject()
        return OverviewData(
            softwareTotal = data.optInt("software_total", 0),
            softwarePublished = data.optInt("software_published", 0),
            softwarePending = data.optInt("software_pending", 0),
            categoryTotal = data.optInt("category_total", 0),
            commentPending = data.optInt("comment_pending", 0),
            correctionPending = data.optInt("correction_pending", 0),
            reportPending = data.optInt("report_pending", 0),
            resourcePending = data.optInt("resource_pending", 0),
            userTotal = data.optInt("user_total", 0),
            apiKeysActive = data.optInt("api_keys_active", 0),
            apiCallsTotal = data.optInt("api_calls_total", 0),
            apiCallsDenied = data.optInt("api_calls_denied", 0)
        )
    }

    suspend fun getPendingList(): List<SoftwareItem> {
        val url = buildUrl("pending")
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<SoftwareItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                SoftwareItem(
                    id = item.optInt("id", 0),
                    name = item.optString("name", "未命名"),
                    iconUrl = item.optString("icon_url", ""),
                    officialUrl = item.optString("official_url", ""),
                    categoryId = item.optInt("category_id", 0),
                    categoryName = item.optString("category_name", ""),
                    summary = item.optString("summary", ""),
                    status = item.optString("status", "pending"),
                    developer = item.optString("developer", ""),
                    specialNotes = item.optString("special_notes", ""),
                    descriptionMd = item.optString("description_md", ""),
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return list
    }

    suspend fun reviewSoftware(id: Int, action: String, reviewNote: String = "", categoryId: Int? = null) {
        val url = buildUrl("review")
        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("id", id)
            put("action", action) // approve, approve_ai, reject
            if (reviewNote.isNotBlank()) put("review_note", reviewNote)
            if (categoryId != null && categoryId > 0) put("category_id", categoryId)
        }
        val req = newRequestBuilder(url)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val json = executeRequest(req)
        checkError(json)
    }

    suspend fun getSoftwares(query: String = "", status: String = "", page: Int = 1, perPage: Int = 20): Pair<List<SoftwareItem>, ApiMeta> {
        val params = mutableMapOf<String, String>()
        if (query.isNotBlank()) params["q"] = query
        if (status.isNotBlank()) params["status"] = status
        params["page"] = page.toString()
        params["per_page"] = perPage.toString()

        val url = buildUrl("softwares", params)
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val metaObj = json.optJSONObject("meta")
        val meta = ApiMeta(
            page = metaObj?.optInt("page", page) ?: page,
            perPage = metaObj?.optInt("per_page", perPage) ?: perPage,
            total = metaObj?.optInt("total", 0) ?: 0
        )

        val list = mutableListOf<SoftwareItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                SoftwareItem(
                    id = item.optInt("id", 0),
                    name = item.optString("name", "未命名"),
                    iconUrl = item.optString("icon_url", ""),
                    officialUrl = item.optString("official_url", ""),
                    categoryId = item.optInt("category_id", 0),
                    categoryName = item.optString("category_name", ""),
                    summary = item.optString("summary", ""),
                    status = item.optString("status", "published"),
                    developer = item.optString("developer", ""),
                    specialNotes = item.optString("special_notes", ""),
                    descriptionMd = item.optString("description_md", ""),
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return Pair(list, meta)
    }

    suspend fun getSoftwareDetail(id: Int): SoftwareDetailData {
        val detailUrl = buildUrl("software", mapOf("id" to id.toString()))
        val detailReq = newRequestBuilder(detailUrl).get().build()
        val detailJson = executeRequest(detailReq)
        checkError(detailJson)
        val item = detailJson.optJSONObject("data") ?: throw IOException("获取软件信息失败")

        return SoftwareDetailData(
            id = item.optInt("id", id),
            name = item.optString("name", ""),
            slug = item.optString("slug", ""),
            iconUrl = item.optString("icon_url", ""),
            officialUrl = item.optString("official_url", ""),
            githubUrl = item.optString("github_url", ""),
            categoryId = item.optInt("category_id", 0),
            categoryName = item.optString("category_name", ""),
            summary = item.optString("summary", ""),
            specialNotes = item.optString("special_notes", ""),
            descriptionMd = item.optString("description_md", ""),
            status = item.optString("status", "published"),
            isFeatured = item.optInt("is_featured", 0) == 1,
            priceType = item.optString("price_type", "free"),
            priceDesc = item.optString("price_desc", ""),
            isOpenSource = item.optInt("is_open_source", 1) == 1,
            license = item.optString("license", ""),
            developer = item.optString("developer", ""),
            publisher = item.optString("publisher", ""),
            tags = item.optString("tags", ""),
            screenshotUrl = item.optString("screenshot_url", ""),
            rawJsonString = item.toString()
        )
    }

    suspend fun saveSoftware(detail: SoftwareDetailData) {
        val saveUrl = buildUrl("softwareSave")
        val baseObj = if (detail.rawJsonString.isNotBlank()) {
            try { JSONObject(detail.rawJsonString) } catch (_: Exception) { JSONObject() }
        } else {
            JSONObject()
        }

        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("id", detail.id)
            put("name", detail.name)
            put("icon_url", detail.iconUrl.ifBlank { baseObj.optString("icon_url", "") })
            put("official_url", detail.officialUrl)
            put("github_url", detail.githubUrl.ifBlank { baseObj.optString("github_url", "") })
            put("category_id", detail.categoryId)
            put("special_notes", detail.specialNotes)
            put("description_md", detail.descriptionMd.ifBlank { baseObj.optString("description_md", "") })
            put("status", detail.status)
            put("is_featured", if (detail.isFeatured) 1 else 0)
            put("price_type", detail.priceType)
            put("price_desc", detail.priceDesc)
            put("is_open_source", if (detail.isOpenSource) 1 else 0)
            put("license", detail.license)
            put("developer", detail.developer)
            put("tags", detail.tags)
            put("screenshot_url", detail.screenshotUrl.ifBlank { baseObj.optString("screenshot_url", "") })
            put("summary", detail.summary)
            if (detail.slug.isNotBlank()) put("slug", detail.slug) else if (baseObj.has("slug")) put("slug", baseObj.optString("slug"))
            if (detail.publisher.isNotBlank()) put("publisher", detail.publisher) else if (baseObj.has("publisher")) put("publisher", baseObj.optString("publisher"))

            if (baseObj.has("channels")) {
                val channels = baseObj.optJSONArray("channels")
                if (channels != null) {
                    put("other_urls", channels.toString())
                }
            } else if (baseObj.has("other_urls")) {
                put("other_urls", baseObj.optString("other_urls"))
            }
        }

        val saveReq = newRequestBuilder(saveUrl)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val saveJson = executeRequest(saveReq)
        checkError(saveJson)
    }

    suspend fun toggleSoftwareStatus(id: Int, newStatus: String) {
        val detail = getSoftwareDetail(id)
        saveSoftware(detail.copy(status = newStatus))
    }

    suspend fun getReports(status: String = ""): List<ReportItem> {
        val params = if (status.isNotBlank()) mapOf("status" to status) else emptyMap()
        val url = buildUrl("reports", params)
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<ReportItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                ReportItem(
                    id = item.optInt("id", 0),
                    softwareId = item.optInt("software_id", 0),
                    softwareName = item.optString("software_name", "关联软件#${item.optInt("software_id")}"),
                    type = item.optString("type", ""),
                    reason = item.optString("reason", item.optString("content", "")),
                    status = item.optString("status", "pending"),
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return list
    }

    suspend fun updateReport(id: Int, status: String, reason: String = "") {
        val url = buildUrl("reportUpdate")
        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("id", id)
            put("status", status) // pending, reviewing, resolved, rejected
            if (reason.isNotBlank()) put("review_reason", reason)
        }
        val req = newRequestBuilder(url)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val json = executeRequest(req)
        checkError(json)
    }

    suspend fun getCorrections(): List<CorrectionItem> {
        val url = buildUrl("corrections")
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<CorrectionItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                CorrectionItem(
                    id = item.optInt("id", 0),
                    softwareId = item.optInt("software_id", 0),
                    softwareName = item.optString("software_name", "软件#${item.optInt("software_id")}"),
                    fieldName = item.optString("field_name", ""),
                    suggestedValue = item.optString("suggested_value", ""),
                    reason = item.optString("reason", ""),
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return list
    }

    suspend fun reviewCorrection(id: Int, action: String, reviewNote: String = "") {
        val url = buildUrl("correctionReview")
        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("id", id)
            put("action", action) // approve, reject
            if (reviewNote.isNotBlank()) put("review_note", reviewNote)
        }
        val req = newRequestBuilder(url)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val json = executeRequest(req)
        checkError(json)
    }

    suspend fun getUsers(query: String = "", page: Int = 1, perPage: Int = 20): Pair<List<UserItem>, ApiMeta> {
        val params = mutableMapOf<String, String>()
        if (query.isNotBlank()) params["q"] = query
        params["page"] = page.toString()
        params["per_page"] = perPage.toString()

        val url = buildUrl("users", params)
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val metaObj = json.optJSONObject("meta")
        val meta = ApiMeta(
            page = metaObj?.optInt("page", page) ?: page,
            perPage = metaObj?.optInt("per_page", perPage) ?: perPage,
            total = metaObj?.optInt("total", 0) ?: 0
        )

        val list = mutableListOf<UserItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            val badgesArr = item.optJSONArray("badges") ?: JSONArray()
            val badges = mutableListOf<String>()
            for (b in 0 until badgesArr.length()) {
                badges.add(badgesArr.optString(b))
            }
            list.add(
                UserItem(
                    id = item.optInt("id", 0),
                    username = item.optString("username", ""),
                    email = item.optString("email", ""),
                    status = item.optString("status", "active"),
                    isBanned = item.optBoolean("is_banned", item.optInt("is_banned", 0) == 1),
                    bannedUntil = item.optString("banned_until").takeIf { it.isNotBlank() },
                    banReason = item.optString("ban_reason").takeIf { it.isNotBlank() },
                    badges = badges,
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return Pair(list, meta)
    }

    suspend fun punishUser(userId: Int, permanent: Boolean, bannedUntil: String = "", reason: String = "") {
        val url = buildUrl("userPunish")
        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("user_id", userId)
            put("permanent", permanent)
            if (bannedUntil.isNotBlank()) put("banned_until", bannedUntil)
            if (reason.isNotBlank()) put("ban_reason", reason)
        }
        val req = newRequestBuilder(url)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val json = executeRequest(req)
        checkError(json)
    }

    suspend fun unpunishUser(userId: Int) {
        val url = buildUrl("userUnpunish")
        val bodyObj = JSONObject().apply {
            put("username", prefs.username)
            put("password", prefs.password)
            put("user_id", userId)
        }
        val req = newRequestBuilder(url)
            .post(bodyObj.toString().toRequestBody(jsonMediaType))
            .build()
        val json = executeRequest(req)
        checkError(json)
    }

    suspend fun getLogs(limit: Int = 30): List<ApiLogItem> {
        val url = buildUrl("logs", mapOf("limit" to limit.toString()))
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<ApiLogItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                ApiLogItem(
                    id = item.optInt("id", 0),
                    endpoint = item.optString("endpoint", ""),
                    method = item.optString("method", "GET"),
                    ip = item.optString("ip", ""),
                    status = item.optString("status", "ok"),
                    httpCode = item.optInt("http_code", 200),
                    createdAt = item.optString("created_at", "")
                )
            )
        }
        return list
    }

    suspend fun getCategories(): List<CategoryItem> {
        val url = buildUrl("categories")
        val req = newRequestBuilder(url).get().build()
        val json = executeRequest(req)
        checkError(json)

        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<CategoryItem>()
        for (i in 0 until arr.length()) {
            val item = arr.optJSONObject(i) ?: continue
            list.add(
                CategoryItem(
                    id = item.optInt("id", 0),
                    name = item.optString("name", ""),
                    slug = item.optString("slug", ""),
                    icon = item.optString("icon", "folder"),
                    sortOrder = item.optInt("sort_order", 0),
                    softwareCount = item.optInt("software_count", 0)
                )
            )
        }
        return list
    }
}

class ApiException(val code: String, message: String, val retryAfter: Int = 0) : Exception(message)

