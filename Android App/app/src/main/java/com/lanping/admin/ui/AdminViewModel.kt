package com.lanping.admin.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.lanping.admin.data.AdminPreferences
import com.lanping.admin.data.ApiException
import com.lanping.admin.data.NavApiService
import com.lanping.admin.model.*
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

enum class HapticFeedbackType {
    CLICK,
    SUCCESS,
    REJECT,
    ERROR
}

class AdminViewModel(application: Application) : AndroidViewModel(application) {

    val prefs = AdminPreferences(application)
    val apiService = NavApiService(prefs)

    private val _isLoggedIn = MutableStateFlow(prefs.isConfigured())
    val isLoggedIn: StateFlow<Boolean> = _isLoggedIn.asStateFlow()

    // Haptic feedback event stream
    private val _hapticEvent = MutableSharedFlow<HapticFeedbackType>(extraBufferCapacity = 8)
    val hapticEvent: SharedFlow<HapticFeedbackType> = _hapticEvent.asSharedFlow()

    // 429 Retry-After Cooldown timer (seconds)
    private val _retryCooldownSeconds = MutableStateFlow(0)
    val retryCooldownSeconds: StateFlow<Int> = _retryCooldownSeconds.asStateFlow()
    private var cooldownJob: Job? = null

    fun triggerHaptic(type: HapticFeedbackType) {
        _hapticEvent.tryEmit(type)
    }

    fun startCooldown(seconds: Int) {
        if (seconds <= 0) return
        cooldownJob?.cancel()
        cooldownJob = viewModelScope.launch {
            var remaining = seconds
            while (remaining > 0) {
                _retryCooldownSeconds.value = remaining
                delay(1000)
                remaining--
            }
            _retryCooldownSeconds.value = 0
        }
    }

    private fun handleException(e: Exception, defaultMsg: String, errorTarget: MutableStateFlow<String?>? = null) {
        val errorMsg = e.message ?: defaultMsg
        errorTarget?.value = errorMsg
        _message.value = errorMsg
        _hapticEvent.tryEmit(HapticFeedbackType.ERROR)
        if (e is ApiException && e.retryAfter > 0) {
            startCooldown(e.retryAfter)
        }
    }

    // Ping / Test connection
    private val _isTestingPing = MutableStateFlow(false)
    val isTestingPing: StateFlow<Boolean> = _isTestingPing.asStateFlow()

    private val _pingResult = MutableStateFlow<PingData?>(null)
    val pingResult: StateFlow<PingData?> = _pingResult.asStateFlow()

    private val _pingError = MutableStateFlow<String?>(null)
    val pingError: StateFlow<String?> = _pingError.asStateFlow()

    // Dashboard Overview
    private val _overview = MutableStateFlow<OverviewData?>(null)
    val overview: StateFlow<OverviewData?> = _overview.asStateFlow()

    private val _isLoadingOverview = MutableStateFlow(false)
    val isLoadingOverview: StateFlow<Boolean> = _isLoadingOverview.asStateFlow()

    private val _overviewError = MutableStateFlow<String?>(null)
    val overviewError: StateFlow<String?> = _overviewError.asStateFlow()

    // Categories
    private val _categoryList = MutableStateFlow<List<CategoryItem>>(emptyList())
    val categoryList: StateFlow<List<CategoryItem>> = _categoryList.asStateFlow()

    // Pending software submissions
    private val _pendingList = MutableStateFlow<List<SoftwareItem>>(emptyList())
    val pendingList: StateFlow<List<SoftwareItem>> = _pendingList.asStateFlow()

    private val _isLoadingPending = MutableStateFlow(false)
    val isLoadingPending: StateFlow<Boolean> = _isLoadingPending.asStateFlow()

    private val _pendingError = MutableStateFlow<String?>(null)
    val pendingError: StateFlow<String?> = _pendingError.asStateFlow()

    private val _pendingActionId = MutableStateFlow<Int?>(null)
    val pendingActionId: StateFlow<Int?> = _pendingActionId.asStateFlow()

