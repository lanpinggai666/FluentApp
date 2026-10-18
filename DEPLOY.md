# 部署手册（蓝屏钙软件导航）

适用版本：应用 1.0.0 / 数据库结构基线 v14。装机、体检、升级三条路：

```bash
# 装机：浏览器打开 https://你的域名/install.php 走网页向导（装完自锁）
php database/install.php        # 或者用命令行装，两条路建出来的库一致
php deploy/preflight.php        # 上线体检（退出码 1 = 有阻塞项）
php database/migrate.php        # 以后升级结构时用
```

---

## 1. 环境要求

| 项 | 要求 | 说明 |
| --- | --- | --- |
| PHP | ≥ 8.1（推荐 8.3） | 用到 `match`、只读属性等新语法 |
| 扩展 | `pdo_sqlite` 或 `pdo_mysql`、`curl`、`mbstring`、`json`、`session` | 体检脚本会逐个查 |
| Web | Nginx + PHP-FPM，或 Apache，或 IIS | 无框架、无 composer 依赖，不需要重写规则 |
| 建议 | OPcache、`expose_php=Off`、`display_errors=Off` | 生产三项 |

数据库二选一：**SQLite**（单机、零配置、毫秒级，默认）或 **MySQL 8 / MariaDB 10.5+**（多进程、大索引）。`preflight.php` 对两者都有效。

## 2. 上传代码

用 `php deploy/package.php` 生成部署包（只含运行所需文件，`tests/`、`tools/`、开发库、`config/.env` 都不在内），上传后解压到例如 `/var/www/softnav`：

```bash
tar -xzf softnav-1.0.0-b14.tar.gz -C /var/www/
mv softnav-1.0.0-b14 softnav
```

**web 根必须指向 `public/`**。其余目录（`config/`、`core/`、`database/`、`models/`…）都不该被 HTTP 直接访问。误把文档根设成项目根时，PHP 脚本会被 CLI 守卫挡成 404，但静态文件（含 SQLite 库）会被原样下载 —— 这一步没有商量余地。

权限（Linux，PHP-FPM 用户是 `www-data` 时）：

```bash
chown -R www-data:www-data /var/www/softnav
find /var/www/softnav -type d -exec chmod 750 {} \;
find /var/www/softnav -type f -exec chmod 640 {} \;
chmod 750 /var/www/softnav/database          # SQLite 要写库文件与 -wal/-shm
```

## 3. 配置

复制模板并按机器改：

```bash
cp config/.env.example config/.env
chmod 640 config/.env
```

读取优先级是 **真实环境变量 > `config/.env` > 代码默认值**，所以容器里用 `-e` 注入即可，不必改文件。

用网页向导装机时，`config/` 必须对 web 进程可写（要落 `.env` 与 `install.lock`）；
纯命令行装机则不需要，`.env` 也可以自己写。

- SQLite：想把库放到 web 根之外，设 `DB_SQLITE_PATH=/var/www/softnav-data/softnav.sqlite`（目录需 `www-data` 可写）。
- MySQL：先建库，再填 `DB_CONNECTION=mysql` 与账号密码。别用 root、别留空密码。
- 后台「系统与AI设置」里还有一层数据库里的设置（站点名、SMTP、验证码、AI、镜像源），装机后在后台填。
- **`site_base_url` 一定要填**（`https://你的域名`）。OAuth 回调、审核邮件里的链接、`sitemap.xml` 都以它为准；不填会退回按请求头猜域名，换域名或被探测时会生成错链接。

## 4. 安装

没装机之前打开首页只会看到「站点尚未安装」的引导页 —— 程序**故意不**在第一次访问时自动建库：
那样任何人访问一次就能把站点装成默认口令，之后向导还会自锁。装机有两条路，共用 `core/Installer.php`，
建出来的库完全一样。

### 4.1 网页向导（推荐：不用登服务器）

浏览器打开 `https://你的域名/install.php`，按四段填：站点信息 → 数据库 → 管理员账号 → 初始数据。

