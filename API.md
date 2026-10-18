# 开放 API —— 后台管理能力（v1）

给 App、桌面端、脚本调用本站后台管理能力用。**鉴权是「后台生成的密钥 + 管理员用户名 + 管理员口令」三件套**，
三者缺一不可；不签发长期 token，也不接受只带密钥的宽松模式。

- 入口：`https://app.lanpinggai.top/index.php?r=api/v1/<动作>`（或 `?r=apiadmin/<动作>`，等价）
- 请求体：`Content-Type: application/json` 的 JSON；也兼容表单编码
- 响应：永远是 JSON，且带 `Cache-Control: no-store`
- 覆盖范围：36 个端点（17 只读 + 19 读写）。文末列出**尚未开放**的能力与原因。

---

## 1. 开始之前

### 1.1 必须 HTTPS

管理员口令是**每次请求都要上传**的。因此除非满足下列任一条件，API 一律拒绝：

| 条件 | 说明 |
| --- | --- |
| 请求走 HTTPS | 正常生产环境 |
| 访问的是 `127.0.0.1` / `localhost` / `::1` | 本机调试免麻烦 |
| 后台设置 `api_allow_insecure = 1` | 显式豁免，只建议临时内网用 |

不满足时返回 `403 insecure_transport`。

### 1.2 生成密钥

后台 → **开放 API 密钥**（`?r=admin/apiKeys`）：

1. 填「用途名称」（例如 `安卓 App`），勾选权限范围：`read` 只读 / `write` 读写；
2. 点「生成密钥」，页面会显示一次 `nav_` 开头的 44 位明文，**关掉页面就查不到**；
3. 库里只保存它的 SHA-256 摘要与 12 位识别前缀，因此数据库被读走也拿不到可用凭据；
4. 「吊销」立即生效，正在使用它的客户端会收到 `401 key_revoked`。

两个总开关也在这一页：

| 开关 | 默认 | 作用 |
| --- | --- | --- |
| `api_enabled` | 1 | 置 0 后所有端点统一返回 `503 api_disabled`，用作紧急总闸 |
| `api_allow_insecure` | 0 | 是否允许明文 HTTP。只能从后台会话改，**API 自己的设置端点不认这个键** |

> 密钥的生成/吊销刻意不开放给 API：否则一把泄露的密钥就能铸造新密钥，吊销也就失去意义。

### 1.3 请求头

```
X-Api-Key: nav_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
```

也支持 `Authorization: Bearer nav_...`。

**不要把密钥放进 URL 查询串** —— URL 会进 Nginx/Apache 访问日志、会进 Referer、也会留在浏览器历史里。
带 `?api_key=` 的请求会被直接拒绝（`400 key_in_query`），而不是「先用着再说」。

---

## 2. 统一约定

### 2.0 请求地址：两种写法都可以

| 写法 | 例子 | 说明 |
| --- | --- | --- |
| 伪静态路径 | `https://example.com/api/v1/softwares` | 最干净，推荐 App 端用。前提是 Web 服务器把不存在的路径交给 `index.php`（`deploy/nginx.conf.example` 与 `public/.htaccess` 都已经这么配了） |
| 查询串 | `https://example.com/?r=api/v1/softwares`<br>`https://example.com/index.php?r=apiadmin/softwares` | 不依赖任何重写规则，服务器上排查问题时用它最稳 |

三条容易踩的：

1. **只有 `api/v1/` 这一段会被改写**。裸 `api/`（如 `api/search`）是站点前端自己的接口（实时搜索、图标代理），别往那边接，也不受本套密钥保护。
2. 驼峰动作名两种写法都一样拼：`api/v1/softwareSave`、`?r=apiadmin/mirrorSave`、`?r=apiadmin/userPunish`。
3. 密钥只认请求头 `X-Api-Key`（或 `Authorization: Bearer`）。放在查询串里会被直接拒（`key_in_query`），因为 URL 会进 Nginx 访问日志与 Referer。

### 2.1 响应信封

```jsonc
// 成功
{ "success": true, "data": { }, "meta": { "version": "v1", "page": 1, "per_page": 20, "total": 137 } }

// 失败
{ "success": false, "error": { "code": "invalid_credentials", "message": "管理员用户名或口令不正确" } }
```