    // Reports & Corrections
    private val _reportList = MutableStateFlow<List<ReportItem>>(emptyList())
    val reportList: StateFlow<List<ReportItem>> = _reportList.asStateFlow()

    private val _correctionList = MutableStateFlow<List<CorrectionItem>>(emptyList())
    val correctionList: StateFlow<List<CorrectionItem>> = _correctionList.asStateFlow()

    private val _isLoadingReports = MutableStateFlow(false)
    val isLoadingReports: StateFlow<Boolean> = _isLoadingReports.asStateFlow()

    private val _reportsError = MutableStateFlow<String?>(null)
    val reportsError: StateFlow<String?> = _reportsError.asStateFlow()

    // Softwares
    private val _softwareList = MutableStateFlow<List<SoftwareItem>>(emptyList())
    val softwareList: StateFlow<List<SoftwareItem>> = _softwareList.asStateFlow()

    private val _isLoadingSoftwares = MutableStateFlow(false)
    val isLoadingSoftwares: StateFlow<Boolean> = _isLoadingSoftwares.asStateFlow()

    private val _softwaresError = MutableStateFlow<String?>(null)
    val softwaresError: StateFlow<String?> = _softwaresError.asStateFlow()
    private var softwareSearchJob: Job? = null

    // Software Detail Editing
    private val _editingSoftwareDetail = MutableStateFlow<SoftwareDetailData?>(null)
    val editingSoftwareDetail: StateFlow<SoftwareDetailData?> = _editingSoftwareDetail.asStateFlow()

    private val _isLoadingSoftwareDetail = MutableStateFlow(false)
    val isLoadingSoftwareDetail: StateFlow<Boolean> = _isLoadingSoftwareDetail.asStateFlow()

    private val _isSavingSoftwareDetail = MutableStateFlow(false)
    val isSavingSoftwareDetail: StateFlow<Boolean> = _isSavingSoftwareDetail.asStateFlow()

    private val _softwareDetailError = MutableStateFlow<String?>(null)
    val softwareDetailError: StateFlow<String?> = _softwareDetailError.asStateFlow()

    // Users
    private val _userList = MutableStateFlow<List<UserItem>>(emptyList())
    val userList: StateFlow<List<UserItem>> = _userList.asStateFlow()

    private val _isLoadingUsers = MutableStateFlow(false)
    val isLoadingUsers: StateFlow<Boolean> = _isLoadingUsers.asStateFlow()

    private val _usersError = MutableStateFlow<String?>(null)
    val usersError: StateFlow<String?> = _usersError.asStateFlow()
    private var userSearchJob: Job? = null

    // Logs
    private val _logList = MutableStateFlow<List<ApiLogItem>>(emptyList())
    val logList: StateFlow<List<ApiLogItem>> = _logList.asStateFlow()

    private val _isLoadingLogs = MutableStateFlow(false)
    val isLoadingLogs: StateFlow<Boolean> = _isLoadingLogs.asStateFlow()

    private val _logsError = MutableStateFlow<String?>(null)
    val logsError: StateFlow<String?> = _logsError.asStateFlow()

    // Notification toast
    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    fun clearMessage() {
        _message.value = null
    }

    fun showWarning(text: String) {
        _message.value = text
        _hapticEvent.tryEmit(HapticFeedbackType.ERROR)
    }

    fun testConnection(url: String, apiKey: String, username: String, pass: String, useQueryRouting: Boolean = false) {
        viewModelScope.launch {
            _isTestingPing.value = true
            _pingError.value = null
            _pingResult.value = null
            prefs.save(url, apiKey, username, pass, useQueryRouting)
            try {
                val res = apiService.ping()
                _pingResult.value = res
                _isLoggedIn.value = true
                _message.value = "连接成功: ${res.siteName}"
                _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                loadOverview()
            } catch (e: Exception) {
                _pingError.value = e.message ?: "连接失败"
                _hapticEvent.tryEmit(HapticFeedbackType.ERROR)
                if (e is ApiException && e.retryAfter > 0) {
                    startCooldown(e.retryAfter)
                }
            } finally {
                _isTestingPing.value = false
            }
        }
    }