- 前提是 **`config/` 目录能被 web 进程写**（向导要落 `config/.env` 与 `config/install.lock`）。
  写不了会在页面上直接告诉你，并让你改用 4.2 的命令行。
- MySQL 那一段填主机/端口/库名/账号/密码，勾上「库不存在时自动创建」即可；账号密码由向导写进 `config/.env`，
  不会留在代码里，也不会回显到页面上。
- 「自动生成强口令」默认勾着，装完那串密码**只展示一次**，当场存进密码管理器。
- 提交成功后向导立刻自锁：再访问 `/install.php` 一律 404，即使有人删掉 `install.lock`，
  只要库里还有数据也照样锁着。确认没问题后建议直接 `rm public/install.php`。
- 想重装只能走命令行 `--force`，网页侧永远不覆盖已有数据。

### 4.2 命令行

```bash
# SQLite
php database/install.php --base-url=https://你的域名

# MySQL（账号密码来自 config/.env 或环境变量）
php database/install.php --db=mysql --create-database --admin=ops --password='足够长的口令'
```

两种都会：环境自检 → 建表（`database/schema_*.sql`）→ 导种子（`database/seed.sql`）→
**覆盖掉种子里对应 `admin123` 的默认口令哈希** → 登记结构版本 v14。
库里已有业务数据时拒绝执行，要重来得加 `--force`（先备份）。种子含 10 条示例软件，不想要加 `--no-seed`。

### 4.3 装完必做

```bash
php deploy/preflight.php --url=https://你的域名    # 退出码 0 才算能上线
```

然后登一次后台，把 SMTP、验证码、AI 线路、GitHub 镜像源补齐。

### 4.4 AI 补全在什么时候跑

- **用户提交软件时不调用 AI。** 提交只把用户填的字段入库、进待审核池 —— 提交的人不该为 AI 等几十秒，
  也没必要在还没过审的资料上烧一次外呼。
- **管理员点「AI 补全后发布」时当场调用**，跑完把结果直接显示在审核结果里（用了哪条线路、第几次应答、补了哪几个字段，
  或者为什么降级成了本地兜底）。一次生成受两个参数约束：`ai_retries`（同一条线路重试几次，默认 3）与
  `ai_total_timeout`（总时长预算，默认 20 秒）；超时、限流、连不上就换下一条线路，所有线路都失败时用内置本地规则兜底，
  详情页不会出现空白。
- **事后重生成**：后台【全部软件】每一行有 `AI`（只补仍空着的字段，人工与审核填过的不动）和
  `覆盖`（先清掉上次 AI 生成的文本字段再整段重写）两个按钮，点了当场跑；开放 API 是 `POST /api/v1/aiGenerate`。
- **等的这 20 秒看得见**：点 `AI` / `覆盖` / 【待审核】的「AI 补全后发布」会弹一张对话面板，
  每 600ms 拉一次这一轮的事件流：轮到哪条线路、模型第几轮要求搜什么、搜到几条、打开了哪个网页、
  耗时与 token、失败为什么换源、最后写了哪几个字段。实现是 `core/AiTrace.php`（临时事件文件，15 分钟自清）
  + `admin/aiTrace` 只读轮询口；跑补全的仍然是原来那一个 POST，**没有 worker、没有 cron、没有流式响应**。
  面板收起不影响结果；JS 不可用时自动退回原来的 302 + 横幅。
  注意：面板里**看不到提示词正文**，也不会有任何密钥 —— 事件写的是临时文件，共享主机上别的进程也可能读到，
  所以只记形状（线路、轮数、工具名、字数、字段名）。要查逐次外呼的 HTTP 与 token，用后台【AI 调用日志】。
- 没有 worker、没有 cron、没有常驻进程 —— **这套流程不需要你能登服务器**。

顺序上有一条保证：**先发布，后补全**。审核走的是「条件更新认领 → 改成 published → 再调 AI」，
所以就算 PHP 在执行 AI 途中超时被掐断，软件也已经发布了，不会把提交丢掉。

