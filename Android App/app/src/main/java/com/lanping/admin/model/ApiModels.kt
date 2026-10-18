package com.lanping.admin.model

data class ApiResult<T>(
    val success: Boolean,
    val data: T? = null,
    val error: ApiError? = null,
    val meta: ApiMeta? = null
)

data class ApiError(
    val code: String = "",
    val message: String = "",
    val retryAfter: Int = 0
)

data class ApiMeta(
    val page: Int = 1,
    val perPage: Int = 20,
    val total: Int = 0,
    val message: String = ""
)

data class PingData(
    val siteName: String = "",
    val serverTime: String = "",
    val schemaVersion: Int = 0,
    val grantedScopes: List<String> = emptyList()
)

data class OverviewData(
    val softwareTotal: Int = 0,
    val softwarePublished: Int = 0,
    val softwarePending: Int = 0,
    val categoryTotal: Int = 0,
    val commentPending: Int = 0,
    val correctionPending: Int = 0,
    val reportPending: Int = 0,
    val resourcePending: Int = 0,
    val userTotal: Int = 0,
    val apiKeysActive: Int = 0,
    val apiCallsTotal: Int = 0,
    val apiCallsDenied: Int = 0
)

data class SoftwareItem(
    val id: Int = 0,
    val name: String = "",
    val iconUrl: String = "",
    val officialUrl: String = "",
    val categoryId: Int = 0,
    val categoryName: String = "",
    val summary: String = "",
    val status: String = "published", // pending, published, rejected, offline
    val developer: String = "",
    val specialNotes: String = "",
    val descriptionMd: String = "",
    val createdAt: String = ""
)

data class SoftwareDetailData(
    val id: Int = 0,
    val name: String = "",
    val slug: String = "",
    val iconUrl: String = "",
    val officialUrl: String = "",
    val githubUrl: String = "",
    val categoryId: Int = 0,
    val categoryName: String = "",
    val summary: String = "",
    val specialNotes: String = "",
    val descriptionMd: String = "",
    val status: String = "published", // pending, published, rejected, offline
    val isFeatured: Boolean = false,
    val priceType: String = "free",
    val priceDesc: String = "",
    val isOpenSource: Boolean = true,
    val license: String = "",
    val developer: String = "",
    val publisher: String = "",
    val tags: String = "",
    val screenshotUrl: String = "",
    val rawJsonString: String = ""
)

data class ReportItem(
    val id: Int = 0,
    val softwareId: Int = 0,
    val softwareName: String = "",
    val type: String = "",
    val reason: String = "",
    val status: String = "pending", // pending, reviewing, resolved, rejected
    val createdAt: String = ""
)

data class CorrectionItem(
    val id: Int = 0,
    val softwareId: Int = 0,
    val softwareName: String = "",
    val fieldName: String = "",
    val suggestedValue: String = "",
    val reason: String = "",
    val createdAt: String = ""
)

data class UserItem(
    val id: Int = 0,
    val username: String = "",
    val email: String = "",
    val status: String = "active",
    val isBanned: Boolean = false,
    val bannedUntil: String? = null,
    val banReason: String? = null,
    val badges: List<String> = emptyList(),
    val createdAt: String = ""
)

data class ApiLogItem(
    val id: Int = 0,
    val endpoint: String = "",
    val method: String = "GET",
    val ip: String = "",
    val status: String = "ok",
    val httpCode: Int = 200,
    val createdAt: String = ""
)

data class CategoryItem(
    val id: Int = 0,
    val name: String = "",
    val slug: String = "",
    val icon: String = "folder",
    val sortOrder: Int = 0,
    val softwareCount: Int = 0
)