App 端请**按 `error.code` 分支**，不要匹配中文 `message`（文案会变，码不会）。

### 2.2 错误码

| code | HTTP | 含义 / 该怎么做 |
| --- | --- | --- |
| `missing_key` | 401 | 没带 `X-Api-Key` |
| `key_invalid` | 401 | 密钥不存在（也可能被删过），去后台重新生成 |
| `key_revoked` | 401 | 密钥已吊销 |
| `key_in_query` | 400 | 密钥出现在 URL 里，改用请求头 |
| `missing_credentials` | 401 | 缺 `username` / `password` |
| `invalid_credentials` | 401 | 用户名或口令错（不区分哪种错，防枚举） |
| `auth_throttled` | 429 | 同一 IP 凭据错误次数过多，按 `retry_after` 退避 |
| `insufficient_scope` | 403 | 这把密钥没有该端点需要的 `read` / `write` 作用域 |
| `rate_limited` | 429 | 超过配额，按 `retry_after` 退避 |
| `insecure_transport` | 403 | 明文 HTTP 被拒，见 1.1 |
| `api_disabled` | 503 | 后台关了总闸 |
| `invalid_param` | 422 | 参数缺失/非法，带 `field` 说明是哪个字段 |
| `method_not_allowed` | 405 | 写端点用了 GET |
| `confirm_required` | 428 | 危险操作需要显式 `confirm=1` |
| `not_found` | 404 | 目标记录不存在 |
| `unknown_endpoint` | 404 | 没有这个端点 |
| `invalid_software` / `invalid_mirror` / `invalid_notice` / `invalid_base_url` | 422 | 具体校验没通过，`message` 里是人话，可能带 `errors[]` |
| `mirror_not_learned` / `mirror_mismatch` | 422 | 镜像站格式没学会 / 模板与样例链接映射不上（见 `mirrorSave`） |
| `not_pending` / `already_handled` | 409 | 这条提交已被处理（并发或重复点击） |
| `category_in_use` | 409 | 分类下还有软件，先迁移 |
| `admin_target` / `self_target` | 403 | 目标是管理员账号，或就是操作者自己（`userPunish`） |
| `invalid_user_id` / `invalid_permission` / `invalid_period` / `invalid_reason` / `empty_punish` / `invalid_badge` / `too_many_badges` | 422 | 用户处罚与身份标签的校验没过，`message` 里说明缺哪一项、合法取值是什么 |

### 2.3 限流（固定窗口，按密钥前缀计，不按站点全局计）

| 场景 | 配额 |
| --- | --- |
| 读端点 | 每把密钥 120 次 / 分钟 |
| 写端点 | 每把密钥 30 次 / 分钟 |
| 凭据错误 | 每 IP 10 次 / 10 分钟 |

被拒的请求（作用域不对、凭据错）**同样消耗配额**，所以拿只读密钥去探测写端点也会被限流。
多实例部署时计数存在本机 `sys_get_temp_dir()/softnav_rate`，需要换成共享存储（Redis）才能全局生效。

### 2.4 分页

列表端点接受 `page`（从 1 开始）与 `per_page`（默认 20，上限 100），返回值放在 `meta` 里：`page` / `per_page` / `total`。

---

## 3. 端点参考

下表里的“参数”都放在 JSON body（GET 端点也可用查询串）。`username` / `password` 每个端点都要，之后不再重复。

### 3.1 读端点（需要 `read`）

