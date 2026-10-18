# 蓝屏钙软件导航网 (SoftwareNav)

一个专为收集与分发各大软件官方渠道、GitHub 原创源及可靠第三方下载地址而打造的纯净、高效软件导航系统。

---

## 🌟 核心特性

- 🎨 **极简白蓝科技视觉**：基于 `frontend-design` 严苛设计规范，拒绝千篇一律与蓝紫色 AI 渐变，极简克制，专注下载效率。
- 🌗 **无缝暗黑模式**：智能跟随系统首选项自动切换，支持全站手动即时切换，首屏零闪烁。
- ⚡ **无 Emoji，全 Fluent 图标**：全站统一采用微软开源 [Fluent System Icons](https://github.com/microsoft/fluentui-system-icons) 纯矢量 SVG 图标。
- 📦 **双模视图随心换**：支持「精美图标网格卡片」与「高密度列表视图」一键切换，记住用户偏好。
- 🚀 **GitHub 镜像节点自动测速**：根据后台配置的镜像源，前端毫秒级并发实时测速 (`🟢 35ms` / `🟡 180ms`)，自动按延迟排序供用户挑选。
- 🔗 **GitHub Release x64 直链自动解析**：自动解析 GitHub API 最新 Release，智能筛选并提供 Windows x64 架构安装包官方直连加速。
- 🤖 **AI 大模型一键补全与润色**：审核游客提交的新软件时，若分类、简介、特别说明有缺失，可一键调用 AI（支持 OpenAI / DeepSeek / 通义千问等兼容 API）智能补全，并在详情页标注 AI 辅助生成徽章。
- 🛡️ **多元人机安全验证**：支持 Cloudflare Turnstile、hCaptcha、极验 (Geetest) 及轻量级内置算术验证，全站有效防刷。
- 💬 **打分评价与敏感词屏蔽**：支持 1-5 星级打分评价，内置敏感情报词黑名单自动拦截与星号替换。
- 🚩 **多理由违规举报系统**：失效链接、恶意捆绑、假冒软件等支持用户一键反馈至后台。
- ✏️ **软件资料修正审核**：详情页支持预填并修正软件名称、分类、图标、官网、GitHub、开发者、发布者、收费模式、开源协议、标签及下载渠道；提交必须填写有效邮箱，驳回后通过一次性、7 天有效的安全链接修改并重新提交。
- 📝 **Markdown 详细说明**：提交表单使用本地 MIT 许可证 EasyMDE 编辑器，后端先转义再渲染 Markdown，仅允许安全协议链接，避免用户内容注入 HTML/脚本。
- ✉️ **SMTP 审核通知**：后台可配置 SMTP、发件人和站点地址，审核通过/驳回后向提交邮箱发送通知，并持久化发送、跳过或失败状态。
- 📈 **全方位后台控制中心**：数据统计大屏、待审核池、软件资料修正审核、软件库 CRUD、公告横幅系统、敏感词词库、Google ADS 预留广告位管理。
- 🔍 **极致 SEO 优化**：语义化 HTML5 标签、动态 Meta Keywords & Description、Open Graph、JSON-LD `SoftwareApplication` 结构化数据、动态 `sitemap.xml` 与 `robots.txt`。

---

## 🚀 快速启动

本系统采用智能数据库适配设计，无需安装任何额外依赖，可在任意具备 PHP 8.0+ 的环境中**零配置一键运行**：

### 1. 启动本地开发服务

本项目静态资源位于 `public/`，因此启动内置服务器时**必须指定文档根**：

```bash
# 推荐：文档根指向 public，静态资源由服务器原生处理（MIME / Range / 缓存头均正确）
php -S localhost:8000 -t public
```

若习惯直接以项目根启动，必须把根目录的 `index.php` 指定为路由脚本：

```bash
php -S localhost:8000 index.php
```

> ⚠️ 直接执行 `php -S localhost:8000`（既不带 `-t`、也不指定路由脚本）**页面能打开，但所有
> CSS / JS / 图片都会 404** —— 内置服务器不会为缺失的路径回退到上级 `index.php`。

打开浏览器访问：[http://localhost:8000](http://localhost:8000) —— 第一次会看到「站点尚未安装」的引导页。

装机二选一，建出来的库完全一致（同一套 `core/Installer.php`）：

```bash
# A. 命令行（推荐，日志看得见）
php database/install.php

# B. 浏览器打开 http://localhost:8000/install.php 走网页向导
```

> 装机会覆盖掉种子里那个公开口令 `admin123`；装完网页向导自动关闭（再访问返回 404）。
>
> 程序**不再**在第一次访问时自动建库：那样任何人都能靠一次访问把站点装成默认口令，
> 之后向导还会自锁。要重建请删掉 `database/nav.sqlite` 或用 `php database/install.php --force`。

---

### 2. 生产环境部署

完整步骤（上传、文档根、权限、HTTPS、备份、升级）见 **[DEPLOY.md](DEPLOY.md)**。
不需要手工导 SQL、也不需要改 `config/config.php` 里的账号密码：

- **浏览器装机**：上传后打开 `https://你的域名/install.php`，填数据库（SQLite 或 MySQL）、站点信息、管理员账号，装完向导自动关闭。
- **命令行装机**（两条路共用 `core/Installer.php`，建出来的库一致）：

```bash
# SQLite
php database/install.php --base-url=https://你的域名

# MySQL：账号密码写在 config/.env（或环境变量）里，也可让向导替你写
cp config/.env.example config/.env && vi config/.env
php database/install.php --db=mysql --create-database
```

---

## 🔑 管理后台登录

- **后台地址**：`http://localhost:8000/?r=admin/login` (或点击页脚“管理后台”)
- **账号口令**：由装机时决定 —— 网页向导可自动生成（只显示一次），命令行 `php database/install.php` 同样行为，也可 `--admin=… --password=…` 自己指定。
- **重装**：`php database/install.php --force`（会先清空数据）。网页向导装过一次就自锁，之后只返回 404。

> ⚠️ `database/seed.sql` 里的管理员哈希对应公开口令 `admin123`，只用于本地体验。
> 正式部署必须用 `install.php` 覆盖它（`deploy/preflight.php` 会把它当阻塞项报 FAIL）。

*(登录后台后可在【系统与AI设置】中随时修改管理员账号与密码)*

---

## 🚀 上线部署

装库、打包、体检、升级都写在 **[DEPLOY.md](DEPLOY.md)** 里，三条命令：

```bash
php database/install.php      # 建库 + 导种子 + 设管理员口令 + 登记结构版本
php deploy/preflight.php      # 上线体检（退出码 1 表示有必须先处理的问题）
php deploy/package.php        # 生成只含生产文件的部署包 + SHA-256 清单
```

以后改表结构请按 `database/migrations/README.md` 加一条版本化迁移，用 `php database/migrate.php` 升级线上库。

---

## 📱 App / 自动化接入（开放 API）

后台管理能力同时以 JSON API 暴露，供自研 App、桌面端或脚本调用：

```bash
curl -s "https://你的域名/index.php?r=api/v1/overview" \
  -H "X-Api-Key: nav_…" -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"…"}'
```

- 鉴权是**密钥 + 管理员用户名 + 口令**三件套，密钥在后台【开放 API 密钥】里生成（库里只存哈希，明文只显示一次，可吊销）。
- 因为口令每次请求都要上传，**必须走 HTTPS**（本机 127.0.0.1 调试免检；生产不要用 `api_allow_insecure`）。
- 端点清单、错误码、限流与调用日志见 **[API.md](API.md)**。

---

## 📂 项目目录结构

```
软件导航/
├── config/
│   ├── config.php            # 全局配置（读 环境变量 > config/.env > 默认值）
│   ├── .env.example          # 服务器落盘配置模板
│   └── install.lock          # 装机后由向导/安装程序写入的安装标记
├── core/
│   ├── Database.php          # PDO 封装（MySQL / SQLite 双方言）
│   ├── Router.php            # 路由分发 + 全局 CSRF 拦截
│   ├── View.php              # 模板渲染与 XSS 输出净化
│   ├── Auth.php              # 会话、CSRF token、开放重定向白名单
│   ├── Url.php               # 协议白名单与 SSRF 目标校验
│   ├── SafeHttp.php          # 出站请求（逐跳复检 + 证书校验）
│   ├── RateLimiter.php       # 登录/验证码/装机速率限制（本机文件计数）
│   ├── Installer.php         # 装机公共逻辑（CLI 与网页向导共用）
│   └── FluentIcon.php        # Fluent System Icons 纯 SVG 引擎
├── models/                   # Software / Category / Comment / Report / User / Notification / Setting …
├── services/                 # AiService / CaptchaService / FilterService / GithubService / OAuthService / MailService
├── controllers/              # Home / Software / Submit / Api / Admin
├── views/
│   ├── layout/               # 头部（含样式清单）、底部
│   ├── software/parts/       # 详情页分片（hero/main/modal-*/scripts/json-ld）
│   └── admin/                # 控制台、审核、软件库、镜像、公告、设置
├── public/                   # ← web 根必须指到这里
│   ├── index.php             # 前端主入口（未装机时给出「请先安装」引导页）
│   ├── install.php           # 网页安装向导（一次性，装完自锁返回 404）
│   ├── .htaccess             # 关目录列表 + 拦隐藏/数据文件
│   └── assets/css/           # style / store-detail / motion / admin / auth-responsive（按序加载）
├── database/
│   ├── schema_sqlite.sql     # SQLite 完整结构（含索引，= 当前基线）
│   ├── schema_mysql.sql      # MySQL 完整结构（与 SQLite 同构）
│   ├── seed.sql              # 分类、镜像源、公告、基础设置与示例软件
│   ├── install.php           # 安装程序（CLI-only）
│   ├── migrate.php           # 版本化升级执行器（CLI-only）
│   ├── migrations/           # 前向迁移 SQL + 写法说明
│   ├── legacy/               # v2…v13 历史脚本，只给老库补齐用
│   └── version.php           # 结构基线号
├── deploy/
│   ├── preflight.php         # 上线体检
│   ├── package.php           # 生产部署包生成
│   └── *.example             # Nginx / Apache 站点与兜底 .htaccess 示例
├── tests/                    # 回归套件：php tests/run.php（全部跑在临时副本上）
├── tools/dev/                # 开发期诊断脚本（不进部署包）
├── API.md                    # 开放 API 文档（App 接入）
├── DEPLOY.md                 # 部署与升级手册
└── README.md                 # 本文件
```
