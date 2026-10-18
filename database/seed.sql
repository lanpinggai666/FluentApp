-- 蓝屏钙软件导航网 - 初始数据种子

-- 1. 默认分类
INSERT INTO categories (id, name, slug, icon, sort_order) VALUES
(1, '效率办公', 'office', 'briefcase', 1),
(2, '系统工具', 'system', 'wrench', 2),
(3, '影音媒体', 'media', 'play', 3),
(4, '开发编程', 'dev', 'code', 4),
(5, '图形设计', 'design', 'image', 5),
(6, '网络通信', 'network', 'globe', 6),
(7, '安全防护', 'security', 'shield', 7),
(8, 'AI 智能', 'ai', 'sparkle', 8),
(9, '社交通讯', 'social', 'comment', 9),
(10, '驱动硬件', 'hardware', 'desktop', 10),
(11, '游戏娱乐', 'game', 'play', 11),
(12, '其它', 'other', 'folder', 99);

-- 2. 软件推荐数据 (含官网、GitHub仓库及说明)
INSERT INTO softwares (id, name, slug, icon_url, official_url, github_url, github_release_keyword, other_urls, category_id, summary, special_notes, is_ai_generated, ai_generated_fields, status, rating_avg, rating_count, views_count, is_featured) VALUES
(1, 'Visual Studio Code', 'vscode', 'https://fastly.jsdelivr.net/gh/devicons/devicon/icons/vscode/vscode-original.svg', 'https://code.visualstudio.com/', 'https://github.com/microsoft/vscode', 'x64', '[{"name":"微云备用","url":"https://code.visualstudio.com/Download"}]', 4, '微软推出的轻量级现代化代码编辑器，支持丰富的插件生态。', '建议从官网下载独立用户安装包或通过Winget安装。', 0, NULL, 'published', 4.95, 128, 1420, 1),

(2, '7-Zip', '7-zip', 'https://www.7-zip.org/7ziplogo.png', 'https://www.7-zip.org/', 'https://github.com/ip7z/7zip', 'x64.exe', '[{"name":"SourceForge","url":"https://sourceforge.net/projects/sevenzip/"}]', 2, '高压缩比的开源文件解压缩工具，支持7z、ZIP、RAR等多种格式。', '永久免费且无任何弹窗广告，推荐下载64位版本。', 0, NULL, 'published', 4.98, 210, 2350, 1),

(3, 'PotPlayer', 'potplayer', 'https://t1.daumcdn.net/potplayer/resources/potplayer.ico', 'https://potplayer.daum.net/', NULL, NULL, '[{"name":"国内镜像","url":"http://potplayer.tv/"}]', 3, '强大的本地音视频播放器，内置庞大解码器，支持高帧率与倍速播放。', '安装时可取消勾选附加编解码器，纯净无扰。', 0, NULL, 'published', 4.88, 95, 1890, 1),

(4, 'Everything', 'everything', 'https://www.voidtools.com/favicon.ico', 'https://www.voidtools.com/', NULL, NULL, '[{"name":"Portable版","url":"https://www.voidtools.com/downloads/"}]', 2, '基于NTFS文件索引的毫秒级本地文件搜索神器。', '首次启动建立全盘索引仅需数秒，占用极小内存。', 0, NULL, 'published', 5.00, 312, 3810, 1),

(5, 'OBS Studio', 'obs-studio', 'https://obsproject.com/assets/images/new_icon_small-r.png', 'https://obsproject.com/', 'https://github.com/obsproject/obs-studio', 'Windows-Installer.exe', '[{"name":"Steam版","url":"https://store.steampowered.com/app/1905180/OBS_Studio/"}]', 3, '开源免费的高性能音视频录制与直播推流工具。', '支持NVENC与QuickSync硬件加速编码。', 0, NULL, 'published', 4.91, 74, 980, 1),

(6, 'Git for Windows', 'git', 'https://git-scm.com/images/logos/logomark-orange@2x.png', 'https://git-scm.com/', 'https://github.com/git-for-windows/git', '64-bit.exe', '[{"name":"淘宝npm镜像","url":"https://npmmirror.com/mirrors/git-for-windows/"}]', 4, '分布式版本控制系统客户端，内置Git Bash与GUI终端。', '国内用户可通过GitHub镜像源加速克隆与下载。', 0, NULL, 'published', 4.90, 88, 1120, 0),