| 端点 | 参数 | 返回 `data` |
| --- | --- | --- |
| `ping` | — | `site_name`、`server_time`、`schema_version`、`granted_scopes` |
| `overview` | — | 各项统计：`software_total` / `software_published` / `software_pending` / `category_total` / `comment_pending` / `correction_pending` / `report_pending` / `resource_pending` / `user_total` / `api_keys_active` / `api_calls_total` / `api_calls_denied` |
| `softwares` | `q`、`category_id`、`status`、`featured_only`、`page`、`per_page` | 软件数组（`q` 同时匹配名称/特别说明/标签/开发者，与前台搜索同一套条件） |
| `software` | `slug` 或 `id` | 单条软件 + `channels[]`（下载渠道）、`comments[]`、`rating_distribution`、`resources[]`、`related[]`、`github`（解析出的 owner/repo） |
| `categories` | — | 分类数组，含每类软件数 |
| `pending` | — | 待审核提交列表 |
| `comments` | `page`、`per_page` | 全站评价（含待审） |
| `reports` | `status`、`type` | 举报列表，`meta.stats` 带统计 |
| `corrections` | — | 待处理的资料修正申请 |
| `mirrors` | — | GitHub 镜像源全量（含停用） |
| `notices` | — | 公告全量 |
| `users` | `q`、`page`、`per_page` | 注册用户（**不含**任何口令字段） |
| `words` | — | 敏感词列表 |
| `settings` | — | 白名单内的设置项；凭据类字段返回占位符 `●●●已设置●●●` 与 `<key>_set` 布尔值 |
| `resources` | `software_id`（可选）、`status`（默认 pending） | 第三方资源/教程待审列表 |
| `logs` | `limit`（≤200） | **本把密钥**的最近调用记录 |
| `aiStatus` | — | AI 可用性自检：总开关、共几条线路/其中几条「已启用且填了密钥」、工具与联网检索开关、本次会用的搜索后端、总预算与重试次数、最近 7 天调用数与失败数、最近三条失败原因。「资料全是占位内容」时先用它判断是配置问题还是模型问题 |

```bash
curl -s "https://nav.example.com/index.php?r=api/v1/softwares" \
  -H "X-Api-Key: nav_..." -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"...","status":"pending","per_page":50}'
```

### 3.2 写端点（需要 `write`，且只接受 POST/PUT/PATCH/DELETE）