`ai_total_timeout` 必须小于 php-fpm 的 `max_execution_time` 与 Nginx 的 `fastcgi_read_timeout`
（默认 30 秒，所以预算默认 20 秒）。`php deploy/preflight.php` 会检查这条，并一并检查：
AI 总开关是否开着、有没有「已启用且填了密钥」的线路（**密钥框只写不读**，页面上看着三条线路都在、
实际一个密钥都没有，是"资料全是占位内容"最常见的原因）、以及人机验证是否被设成 `none`
（`none` 时服务端直接放行，验证码等于没装）。

联网这块有两个建议：① `ai_tool_use=1`（默认开）让模型自己决定搜什么、开哪个网页，线路不支持 tools 时会自动退回
服务端预取，不会因此失败；② 在设置里填一个搜索 API Key（Tavily / Brave / Serper / Firecrawl 任一，
四家按「填了 Key 就优先」排序，Firecrawl 还会把结果页正文一起带回来，命中它就不必再开一次网页）。
不填也能用，但免 Key 的 DuckDuckGo/Bing 结果页属于「抓公开网页」，会受对方限流与改版影响；
官方接口是给程序用的，稳定得多。抓取层默认遵守 robots.txt 并在同一主机之间留 1.2–1.9 秒间隔，
这是为了少被判成爬虫，也为了不给人家添麻烦 —— 代码里没有代理池、打码或绕过登录墙的机制，也不要加。

### 4.5 提交时的两件自动事

- **图标**：提交表单的「软件图标链接」现在可以不填。留空时服务端去官网抓它自己声明的图标
  （首页 `<link rel="icon|apple-touch-icon">`，都没有再试 `/favicon.ico`），总预算 3 秒、按域名缓存 7 天，
  抓不到就用站内占位图 `assets/images/default-app.svg`，**不会因为抓不到而打回提交**。
  这一步同样走 robots.txt 与公网地址校验（内网、`169.254.169.254`、非 80/443 一律不发）。
- **Google One Tap**：配了 `oauth_google_client_id` 就默认开启，未登录访客进站会在角落弹一次 Google 账号卡
  （登录框里那个「Google 登录」按钮不受影响）。要在 Google 控制台把 **`site_base_url` 的根地址**加进
  「已授权的 JavaScript 来源」，否则浏览器直接不回凭证、弹窗一次都不出。产品层面做了三道抑制：
  每个会话只弹一次、用户点「不想知道」后 7 天不打扰、站内登录弹层开着时不抢。
  后台【系统与AI设置】的「One Tap 自动弹窗」可以整块关掉。
  注意浏览器全面禁用第三方 Cookie 后 One Tap 会失效（Google 侧的 `not_allowed_to_ask`），这不是本站的 bug。

## 5. Web 服务器

### Nginx + PHP-FPM

照抄 `deploy/nginx.conf.example`。要点：`root /var/www/softnav/public;` + `try_files $uri $uri/ /index.php$uri$is_args$args;` + `location ~ /\.` 拒绝隐藏文件（`config/.env` 正在其中）。示例里已经带了伪静态规则（见 5.1），不开启这个功能也不影响原来 `?r=控制器/动作` 的用法。

### 5.1 伪静态：`https://example.com/software/a`

**全站链接都只从一个口子生成**：`core/Url.php` 的 `route()` / `category()` / `detail()` / `target()`，所以地址形态是一个开关决定的，不是散在各模板里改出来的。

**开关**：后台【系统与AI设置】→「URL 形态」，设置键 `url_rewrite`，**默认开启**。
关掉即刻全站回到 `index.php?r=控制器/动作` 形态；两种形态始终都可达，开启状态下访问旧形态地址会自动 301 到伪静态地址。