    fun logout() {
        prefs.clear()
        _isLoggedIn.value = false
        _overview.value = null
        _pendingList.value = emptyList()
        _categoryList.value = emptyList()
        _message.value = "已退出登录"
        _hapticEvent.tryEmit(HapticFeedbackType.CLICK)
    }

    fun loadOverview() {
        viewModelScope.launch {
            _isLoadingOverview.value = true
            _overviewError.value = null
            try {
                _overview.value = apiService.overview()
            } catch (e: Exception) {
                handleException(e, "获取概览失败", _overviewError)
            } finally {
                _isLoadingOverview.value = false
            }
        }
    }

    fun loadCategories() {
        viewModelScope.launch {
            try {
                _categoryList.value = apiService.getCategories()
            } catch (e: Exception) {
                // Ignore silently or keep previous cache
            }
        }
    }

    fun loadPendingList() {
        viewModelScope.launch {
            _isLoadingPending.value = true
            _pendingError.value = null
            loadCategories()
            try {
                _pendingList.value = apiService.getPendingList()
            } catch (e: Exception) {
                handleException(e, "加载待审列表失败", _pendingError)
            } finally {
                _isLoadingPending.value = false
            }
        }
    }

    fun reviewSoftware(id: Int, action: String, note: String = "", categoryId: Int? = null) {
        viewModelScope.launch {
            _pendingActionId.value = id
            try {
                apiService.reviewSoftware(id, action, note, categoryId)
                val actionName = when (action) {
                    "approve" -> "已通过审核"
                    "approve_ai" -> "已AI辅助通过"
                    "reject" -> "已驳回"
                    else -> "处理完成"
                }
                _message.value = actionName
                if (action == "reject") {
                    _hapticEvent.tryEmit(HapticFeedbackType.REJECT)
                } else {
                    _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                }
                // Refresh list and overview
                loadPendingList()
                loadOverview()
            } catch (e: Exception) {
                handleException(e, "操作失败: ${e.message}")
            } finally {
                _pendingActionId.value = null
            }
        }
    }

    fun loadReportsAndCorrections() {
        viewModelScope.launch {
            _isLoadingReports.value = true
            _reportsError.value = null
            try {
                _reportList.value = apiService.getReports(status = "pending")
                _correctionList.value = apiService.getCorrections()
            } catch (e: Exception) {
                handleException(e, "获取待办失败", _reportsError)
            } finally {
                _isLoadingReports.value = false
            }
        }
    }

    fun handleReport(id: Int, status: String, reason: String = "") {
        viewModelScope.launch {
            try {
                apiService.updateReport(id, status, reason)
                _message.value = if (status == "resolved") "举报已标为解决" else "举报已驳回"
                if (status == "resolved") {
                    _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                } else {
                    _hapticEvent.tryEmit(HapticFeedbackType.REJECT)
                }
                loadReportsAndCorrections()
                loadOverview()
            } catch (e: Exception) {
                handleException(e, "处理举报失败: ${e.message}")
            }
        }
    }

    fun handleCorrection(id: Int, action: String, note: String = "") {
        viewModelScope.launch {
            try {
                apiService.reviewCorrection(id, action, note)
                _message.value = if (action == "approve") "纠错已采纳" else "纠错已驳回"
                if (action == "approve") {
                    _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                } else {
                    _hapticEvent.tryEmit(HapticFeedbackType.REJECT)
                }
                loadReportsAndCorrections()
                loadOverview()
            } catch (e: Exception) {
                handleException(e, "处理纠错失败: ${e.message}")
            }
        }
    }