| 端点 | 参数 | 说明 |
| --- | --- | --- |
| `softwareSave` | `id?`（无 `id` 即新建）、`name`*、`icon_url`*、`official_url`*、`github_url`、`category_id`、`special_notes`、`description_md`、`status`(`pending`/`published`/`rejected`/`offline`)、`is_featured`、`price_type`、`price_desc`、`is_open_source`、`license`、`developer`、`publisher`(≤100 字)、`tags`、`screenshot_url`、`slug`(小写字母/数字/连字符，≤150，撞车报 `invalid_software`)、`other_urls`（渠道 JSON 或 `channel_url[]`+`channel_type[]`+`channel_name[]`） | 与后台网页**同一套校验**（链接白名单、状态枚举、必填项）。返回 `{id, created}`。**字段语义见下**；`summary`（一句话简介）已改为派生列，写了也不采纳 |
| `softwareDelete` | `id`*、`confirm=1`* | 不可逆，必须显式 `confirm=1`，否则 `428` |
| `review` | `id`*、`action`*(`approve`/`approve_ai`/`reject`)、`review_note`（驳回必填）、`category_id`、`special_notes`、`description_md` | 审核游客提交。与后台同一条实现：条件更新防并发重复审核、绑定归属人并发站内信、驳回时生成一次性修改链接、按 SMTP 设置发信。返回 `{id,status,notification,edit_link_token}`。**`approve_ai` 会当场调用 AI**（先认领并发布，再补全，所以超时被掐也不会丢发布），响应里的 `ai_note` 说明这次用了哪条线路、补了几个字段 |
| `correctionReview` | `id`*、`action`*(`approve`/`reject`)、`review_note`（驳回必填） | 资料修正申请审核 |
| `reportUpdate` | `id`*、`status`*(`pending`/`reviewing`/`resolved`/`rejected`)、`review_reason` | 举报处理 |
| `commentDelete` | `id`* | 删除评价 |
| `userPunish` | `user_id`*、`permanent`、`banned_until`(`2026-10-05` 或 `2026-10-05 18:00:00`)、`ban_reason`(≤200 字)、`restricted_permissions`(数组，取值 `comment`/`submit_software`/`submit_resource`/`correction`/`report`) | 封禁或限制操作权力，并给用户发站内信。三条硬规则与后台网页一致：**管理员账号不可被处罚**（否则没人能在页面上解开你）、`permanent`/`banned_until`/权限项**至少给一个**（啥都不选等于误发一条「你被限制了」的通知）、临时封禁的到期时间必须能解析且晚于当前时间。返回的是**落库后的实际状态**（`is_banned`/`banned_until`/`ban_reason`/`restricted_permissions`/`badges`/`status`），不是你提交的那份 |
| `userUnpunish` | `user_id`* | 解除全部封禁与权限限制，`status` 复位为 `active`，同样发通知 |
| `userBadgeSave` | `user_id`*、`badges`（数组，或后台表单那种英文逗号字符串） | 覆盖式设置身份标签：每个 ≤20 字、最多 5 个，空项与重复自动去掉，传空即清空。返回落库后的标签 |
| `aiGenerate` | `software_id`*、`overwrite`(`1`/`true`) | **当场**为这条软件跑一次 AI 补全并写库，与后台「重新生成」按钮、审核时的当场补全同一份实现。默认只补仍空着的字段；`overwrite=1` 先按 `ai_generated_fields` 清掉上次 AI 生成的文本字段再整段重写（分类不清）。返回 `{source, provider, attempts, filled, cleared, note}`；`source` 为 `ai`（线路真的答了）/ `offline`（本地规则兜底）/ `none`（没有空缺要补）。同步外呼，最长约 `ai_total_timeout` 秒，调用方的读超时要设得比它大 |
| `categorySave` | `id?`、`name`*、`slug`、`icon`、`sort_order` | 分类新增/更新 |
| `categoryDelete` | `id`* | 分类下仍有软件时返回 `409` |
| `mirrorSave` | `id?`、`name`、`url_template`、`type`*(`download`/`web`/`both`)、`sample_url`、`original_url`、`url_style`(`prefix`/`replace`)、`is_active`、`sort_order` | **类型必须由调用方定**，系统不替你猜。只给 `sample_url`（+ 可选 `original_url`）时，系统反推模板并自证：用模板把原始链接映射一遍，逐字符等于你贴的那条才保存。学不会返回 `mirror_not_learned`，模板与样例对不上返回 `mirror_mismatch` |
| `mirrorDelete` | `id`* | |
| `noticeSave` | `id?`、`title`*、`content`*、`is_active`、`sort_order` | |
| `noticeDelete` | `id`* | |
| `wordSave` | `words`（字符串或数组）、`replacement` | 批量新增敏感词，返回 `{added}` |
| `wordDelete` | `id`* | |
| `settingsSave` | 白名单内任意键（见下） | **凭据字段留空表示沿用原值**，不会被清空。可写：站点信息 5 项、logo/favicon/footer、`ai_*`（含 `ai_retries`/`ai_total_timeout`/`ai_tool_use`/`ai_tool_rounds`/`ai_page_chars`/`ai_respect_robots`/`ai_courtesy_delay`）、`search_tavily_key`/`search_brave_key`/`search_serper_key`/`search_firecrawl_key`（只写不读）、`captcha_*`、`google_ads_*`、`smtp_*`、`site_base_url`、`google_one_tap`、三组 OAuth `client_id`/`client_secret`、`allowed_email_domains`、5 个 `require_login_*`。不可写：`api_enabled`、`api_allow_insecure`、管理员口令 |

**软件字段的两种语义**（`softwareSave`，`SoftwareWriteService::build()`）：

- **全量覆盖**：`name`、`icon_url`、`official_url`、`github_url`、`category_id`、`special_notes`、`description_md`、`status`、`is_featured`、`price_type`、`price_desc`、`is_open_source`、`license`、`developer`、`tags`、`screenshot_url`。这些每次都要重发，**没带就等于写空/默认值**（漏传 `tags` 会把标签清掉）。
- **没提交就不碰**：`publisher`、`slug`、下载渠道（`other_urls`/`channel_url[]`）。带上才改，`publisher` 传空串是「显式清空」，`slug` 传空串是「交回系统按名称生成/保持原值」。
- **派生列，写了也不采纳**：`summary`（列表页那句简介）由 `description_md` 的**第一句话**自动算出（`Markdown::summaryFrom()`：去标题/列表/代码块，取首段，中文按 `。！？` 断句，英文按「句点+空格」断句，≤300 字）。所有写入路径（后台、API、提交、审核补全、资料修正）都在 `Software::create()/update()` 里统一重算，所以**改正文即改简介**，不存在两处不一致。想读它是可以的：`software` / `softwares` 里都返回。
- **已移出白名单**：`github_release_keyword`。该列目前没有任何代码读取，接了参数只会让 App 以为改了 Release 文件过滤生效；要恢复功能先把它接回 `GithubService`。