(7, 'Snipaste', 'snipaste', 'https://www.snipaste.com/img/logo.svg', 'https://www.snipaste.com/', NULL, NULL, '[{"name":"微软商店版","url":"https://apps.microsoft.com/detail/9P1WXPKB68KX"}]', 1, '简单但强大的截图贴图工具，支持取色、标注与马赛克。', 'F1截图，F3贴图，极大提升桌面办公与设计效率。', 0, NULL, 'published', 4.96, 160, 2410, 1),

(8, 'Rufus', 'rufus', 'https://rufus.ie/pics/rufus-128.png', 'https://rufus.ie/', 'https://github.com/pbatard/rufus', 'x64.exe', '[{"name":"GitHub Releases","url":"https://github.com/pbatard/rufus/releases"}]', 2, '小巧可靠的启动盘制作工具，支持绕过Win11 TPM检测。', '免安装单文件，运行速度极快。', 0, NULL, 'published', 4.94, 115, 1620, 0),

(9, 'Typora', 'typora', 'https://typora.io/img/favicon-64.png', 'https://typora.io/', NULL, NULL, NULL, 1, '所见即所得的极简 Markdown 编辑器，支持丰富的自定义主题。', '支持数学公式、代码高亮及实时表格编辑。', 0, NULL, 'published', 4.79, 64, 890, 0),

(10, 'Geek Uninstaller', 'geek-uninstaller', 'https://geekuninstaller.com/static/img/geek.ico', 'https://geekuninstaller.com/', NULL, NULL, NULL, 2, '轻巧高效的软件卸载清理工具，强制扫描残留文件与注册表。', '单文件免安装，右键直接彻底粉碎残余。', 0, NULL, 'published', 4.92, 102, 1430, 0);

-- 3. 默认GitHub镜像源 (用于自动测速与直链加速)
INSERT INTO github_mirrors (name, url_template, is_active, sort_order) VALUES
('官方 GitHub (直连)', 'https://github.com/', 1, 1),
('GHProxy 线路1', 'https://ghproxy.net/https://github.com/', 1, 2),
('GHProxy 线路2', 'https://mirror.ghproxy.com/https://github.com/', 1, 3),
('Boki 镜像', 'https://github.boki.moe/https://github.com/', 1, 4),
('Moeyy 镜像', 'https://github.moeyy.xyz/https://github.com/', 1, 5);

-- 4. 默认公告
INSERT INTO notices (title, content, is_active, sort_order) VALUES
('欢迎来到蓝屏钙软件导航网', '本站致力于汇聚纯净、可靠的软件官网与权威分发源，杜绝捆绑与流氓软件。支持游客提交收录，经审核后即刻上线！', 1, 1);

-- 5. 全局基础设置
INSERT INTO settings (key_name, key_value) VALUES
('site_name', '蓝屏钙软件导航'),
('site_subtitle', '纯净 · 高效 · 权威软件下载渠道导航'),
('site_description', '专门收集整理各类优质常用软件的官方网站、GitHub仓库及可靠第三方分发渠道，提供GitHub镜像测速直连下载。'),
('site_keywords', '软件导航,官网下载,GitHub镜像,纯净软件,工具软件,蓝屏钙'),
('admin_username', 'admin'),
('admin_password_hash', '$2y$10$achoy/GGwNkU2UtCE1kXgORO0dnvTgHGiQIsTx/4bhq3sAqyZNPti'), -- 引导口令 admin123，首次登录后请立即在后台修改
('ai_provider', 'openai_compatible'),
('ai_api_base', 'https://api.openai.com/v1'),
('ai_api_key', ''),
('ai_model', 'gpt-4o-mini'),
('ai_enabled', '1'),
('captcha_provider', 'local'), -- local, turnstile, hcaptcha, geetest, none
('captcha_site_key', ''),
('captcha_secret_key', ''),
('google_ads_enabled', '0'),
('google_ads_client', 'ca-pub-xxxxxxxxxxxxxxxx'),
('google_ads_slot', '1234567890'),
('google_ads_code', '');

-- 6. 默认敏感词库
INSERT INTO sensitive_words (word, replacement) VALUES
('兼职刷单', '***'),
('博彩', '***'),
('私服', '***'),
('外挂脚本', '***'),
('色情', '***');