    fun loadSoftwares(query: String = "") {
        softwareSearchJob?.cancel()
        softwareSearchJob = viewModelScope.launch {
            _isLoadingSoftwares.value = true
            _softwaresError.value = null
            try {
                val (list, _) = apiService.getSoftwares(query = query, perPage = 25)
                _softwareList.value = list
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) return@launch
                handleException(e, "加载软件列表失败", _softwaresError)
            } finally {
                _isLoadingSoftwares.value = false
            }
        }
    }

    fun toggleSoftwareStatus(id: Int, currentStatus: String) {
        viewModelScope.launch {
            val newStatus = if (currentStatus == "published") "offline" else "published"
            try {
                apiService.toggleSoftwareStatus(id, newStatus)
                _message.value = if (newStatus == "offline") "已紧急下架" else "已重新上架"
                _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                loadSoftwares()
                loadOverview()
            } catch (e: Exception) {
                handleException(e, "更新状态失败: ${e.message}")
            }
        }
    }

    fun openSoftwareDetailEditor(id: Int) {
        viewModelScope.launch {
            _isLoadingSoftwareDetail.value = true
            _softwareDetailError.value = null
            _editingSoftwareDetail.value = null
            loadCategories()
            try {
                val detail = apiService.getSoftwareDetail(id)
                _editingSoftwareDetail.value = detail
                _hapticEvent.tryEmit(HapticFeedbackType.CLICK)
            } catch (e: Exception) {
                handleException(e, "获取软件详情失败", _softwareDetailError)
            } finally {
                _isLoadingSoftwareDetail.value = false
            }
        }
    }

    fun closeSoftwareDetailEditor() {
        _editingSoftwareDetail.value = null
        _softwareDetailError.value = null
        _isLoadingSoftwareDetail.value = false
        _isSavingSoftwareDetail.value = false
    }

    fun saveSoftwareDetail(detail: SoftwareDetailData, onSaved: (() -> Unit)? = null) {
        viewModelScope.launch {
            _isSavingSoftwareDetail.value = true
            _softwareDetailError.value = null
            try {
                apiService.saveSoftware(detail)
                _message.value = "软件「${detail.name}」已保存"
                _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                _editingSoftwareDetail.value = null
                onSaved?.invoke()
                loadSoftwares()
                loadReportsAndCorrections()
                loadOverview()
            } catch (e: Exception) {
                handleException(e, "保存软件失败: ${e.message}", _softwareDetailError)
            } finally {
                _isSavingSoftwareDetail.value = false
            }
        }
    }

    fun loadUsers(query: String = "") {
        userSearchJob?.cancel()
        userSearchJob = viewModelScope.launch {
            _isLoadingUsers.value = true
            _usersError.value = null
            try {
                val (list, _) = apiService.getUsers(query = query, perPage = 20)
                _userList.value = list
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) return@launch
                handleException(e, "加载用户失败", _usersError)
            } finally {
                _isLoadingUsers.value = false
            }
        }
    }

    fun punishUser(userId: Int, permanent: Boolean, reason: String = "违规封禁") {
        viewModelScope.launch {
            try {
                apiService.punishUser(userId, permanent = permanent, reason = reason)
                _message.value = "已封禁用户 #$userId"
                _hapticEvent.tryEmit(HapticFeedbackType.REJECT)
                loadUsers()
            } catch (e: Exception) {
                handleException(e, "封禁失败: ${e.message}")
            }
        }
    }

    fun unpunishUser(userId: Int) {
        viewModelScope.launch {
            try {
                apiService.unpunishUser(userId)
                _message.value = "已解封用户 #$userId"
                _hapticEvent.tryEmit(HapticFeedbackType.SUCCESS)
                loadUsers()
            } catch (e: Exception) {
                handleException(e, "解封失败: ${e.message}")
            }
        }
    }

    fun loadLogs() {
        viewModelScope.launch {
            _isLoadingLogs.value = true
            _logsError.value = null
            try {
                _logList.value = apiService.getLogs(limit = 25)
            } catch (e: Exception) {
                handleException(e, "加载日志失败", _logsError)
            } finally {
                _isLoadingLogs.value = false
            }
        }
    }
}