**「读出来直接改回去」是安全的**（App 编辑详情的推荐姿势）：

1. `GET /software`（`id` 或 `slug`）→ 返回整行 + `channels[]` + `comments[]`/`resources[]`/`related[]`/`github`/`rating_distribution`。上面 19 个可写字段**一个不缺**都能读到（测试组 55 逐列比对锁住了这点）。
2. 只改你要改的那个值，其余字段**原样回传**，把 `channels[]` 直接当 `other_urls` 提交即可（结构不用重抄）。
3. 这样提交回去是**幂等**的：实测 19 列零漂移，渠道 URL 一条不丢。
4. 若只想改个别字段且不改其它列，用「没提交就不碰」那三个（`publisher`/`slug`/渠道）单独提交即可；但**全量覆盖那些字段必须一起带上**，漏哪个就清哪个。`summary` 不用你管，改了 `description_md` 它就跟着变。
5. 响应里永远不含 `submitter_email`、`submitter_ip`、`edit_token_hash`、`edit_token_expires_at`、`notification_error`（它们也不可经 API 写）。

```bash
# 审核通过一条待审提交并归入分类 3
curl -s -X POST "https://nav.example.com/index.php?r=apiadmin/review" \
  -H "X-Api-Key: nav_..." -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"...","id":123,"action":"approve","category_id":3}'

# 驳回（必须给理由，同时会给提交者生成一次性修改链接并发信）
curl -s -X POST "https://nav.example.com/index.php?r=apiadmin/review" \
  -H "X-Api-Key: nav_..." -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"...","id":123,"action":"reject","review_note":"官网链接打不开，请换成有效地址"}'
```

---

## 4. App 端接入建议

1. **不要把口令写进客户端安装包**。密钥可以随配置下发，但口令必须由登录者自己输入（本站管理员只有一个账号，一次登录一次输入而已，不需要 OAuth 那套）。
2. 会话内可以把口令留在内存里复用，**不要落盘**（iOS Keychain / Android EncryptedSharedPreferences 除外）。
3. 收到 `401 invalid_credentials` 不要自动重试；收到 `429` 按 `error.retry_after` 退避；`409 not_pending` 说明这条已被别人审完，刷新列表即可。
4. 一切列表都用 `page` / `per_page`，别一次拉全量。
5. 时间请用 `overview`/`ping` 返回的 `server_time` 对齐，别信客户端时钟。
6. 出问题时先查后台「开放 API 密钥」页的最近调用表，或调 `logs` 端点 —— 里面能看到端点、方法、结果、HTTP 码与来源 IP（**不含参数**，所以不会泄露口令）。

## 5. 尚未通过 API 开放的能力

| 能力 | 原因 |
| --- | --- |
| 生成/吊销 API 密钥 | 故意不做：见 1.2，密钥不能自我签发 |
| 修改管理员口令 | 属凭据变更，只允许后台会话操作 |
| AI 连通性测试 `testAiProvider`、发信测试 `testMail`、镜像实时测速 `probeMirror` | 属诊断类，会产生外网请求，建议留在后台手工触发 |
| 公告/评论的前台展示逻辑修改 | 已覆盖增删，未覆盖排序批量操作 |

需要上面这几项的话说一声，按同一套模式补齐（抽 service + 加端点 + 加用例）。

## 6. 变更记录

