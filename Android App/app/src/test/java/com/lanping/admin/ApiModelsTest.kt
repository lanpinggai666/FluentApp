package com.lanping.admin

import com.lanping.admin.data.AdminCredentialsProvider
import com.lanping.admin.data.ApiException
import com.lanping.admin.data.NavApiService
import com.lanping.admin.model.*
import org.json.JSONArray
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test

class ApiModelsTest {

    private class MockCredentialsProvider(
        override val baseUrl: String = "https://app.lanpinggai.top",
        override val apiKey: String = "nav_testkey12345678901234567890123456789012",
        override val username: String = "admin",
        override val password: String = "adminPass123!",
        override val useQueryRouting: Boolean = false
    ) : AdminCredentialsProvider

    @Test
    fun testPingDataParsing() {
        val jsonString = """
            {
                "success": true,
                "data": {
                    "site_name": "清源软件导航",
                    "server_time": "2026-10-02T11:45:00+08:00",
                    "schema_version": 16,
                    "granted_scopes": ["read", "write"]
                }
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        assertTrue(json.getBoolean("success"))
        val data = json.getJSONObject("data")
        val scopes = mutableListOf<String>()
        val arr = data.getJSONArray("granted_scopes")
        for (i in 0 until arr.length()) {
            scopes.add(arr.getString(i))
        }

        val ping = PingData(
            siteName = data.getString("site_name"),
            serverTime = data.getString("server_time"),
            schemaVersion = data.getInt("schema_version"),
            grantedScopes = scopes
        )

        assertEquals("清源软件导航", ping.siteName)
        assertEquals(16, ping.schemaVersion)
        assertEquals(2, ping.grantedScopes.size)
        assertTrue(ping.grantedScopes.contains("write"))
    }

    @Test
    fun testOverviewDataParsing() {
        val jsonString = """
            {
                "success": true,
                "data": {
                    "software_total": 120,
                    "software_published": 115,
                    "software_pending": 5,
                    "category_total": 8,
                    "comment_pending": 2,
                    "correction_pending": 3,
                    "report_pending": 1,
                    "resource_pending": 0,
                    "user_total": 45,
                    "api_keys_active": 3,
                    "api_calls_total": 350,
                    "api_calls_denied": 2
                }
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        val data = json.getJSONObject("data")
        val overview = OverviewData(
            softwareTotal = data.getInt("software_total"),
            softwarePublished = data.getInt("software_published"),
            softwarePending = data.getInt("software_pending"),
            categoryTotal = data.getInt("category_total"),
            commentPending = data.getInt("comment_pending"),
            correctionPending = data.getInt("correction_pending"),
            reportPending = data.getInt("report_pending"),
            userTotal = data.getInt("user_total"),
            apiKeysActive = data.getInt("api_keys_active"),
            apiCallsTotal = data.getInt("api_calls_total"),
            apiCallsDenied = data.getInt("api_calls_denied")
        )

        assertEquals(120, overview.softwareTotal)
        assertEquals(5, overview.softwarePending)
        assertEquals(1, overview.reportPending)
        assertEquals(3, overview.correctionPending)
        assertEquals(45, overview.userTotal)
    }

    @Test
    fun testErrorEnvelope() {
        val jsonString = """
            {
                "success": false,
                "error": {
                    "code": "invalid_credentials",
                    "message": "管理员用户名或口令不正确"
                }
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        assertFalse(json.getBoolean("success"))
        val err = json.getJSONObject("error")
        val apiError = ApiError(
            code = err.getString("code"),
            message = err.getString("message")
        )

        assertEquals("invalid_credentials", apiError.code)
        assertEquals("管理员用户名或口令不正确", apiError.message)
        assertEquals(0, apiError.retryAfter)
    }

    @Test
    fun testRateLimitedWithRetryAfterParsing() {
        val jsonString = """
            {
                "success": false,
                "error": {
                    "code": "rate_limited",
                    "message": "调用过于频繁，请 45 秒后再试",
                    "retry_after": 45
                }
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        assertFalse(json.getBoolean("success"))
        val err = json.getJSONObject("error")
        val apiError = ApiError(
            code = err.getString("code"),
            message = err.getString("message"),
            retryAfter = err.getInt("retry_after")
        )

        assertEquals("rate_limited", apiError.code)
        assertEquals(45, apiError.retryAfter)

        // Verify mapErrorCode formats retry_after dynamically
        val mapped = NavApiService.mapErrorCode("rate_limited", retryAfter = 45)
        assertTrue(mapped.contains("45"))
        assertTrue(mapped.contains("秒后再试"))

        // Verify service checkError throws ApiException with retryAfter
        val service = NavApiService(MockCredentialsProvider())
        try {
            service.checkError(json)
            fail("Expected ApiException")
        } catch (e: ApiException) {
            assertEquals("rate_limited", e.code)
            assertEquals(45, e.retryAfter)
            assertTrue(e.message?.contains("45") == true)
        }
    }

    @Test
    fun testAuthThrottledWithRetryAfterParsing() {
        val jsonString = """
            {
                "success": false,
                "error": {
                    "code": "auth_throttled",
                    "message": "管理员凭据连续错误太多次，请 120 秒后再试",
                    "retry_after": 120
                }
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        val err = json.getJSONObject("error")
        val apiError = ApiError(
            code = err.getString("code"),
            message = err.getString("message"),
            retryAfter = err.getInt("retry_after")
        )

        assertEquals("auth_throttled", apiError.code)
        assertEquals(120, apiError.retryAfter)

        val mapped = NavApiService.mapErrorCode("auth_throttled", retryAfter = 120)
        assertTrue(mapped.contains("120"))
        assertTrue(mapped.contains("秒后再试"))
    }

    @Test
    fun testAll24ErrorCodesMapping() {
        val codes = listOf(
            "missing_key",
            "key_invalid",
            "key_revoked",
            "key_in_query",
            "missing_credentials",
            "invalid_credentials",
            "auth_throttled",
            "insufficient_scope",
            "rate_limited",
            "insecure_transport",
            "api_disabled",
            "invalid_param",
            "method_not_allowed",
            "confirm_required",
            "not_found",
            "unknown_endpoint",
            "invalid_software",
            "invalid_mirror",
            "invalid_notice",
            "invalid_base_url",
            "mirror_not_learned",
            "mirror_mismatch",
            "not_pending",
            "already_handled",
            "category_in_use",
            "admin_target",
            "self_target",
            "invalid_user_id",
            "invalid_permission",
            "invalid_period",
            "invalid_reason",
            "empty_punish",
            "invalid_badge",
            "too_many_badges"
        )

        for (code in codes) {
            val defaultMsg = NavApiService.mapErrorCode(code, rawMsg = "", retryAfter = 10)
            assertNotNull(defaultMsg)
            assertTrue("Default message for code $code should not be blank", defaultMsg.isNotBlank())
            assertFalse("Default message for code $code should not contain unknown", defaultMsg.contains("unknown_error"))
        }

        // Test custom server message preservation for parameter/validation errors as defined in API.md
        val customParamMsg = NavApiService.mapErrorCode("invalid_param", rawMsg = "分类名称不能为空")
        assertEquals("分类名称不能为空", customParamMsg)

        val customSoftwareMsg = NavApiService.mapErrorCode("invalid_software", rawMsg = "链接不在白名单内")
        assertEquals("链接不在白名单内", customSoftwareMsg)

        // Test fixed security code overrides
        val fixedKeyMsg = NavApiService.mapErrorCode("missing_key", rawMsg = "ignored server message")
        assertEquals("缺少 API 密钥", fixedKeyMsg)
    }

    @Test
    fun testCategoriesParsing() {
        val jsonString = """
            {
                "success": true,
                "data": [
                    {
                        "id": 1,
                        "name": "实用工具",
                        "slug": "tools",
                        "icon": "tool",
                        "sort_order": 0,
                        "software_count": 28
                    },
                    {
                        "id": 2,
                        "name": "开发编程",
                        "slug": "dev",
                        "icon": "code",
                        "sort_order": 1,
                        "software_count": 42
                    }
                ]
            }
        """.trimIndent()

        val json = JSONObject(jsonString)
        val arr = json.getJSONArray("data")
        val list = mutableListOf<CategoryItem>()
        for (i in 0 until arr.length()) {
            val obj = arr.getJSONObject(i)
            list.add(
                CategoryItem(
                    id = obj.getInt("id"),
                    name = obj.getString("name"),
                    slug = obj.getString("slug"),
                    icon = obj.getString("icon"),
                    sortOrder = obj.getInt("sort_order"),
                    softwareCount = obj.getInt("software_count")
                )
            )
        }

        assertEquals(2, list.size)
        assertEquals("实用工具", list[0].name)
        assertEquals(28, list[0].softwareCount)
        assertEquals("开发编程", list[1].name)
        assertEquals(42, list[1].softwareCount)
    }

    @Test
    fun testSecurityCredentialsNeverInQueryString() {
        val provider = MockCredentialsProvider(useQueryRouting = false)
        val service = NavApiService(provider)

        val url = service.buildUrl("softwares", mapOf("q" to "git", "page" to "1"))
        assertFalse("URL must not contain api_key in query string", url.contains("api_key="))
        assertFalse("URL must not contain key in query string", url.contains("key="))
        assertFalse("URL must not contain password in query string", url.contains("password="))
        assertFalse("URL must not contain raw api key value", url.contains(provider.apiKey))
        assertFalse("URL must not contain raw password", url.contains(provider.password))
        // 遵循 API.md 2.0: 伪静态格式
        assertTrue("URL must point to clean pseudo-static action endpoint", url.contains("/api/v1/softwares?q=git&page=1"))

        // 测试兼容查询串格式
        val queryProvider = MockCredentialsProvider(useQueryRouting = true)
        val queryService = NavApiService(queryProvider)
        val queryUrl = queryService.buildUrl("softwares", mapOf("q" to "git", "page" to "1"))
        assertTrue("URL must point to query string action endpoint", queryUrl.contains("/index.php?r=api/v1/softwares&q=git&page=1"))
    }

    @Test
    fun testDefenseInDepthQueryParamSanitization() {
        val provider = MockCredentialsProvider()
        val service = NavApiService(provider)

        // Attempt to maliciously or inadvertently pass sensitive credential keys in queryParams
        val maliciousParams = mapOf(
            "q" to "vscode",
            "api_key" to "nav_leakedkey123456",
            "password" to "secretpassword",
            "token" to "sometoken",
            "page" to "2"
        )
        val url = service.buildUrl("softwares", maliciousParams)

        assertFalse("buildUrl must sanitize api_key", url.contains("api_key="))
        assertFalse("buildUrl must sanitize password", url.contains("password="))
        assertFalse("buildUrl must sanitize token", url.contains("token="))
        assertTrue("buildUrl must keep safe query param q", url.contains("q=vscode"))
        assertTrue("buildUrl must keep safe query param page", url.contains("page=2"))
    }

    @Test
    fun testBaseUrlNormalization() {
        val clean1 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("https://app.lanpinggai.top/")
        assertEquals("https://app.lanpinggai.top", clean1)

        val clean2 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("https://app.lanpinggai.top///")
        assertEquals("https://app.lanpinggai.top", clean2)

        val clean3 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("https://app.lanpinggai.top/index.php")
        assertEquals("https://app.lanpinggai.top", clean3)

        val clean4 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("https://app.lanpinggai.top/index.php?r=admin")
        assertEquals("https://app.lanpinggai.top", clean4)

        val clean5 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("   ")
        assertEquals("https://app.lanpinggai.top", clean5)

        val clean6 = com.lanping.admin.data.AdminPreferences.cleanBaseUrl("https://app.lanpinggai.top/api/v1/softwares")
        assertEquals("https://app.lanpinggai.top", clean6)
    }

    @Test
    fun testReviewNoteRequiredMapping() {
        val msg = NavApiService.mapErrorCode("review_note_required", "")
        assertEquals("驳回时必须填写驳回理由", msg)
    }

    @Test
    fun testReviewSoftwarePayloadWithCategory() {
        val bodyObj = JSONObject().apply {
            put("username", "admin")
            put("password", "pass123")
            put("id", 42)
            put("action", "approve")
            put("category_id", 3)
        }
        assertEquals(42, bodyObj.getInt("id"))
        assertEquals("approve", bodyObj.getString("action"))
        assertEquals(3, bodyObj.getInt("category_id"))
        assertEquals("admin", bodyObj.getString("username"))
        assertEquals("pass123", bodyObj.getString("password"))
    }

    @Test
    fun testSecurityHeadersIncludeCredentials() {
        val provider = MockCredentialsProvider()
        val service = NavApiService(provider)

        val url = service.buildUrl("pending")
        val request = service.newRequestBuilder(url).build()

        assertEquals(provider.apiKey, request.header("X-Api-Key"))
        assertEquals(provider.username, request.header("X-Admin-Username"))
        assertEquals(provider.password, request.header("X-Admin-Password"))
        assertEquals("application/json", request.header("Accept"))
        assertFalse("Request url must not leak credentials", request.url.toString().contains(provider.apiKey))
    }

    @Test
    fun testSoftwareDetailParsingAndPayloadBuilding() {
        val rawJson = """
            {
                "id": 101,
                "name": "Visual Studio Code",
                "slug": "vscode",
                "icon_url": "https://example.com/vscode.png",
                "official_url": "https://code.visualstudio.com",
                "github_url": "https://github.com/microsoft/vscode",
                "category_id": 2,
                "category_name": "开发工具",
                "summary": "开源代码编辑器",
                "special_notes": "推荐配合扩展使用",
                "description_md": "微软出品的代码编辑器",
                "status": "published",
                "is_featured": 1,
                "price_type": "free",
                "price_desc": "免费开源",
                "is_open_source": 1,
                "license": "MIT",
                "developer": "Microsoft",
                "publisher": "admin",
                "tags": "编辑器,开发,微软",
                "screenshot_url": "https://example.com/shot.png",
                "channels": [
                    {"name": "官方安装包", "url": "https://update.code.visualstudio.com/latest/win32-x64-user/stable"}
                ]
            }
        """.trimIndent()

        val json = JSONObject(rawJson)
        val detail = SoftwareDetailData(
            id = json.getInt("id"),
            name = json.getString("name"),
            slug = json.getString("slug"),
            iconUrl = json.getString("icon_url"),
            officialUrl = json.getString("official_url"),
            githubUrl = json.getString("github_url"),
            categoryId = json.getInt("category_id"),
            categoryName = json.getString("category_name"),
            summary = json.getString("summary"),
            specialNotes = json.getString("special_notes"),
            descriptionMd = json.getString("description_md"),
            status = json.getString("status"),
            isFeatured = json.getInt("is_featured") == 1,
            priceType = json.getString("price_type"),
            priceDesc = json.getString("price_desc"),
            isOpenSource = json.getInt("is_open_source") == 1,
            license = json.getString("license"),
            developer = json.getString("developer"),
            publisher = json.getString("publisher"),
            tags = json.getString("tags"),
            screenshotUrl = json.getString("screenshot_url"),
            rawJsonString = rawJson
        )

        assertEquals("Visual Studio Code", detail.name)
        assertEquals("vscode", detail.slug)
        assertEquals(2, detail.categoryId)
        assertTrue(detail.isFeatured)
        assertEquals("published", detail.status)

        // Modify detail
        val updated = detail.copy(
            name = "VS Code (最新版)",
            status = "offline",
            summary = "现代化代码与文本编辑器"
        )

        assertEquals("VS Code (最新版)", updated.name)
        assertEquals("offline", updated.status)
        assertEquals("现代化代码与文本编辑器", updated.summary)
        // Original fields preserved
        assertEquals("vscode", updated.slug)
        assertEquals("Microsoft", updated.developer)
        assertTrue(updated.rawJsonString.contains("channels"))
    }
}