覆盖到的链接：详情页 `/software/<slug>`、分类页 `/category/<slug>`、`/submit`、`/admin/*`、`/user/*`、`/auth/*`、分页与筛选（`/admin/reports?status=pending`）、站内信与邮件里的详情链接、`sitemap.xml`，以及所有 `header('Location: …')` 跳转（评论、举报、审核、后台保存之后地址栏不会再挂 `?r=`）。

- **Nginx**（`deploy/nginx.conf.example` 已内置，直接抄）：

  ```nginx
  location / {
      try_files $uri $uri/ /index.php$uri$is_args$args;   # /software/a → /index.php/software/a
  }

  location ~ ^/(index|sitemap)\.php(/|$) {
      include fastcgi_params;
      fastcgi_split_path_info ^(.+?\.php)(/.*)$;
      fastcgi_pass unix:/run/php/php8.3-fpm.sock;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      fastcgi_param PATH_INFO $fastcgi_path_info;
      fastcgi_param REMOTE_ADDR $remote_addr;
      fastcgi_read_timeout 30s;
  }
  ```

  改完 `nginx -t && systemctl reload nginx`。

- **Apache**：`public/.htaccess` 里「不存在的路径交给 index.php」那条已经够了，只要 `AllowOverride All` 生效、`mod_rewrite` 已启用，不需要额外规则。
- **IIS**：URL Rewrite 加一条 `^software/(?<slug>[^/]+)/?$` → `index.php/software/{R:1}`，思路与 Nginx 相同。
- **本地 `php -S` 预览**：内置服务器会把找不到的路径退给 `index.php`，实测 `/software/<slug>`、`/admin/users` 这类形态在 `php -S -t public` 下同样能打开，所以本地调试不需要额外处理。

行为说明：

1. 开关决定**全站**链接形态。若你的服务器规则比较特殊（比如只放行了 `?r=`），取消勾选即可整体退回旧形态，不用改代码。
2. 旧形态 `index.php?r=software/view&slug=x` 在开启状态下**自动 301** 到 `/software/x`，既有的收藏与外链不会留下重复内容；关掉开关后伪静态地址照常可达，只是站内不再生成新的。
3. slug 允许中文，地址里是百分号编码的，`core/Router.php` 会先解码再查库；子目录部署（装在 `/nav/`）前缀取 `site_base_url` 或当前脚本目录，注意别在 `site_base_url` 里漏掉或多写斜杠（历史上 `//index.php?...` 被浏览器当成协议相对地址跳到别人域名，就是这个拼法错了）。
4. 验证规则到位没有：跑 `php deploy/preflight.php --url=https://你的域`，体检里有「伪静态地址可直达」一项；服务器没配规则又开着开关，这一项会直接 FAIL 提醒。


### Apache

文档根指到 `public/` 即可，`public/.htaccess` 已关掉目录列表并拦住隐藏文件。

### 改不了文档根的主机（宝塔/共享主机）

把 `deploy/apache-root.htaccess.example` 复制成项目根的 `.htaccess`，它会把所有请求改写进 `public/`，并直接拒绝 `config/`、`database/`、`core/` 等目录。这只是兜底，能改文档根就改。

### IIS

网站物理路径指到 `public/`，`web.config` 里把应用池身份设为对该目录有写权限；SQLite 库在目录外时同样要给写权限。

## 6. HTTPS

证书自己在 Nginx/Apache 上配（Let's Encrypt 也行）。上线后把 `site_base_url` 改成 `https://`，并加上 HSTS：

```nginx
add_header Strict-Transport-Security "max-age=31536000" always;
```

启用 OAuth 快捷登录时，第三方控制台里的回调地址必须与 `site_base_url` 完全一致（含协议与结尾斜杠与否）。

## 7. 备份

- SQLite：**别直接 cp 单独的 .sqlite 文件**（WAL 下最新数据在 `-wal` 里）。用 `sqlite3 库文件 ".backup /备份路径"`，或先 `PRAGMA wal_checkpoint(TRUNCATE)` 再打包 `.sqlite`、`-wal`、`-shm` 三个文件。
- MySQL：`mysqldump --single-transaction --default-character-set=utf8mb4 库名 > softnav-$(date +%F).sql`。
- 同时备份 `config/.env`（丢了要重填账号密码）与后台设置里的密钥。