| 版本 | 变化 |
| --- | --- |
| v1（结构基线 v15） | 首版：31 个端点、密钥 + 用户名 + 口令三件套、HTTPS 强制、按密钥限流、调用日志 |
| v1（端点扩充，结构基线 v16） | 镜像源改成「类型由人选 + 真实链接自证学格式」；`softwareSave` 接通 `summary`/`publisher`/`slug`；新增 `userPunish`/`userUnpunish`/`userBadgeSave`（31→34），处罚与标签规则抽进 `UserAdminService`，后台网页与 API 同一份 |
| v1（结构基线 v17） | 新增 `ai_logs` 表与后台「AI 调用日志」面板（线路、耗时、token、失败原因）；`summary` 从可写字段改为 `description_md` 首句派生；设置新增 `admin_email`；发信错误文案点名缺哪一项并写服务器日志 |
| v1（结构基线 v18） | **AI 补全改为异步队列**：新增 `ai_jobs` 表、`core/AiQueue.php`、`deploy/ai-worker.php`（cron）、后台【AI 队列】页与 `aiQueue`/`aiRetry` 两个端点（34→36）。提交与 `review=approve_ai` 不再在请求里调 AI；静态兜底默认值移到 AI 之后执行（先占位会让 AI 永远填不进空字段）。**联网能力重做**：`core/WebSearch.php`（Tavily/Brave/Serper 官方接口优先 + DDG/Bing 免 Key 兜底，DOMXPath 解析、解跳转、去重、下载站降权）与 `core/WebFetcher.php`（自洽浏览器指纹、gzip、cookie、Referer、robots.txt、同主机间隔），抓取仍然全部经 `SafeHttp`；`AiService` 支持 OpenAI tools（模型自己 `web_search`/`open_page`/`github_readme`），线路不认 tools 时自动退回服务端预取；`ai_logs` 新增 `tool_rounds`/`tool_calls`，`softwares` 新增 `ai_sources`（引用来源）。设置新增 `ai_retries`/`ai_total_timeout`/`ai_tool_use`/`ai_tool_rounds`/`ai_page_chars`/`ai_respect_robots`/`ai_courtesy_delay`/`ai_async_inline`/`ai_job_attempts` 与三个 `search_*_key` |
| v1（结构基线 v19） | **撤掉异步队列**：删 `ai_jobs` 表、`core/AiQueue.php`、`deploy/ai-worker.php`、后台【AI 队列】页与设置项 `ai_async_inline`/`ai_job_attempts`，端点 `aiQueue`→`aiStatus`、`aiRetry`→`aiGenerate`（总数仍 36，读 17 / 写 19）。AI 补全改回**审核通过时当场生成**，提交软件时不调用；后台「重新生成」（`AI` = 只补空缺，`覆盖` = 清掉上次 AI 文本字段重写）当场执行。`ai_total_timeout` 默认从 45s 压到 **20s**，因为现在是同步等待，必须小于 php-fpm 的 30s 执行时限。保留的部分：`ai_retries` 同线路重试、多线路容灾、联网搜索与 tools、引用来源 `ai_sources`、审核顺序「先发布后补全」。需要 worker / cron 的运维前提一并取消 |
| v1（功能批次 25–26，结构基线仍 v19） | 详情页「资料来源」卡（`softwares.ai_sources` 渲染，输出侧重新校验）+ **四项行为变化，不改表结构**：① README 现在会渲染图片 —— `GithubService::simpleMarkdownToHtml()` 重写成「先按白名单净化裸 HTML，再做 markdown 解析」（DOMDocument，标签/属性双白名单、`on*` 全删、`<a>` 强制 `rel="nofollow noopener noreferrer"`），仓库相对路径与 `<img width=...>`、`<div align="center">` 徽章墙都能出图；配套补上 `.github-readme-rendered` 一套 CSS（此前这些类没有任何样式）与 `public/assets/images/default-app.svg` 占位图。② **提交软件时图标可以不填**：`core/Favicon.php` 去官网抓它自己声明的 icon / apple-touch-icon，没有再试 `/favicon.ico`，3 秒预算、按域名缓存 7 天，抓不到用站内占位图且不打回提交；抓取仍全部经 `WebFetcher`/`SafeHttp`（robots.txt + 公网地址校验）。③ 搜索后端加 **Firecrawl**（`search_firecrawl_key`，只写不读），它会连结果页正文一起返回，命中时省一次开网页。④ **Google One Tap**：配了 `oauth_google_client_id` 就默认对未登录访客弹一次账号卡，开关 `google_one_tap`，每会话一次 + 可关 7 天；前提是 Google 控制台把站点根地址加进「已授权的 JavaScript 来源」。另外 `SafeHttp::get()` 返回值新增 `final_url`（跳转后的真实地址），`WebFetcher::stateDir()` 转为公开。端点总数仍 36 |
