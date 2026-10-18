# 蓝屏钙软件导航网 - TODO 任务清单与迭代路线

## 架构与已实现特性清单 (v1.0.0 已全部交付)

### 一、 数据库与核心底层架构
- [x] **高性能数据库驱动支持**：支持 MySQL 生产库 (InnoDB + 复合索引优化)，同时支持 SQLite 零配置快速启动与平滑热切换。
- [x] **大容量网站索引优化**：
  - [x] 对 `softwares` 表的 `(status, is_featured, created_at)`、`(status, category_id)`、`(status, views_count)` 建立高性能复合索引。
  - [x] 优化海量数据场景下的高效随机抽样 (`RANDOM()` / `RAND()`)。
- [x] **MVC 模块化分层**：
  - [x] `core/`: Database (PDO), Router, View (XSS 防护与模板引擎), Auth (Session 鉴权与安全), FluentIcon (微软矢量图标渲染)。
  - [x] `models/`: Software, Category, Comment, Report, GithubMirror, Notice, Setting。
  - [x] `controllers/`: HomeController, SoftwareController, SubmitController, AdminController, ApiController。
  - [x] `services/`: AiService (大模型元数据生成), CaptchaService (人机验证), FilterService (敏感词过滤), GithubService (GitHub API 与直链解析)。

### 二、 界面与视觉设计规范 (严格遵循 Frontend-Design Skill)
- [x] **纯净白蓝现代配色**：采用 `#0284c7` (天蓝/极简科技蓝) 与 `#f8fafc` 纯净背景，杜绝 AI 浮夸蓝紫渐变。
- [x] **暗黑模式无缝支持**：支持根据系统偏好自动切换，并提供顶部手动切换按钮，数据持久化于本地并预载消除白屏闪烁。
- [x] **全站禁用 Emoji 图标**：全站统一采用微软开源 [Fluent System Icons](https://github.com/microsoft/fluentui-system-icons) 纯 SVG 矢量渲染。
- [x] **极简克制交互**：绝不在界面编写冗长解释说明，保持高信息密度与功能至上。

### 三、 主页核心功能
- [x] **双视图自由切换**：支持「精美图标网格卡片 (Grid)」与「桌面高效列表 (List)」一键切换，偏好记忆于 Cookie。
- [x] **随机推荐抽取与快速排序**：支持随机抽取、热门访问量、高分好评、最新收录排序。
- [x] **分类导航侧边栏**：分类与包含软件数实时统计，支持一键筛选。
- [x] **实时搜索与自动补全**：输入即时通过 API 检索软件名与简介，提供下拉联想。
- [x] **公告横幅系统**：顶部公告简洁单行显示，点击可呼出详情弹窗，支持关闭。
- [x] **右下角悬浮提交按钮 (FAB)**：点击快速呼出主页提交弹窗。

### 四、 游客软件提交
- [x] **主页弹窗与独立页面双入口**：满足不同场景提交需求。
- [x] **严格字段收集与图床引导**：
  - 必填项：软件名称、软件图标 URL (附第三方免费图床指引)、软件官网 URL。
   - 选填项：软件分类、GitHub 仓库地址、Release 关键词 (默认 x64)、其它获取渠道 (网盘/分流站)、Markdown 详细说明。
- [x] **多方案人机安全验证**：支持 Cloudflare Turnstile、hCaptcha、极验 (Geetest) 及轻量级内置算术验证。
   - 所有提交必须填写有效联系邮箱，审核结果通过 SMTP 邮件通知。

### 五、 软件详情页
- [x] **软件元数据展示**：高清图标、标题、分类徽章、简介、星级评分、特别说明 callout。
- [x] **AI 辅助生成标注**：若该软件缺失字段经由大模型补全，展示专属 AI 辅助生成徽章。
- [x] **官方网站与其它渠道直达**：主按钮突出，第三方网盘与镜像分流按钮清晰排列。
- [x] **GitHub 镜像节点自动测速**：
  - 前端 JavaScript 并发对配置的各镜像节点发起微探针测速。
  - 毫秒级延迟实时反馈 (`🟢 35ms` / `🟡 180ms` / `🔴 超时`) 并按速度动态重排。
- [x] **GitHub Release 官方 x64 直链解析**：自动从 GitHub 最新发布版本中筛选匹配 x64 安装包 (.exe/.msi/.zip) 并提供一键直下。
- [x] **用户打分与评价**：支持 1-5 星级打分、昵称、内容、人机验证与敏感违禁词实时过滤屏蔽。
- [x] **多理由举报反馈弹窗**：支持失效链接、恶意病毒、仿冒流氓、版权侵犯等多种理由反馈至后台。
- [x] **同分类相关软件推荐**。
- [x] **软件资料修正提交**：详情页提供完整资料预填表单，支持修正名称、分类、图标、官网、GitHub、收费模式、开源信息、开发者、发布者、标签及截图网址。
- [x] **下载渠道动态维护**：支持添加、删除并修改渠道类型、名称和网址，提交后统一进入审核流程。

### 六、 管理后台系统
- [x] **数据统计大屏**：软件收录总数、待审核提交数、总浏览量、评价数、待处理举报数。
- [x] **软件审核与 1-Click AI 智能生成补全**：
  - 审核时若分类、简介、特别说明或其它渠道缺失，可一键调用 AI 大模型补全并自动打标发布。
  - 兼容 OpenAI / DeepSeek / 通义千问等所有标准 Chat Completions API。
  - 支持未配置 Key 时的智能离线规则保障。
- [x] **全量软件 CRUD 管理**：分页、检索、新增、编辑、删除。
- [x] **GitHub 镜像节点管理**：后台增删改查加速前缀模板。
- [x] **公告管理系统**：支持发布、停用、删除横幅公告。
- [x] **评价审核与违禁词库维护**：删除垃圾广告评论，动态维护敏感词黑名单。
- [x] **举报反馈处理**：核实处理与忽略。
- [x] **系统设置与凭据安全**：SEO 关键词、AI API Key、人机验证密钥、管理员账户密码修改。

### 七、 SEO 与商业化预留
- [x] **SEO 深度优化**：
  - 语义化 HTML5 标签。
  - 动态 Meta Description & Keywords、Open Graph 协议。
  - 详情页注入 Google 结构化数据 (`SoftwareApplication` Schema JSON-LD)。
  - 动态输出 `sitemap.xml` 网站地图与 `robots.txt` 爬虫指引。
- [x] **Google ADS 广告位预留**：低调克制置于底部或预留槽位，开关式控制，绝不遮挡主操作。

---

## 后续版本迭代规划 (Roadmap)

### v1.1.0 计划
- [ ] **Winget / Scoop / Chocolatey 命令行生成**：在详情页增加一键复制 `winget install ...` 快捷安装命令。
- [ ] **软件历史版本归档**：支持记录软件的历史主要大版本更新说明与下载历史。
- [ ] **深色模式快捷键**：支持按键快捷切换 (`Shift + D`)。

### v1.2.0 计划 (千万级数据演进)
- [ ] **全文检索引擎接入**：针对百万级以上软件数据，接入 MeiliSearch 或 ElasticSearch 实现拼音与模糊高容错毫秒检索。
- [ ] **Redis 高速缓存层**：为热门详情页与 GitHub Release 解析结果接入 Redis 缓存。
- [ ] **批量导入与爬虫同步脚本**：支持导入 GitHub Trending 及各大官方 RSS 更新源。
- [ ] **Docker 一键镜像交付**：提供 `Dockerfile` 与 `docker-compose.yml` (含 PHP-FPM + Nginx + MySQL)。