## 8. 以后怎么升级

结构变更走版本化迁移，流程写死在 `database/migrations/README.md` 里：改两份 schema → `database/version.php` 基线 +1 → 加一个 `NNNN_*.sql` 增量。

线上升级顺序：

```bash
# 1. 备份（见上一节），并记下当前 MANIFEST.txt
# 2. 覆盖代码：包里没有 config/.env 也没有数据库，解压覆盖不会冲掉服务器配置
tar -xzf softnav-1.1.0-b15.tar.gz -C /var/www/
rm -rf /var/www/softnav.old
mv /var/www/softnav /var/www/softnav.old
mv /var/www/softnav-1.1.0-b15 /var/www/softnav
cp /var/www/softnav.old/config/.env /var/www/softnav/config/.env    # .env 放在代码目录里时要带过来
# 3. 只跑增量结构
php database/migrate.php --dry-run      # 先看要执行什么
php database/migrate.php                # 含 DROP/TRUNCATE 时需要 --yes
# 4. 体检 + 冒烟
php deploy/preflight.php --url=https://你的域名
```

**PHP 装在 Docker 里、进不去命令行？** 登录后台点【结构升级】（`?r=admin/migrate`）就行：同一份执行逻辑
（`core/Migrator.php`），页面上能看将要执行的 SQL、一键补齐待处理迁移、顺手同步 `config/install.lock` 里的版本号。
门槛是「已登录后台 + CSRF」，含 DROP/TRUNCATE 的迁移必须再勾一次确认框；页面上没有任何输入能拼进 SQL
（要跑的语句只来自 `database/migrations/*.sql`）。**别在 web 根放一个裸的 `update.php`** ——
那等于让未登录的人改表结构，本站所有写路径都有会话门槛，这条不能开例外。

容器部署另外两条要紧的：
1. 数据库文件必须在卷上（`DB_SQLITE_PATH=/var/www/softnav-data/...`，目录对 www-data(uid 33) 可写），
   否则每次重建镜像等于把库删了，表现就是又让你「重新安装」。
2. `config/.env` 不必进镜像，直接注环境变量即可（`DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` /
   `DB_USERNAME` / `DB_PASSWORD` / `DB_SQLITE_PATH` / `APP_ENV` / `APP_DEBUG`；优先级：环境变量 > `config/.env` > 默认值）。
   `config/install.lock` 丢了也不致命：库里已有数据时网页向导照样自锁，体检只会给一条 WARN。
   错误日志走 `error_log`，容器里用 `docker compose logs app` 看（SMTP/AI 的失败原因都在那里）。

如果 SQLite 库还在代码目录的 `database/` 里（没按第 3 节挪到 `/var/www/softnav-data/`），
上面的 `mv` 会把库一起挪走 —— 记得把 `nav.sqlite*` 放回新目录。这正是建议把 `DB_SQLITE_PATH`
指到代码目录之外的原因：升级时代码可以被整体替换，数据不行。

**不要**在已有库上重跑 `database/install.php`（那会重建全部表）；老库（v13 及更早）先按 `database/legacy/README.md` 补齐，再 `php database/migrate.php --stamp=baseline`。

回滚：数据库用第 7 节的备份还原，代码解压回上一个包 —— 用 `MANIFEST.txt` 里的 SHA-256 校验上传是否完整、有没有被改动过。

## 9. 多实例与反向代理

限流与会话目前按**本机文件 + 单机 PHP 会话**实现：

- 站点在**反向代理后面**时，`REMOTE_ADDR` 变成代理 IP，登录限流会把所有访客当同一个人（很快就集体 429）。需要让 PHP 看到真实客户端 IP（Nginx 配 `fastcgi_param REMOTE_ADDR $remote_addr` 并让代理透传），或者只在前一层做速率限制。
- 多实例部署要把会话与限流计数放到共享存储（Redis 之类）——目前 `core/RateLimiter.php` 是本机文件计数，接口已统一，换后端成本很低。
- 代码**不读取** `X-Forwarded-For`/`X-Forwarded-Host`（伪造它们就能绕过限流、污染回调地址），所以别让「信任转发头」这件事悄悄发生。

