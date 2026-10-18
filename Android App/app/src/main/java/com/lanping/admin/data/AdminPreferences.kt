package com.lanping.admin.data

import android.content.Context
import android.content.SharedPreferences

interface AdminCredentialsProvider {
    val baseUrl: String
    val apiKey: String
    val username: String
    val password: String
    val useQueryRouting: Boolean
}

class AdminPreferences(context: Context) : AdminCredentialsProvider {
    private val prefs: SharedPreferences = context.getSharedPreferences("watch_admin_prefs", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_BASE_URL = "base_url"
        private const val KEY_API_KEY = "api_key"
        private const val KEY_USERNAME = "username"
        private const val KEY_PASSWORD = "password"
        private const val KEY_USE_QUERY_ROUTING = "use_query_routing"
        private const val DEFAULT_BASE_URL = "https://app.lanpinggai.top"

        fun cleanBaseUrl(input: String): String {
            var url = input.trim().trimEnd('/')
            if (url.contains("/api/v1")) {
                url = url.substringBefore("/api/v1").trimEnd('/')
            }
            if (url.contains("/index.php")) {
                url = url.substringBefore("/index.php").trimEnd('/')
            }
            if (url.contains("?")) {
                url = url.substringBefore("?").trimEnd('/')
            }
            return if (url.isBlank()) DEFAULT_BASE_URL else url
        }
    }

    override var baseUrl: String
        get() {
            val url = prefs.getString(KEY_BASE_URL, DEFAULT_BASE_URL) ?: DEFAULT_BASE_URL
            return cleanBaseUrl(url)
        }
        set(value) {
            prefs.edit().putString(KEY_BASE_URL, cleanBaseUrl(value)).apply()
        }

    override var apiKey: String
        get() = prefs.getString(KEY_API_KEY, "") ?: ""
        set(value) = prefs.edit().putString(KEY_API_KEY, value.trim()).apply()

    override var username: String
        get() = prefs.getString(KEY_USERNAME, "") ?: ""
        set(value) = prefs.edit().putString(KEY_USERNAME, value.trim()).apply()

    override var password: String
        get() = prefs.getString(KEY_PASSWORD, "") ?: ""
        set(value) = prefs.edit().putString(KEY_PASSWORD, value).apply()

    override var useQueryRouting: Boolean
        get() = prefs.getBoolean(KEY_USE_QUERY_ROUTING, false)
        set(value) = prefs.edit().putBoolean(KEY_USE_QUERY_ROUTING, value).apply()

    fun isConfigured(): Boolean {
        return apiKey.isNotBlank() && username.isNotBlank() && password.isNotBlank()
    }

    fun save(baseUrl: String, apiKey: String, username: String, pass: String, useQueryRouting: Boolean = false) {
        this.baseUrl = baseUrl
        this.apiKey = apiKey
        this.username = username
        this.password = pass
        this.useQueryRouting = useQueryRouting
    }

    fun clear() {
        prefs.edit().clear().apply()
    }
}