## 10. 开放 API（App 接入）

后台能力（审核、软件库、镜像源、公告、评论、举报、修正申请、设置等）同时以 JSON API 暴露，
端点、错误码、限流与示例见 **[API.md](API.md)**。部署时要知道的三件事：

1. **鉴权是「密钥 + 管理员用户名 + 口令」三件套**，密钥在后台【开放 API 密钥】生成，只存哈希、只显示一次、可吊销。
2. 因为口令每次请求都要上传，**站点必须上 HTTPS**。`deploy/preflight.php` 会检查有没有人把
   `api_allow_insecure` 打开（打开了就报 FAIL）。
3. 结构升级后记得 `php database/migrate.php`：API 的密钥表（v15）就是靠它补上的，
   没补上时体检会直接告诉你「代码已经有开放 API，但库里没有对应表」。

不需要 App 接入的话，把后台那一页的总闸 `api_enabled` 关掉即可，所有端点统一返回 503。

## 11. 常见故障

| 现象 | 先看 | 处理 |
| --- | --- | --- |
| 首页显示「站点尚未安装」 | 还没装机 | 打开 `/install.php` 走向导，或 `php database/install.php` |
| 向导提示 config/ 不可写 | `config/` 属主与权限 | 给 web 进程写权限，或改用命令行装机后再手工写 `config/.env` |
| 向导打不开 / 返回 404 | `config/install.lock` 或库里已有数据 | 说明已经装过。要重装用 `php database/install.php --force`（先备份） |
| 页面白屏 500 | PHP-FPM/Apache 错误日志、`php -l` | 生产不回显堆栈，日志里才有详情 |
| CSS/JS 全 404 | 文档根是否 `public/` | 见第 2 节 |
| 详情页偶发 500/忙 | `PRAGMA journal_mode` 是否 wal | 库目录不可写时 WAL 建不起来，`preflight` 会报 |
| 登录提示次数过多 | `sys_get_temp_dir()/softnav_rate` | 反代后的共 IP 所致，见第 9 节 |
| 提交/审核 400 | CSRF token 是否随表单一并提交 | 页面被缓存、或反向代理吞掉 Cookie 时会这样 |
| OAuth 回调报 state 无效 | `site_base_url` 与第三方后台回调地址 | 必须逐字符一致 |
| Google One Tap 一次都不弹 | Google 控制台的「已授权的 JavaScript 来源」、`google_one_tap` 开关 | 来源里必须有 `site_base_url` 的根地址；浏览器禁第三方 Cookie 时 Google 侧直接拒（`not_allowed_to_ask`，控制台能看到原因） |
| 收到邮件里链接是 localhost | `site_base_url` 未填 | 填成正式域名 |
| 升级后页面报错 | `php database/migrate.php` 是否有未执行项 | 结构没跟上代码 |
| 审核点「AI 补全后发布」后要等十几秒 | 后台【AI 调用日志】面板、`ai_retries` / `ai_total_timeout` | 这是当场生成，等的是外呼本身。预算默认 20s，php-fpm 的 `max_execution_time` 与 Nginx 的 `fastcgi_read_timeout` 必须大于它；所有线路都失败时会用本地规则兜底，不会白屏也不会丢发布（先发布后补全） |
| 软件资料是「XX 官方团队」「标准安装包 / 免安装便携版」这类占位内容 | `php deploy/preflight.php` 的「AI 补全」段、后台【系统与AI设置】→ AI 调用日志 | 那是本地规则兜底的结果：说明没有一条「已启用且填了密钥」的线路，或密钥/地址/出网有问题（日志里有点名原因）。密钥框只写不读，页面上看着线路都在不代表填过 |
