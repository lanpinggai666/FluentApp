  <!-- 2. 主体双栏布局 -->
  <div class="ms-body-grid">
    
    <!-- 左侧主要信息列 -->
    <div class="ms-content-column">

      <!-- 卡片 1: 相关资源 -->
      <div class="ms-card" id="tutorials-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">
            <span>相关资源</span>
          </div>
          <button type="button" class="btn-store-pill" onclick="openModal('add-resource-modal')">
            <?= FluentIcon::render('plus', 'fluent-icon-sm') ?> 提交相关资源
          </button>
        </div>
        <div class="ms-section-divider"></div>

        <div id="community-resources-container">
          <?php if (empty($resources)): ?>
            <div style="text-align: center; padding: 22px 16px; color: var(--text-muted); font-size: 0.8125rem;">
              <div style="margin-bottom: 8px;">暂无该软件的第三方视频教程与指南</div>
              <button type="button" class="btn-store-secondary" onclick="openModal('add-resource-modal')" style="font-size: 0.75rem; height: 30px; padding: 0 14px;">
                + 分享第一个 B站 / 博客教程
              </button>
            </div>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <?php foreach ($resources as $res): ?>
                <div class="tutorial-item" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 14px; background: var(--bg-surface-secondary); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); transition: var(--fluent-transition);">
                  <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                      <?php if ($res['type'] === 'video'): ?>
                        <span class="badge" style="background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8;">
                          <?= FluentIcon::render('play', 'fluent-icon-sm') ?> 视频教程
                        </span>
                      <?php elseif ($res['type'] === 'article'): ?>
                        <span class="badge badge-os">
                          <?= FluentIcon::render('open', 'fluent-icon-sm') ?> 图文指南
                        </span>
                      <?php elseif ($res['type'] === 'config'): ?>
                        <span class="badge badge-ai">
                          <?= FluentIcon::render('settings', 'fluent-icon-sm') ?> 配置技巧
                        </span>
                      <?php else: ?>
                        <span class="badge badge-paid">
                          <?= FluentIcon::render('wrench', 'fluent-icon-sm') ?> 实用扩展
                        </span>
                      <?php endif; ?>

                      <a href="<?= Url::href($res['url']) ?>" target="_blank" rel="noopener noreferrer" style="font-weight: 600; font-size: 0.875rem; color: var(--text-primary);">
                        <?= View::e($res['title']) ?>
                      </a>

                      <?php if (!empty($res['author'])): ?>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                          · 来自 <?= View::e($res['author']) ?>
                        </span>
                      <?php endif; ?>
                    </div>

                    <?php if (!empty($res['description'])): ?>
                      <div style="font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.5;">
                        <?= View::e($res['description']) ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <a href="<?= Url::href($res['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-store-pill" style="flex-shrink: 0; align-self: center;">
                    前往观看
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 卡片 2: 说明 (Description) -->
      <div class="ms-card" id="desc-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">说明</div>
        </div>
        <div class="ms-section-divider"></div>
        <div class="ms-collapsible-wrapper" id="desc-wrapper">
          <div class="ms-desc-body" style="font-size: 0.9375rem; color: var(--text-secondary); line-height: 1.7;">
            <?php if ($hasDescriptionMd): ?>
              <div class="md-body"><?= $descriptionMdHtml ?></div>
            <?php endif; ?>
            <?php if (!empty($readmeHtml)): ?>
              <?php if ($hasDescriptionMd): ?>
                <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 6px;">
                  <?= FluentIcon::render('code', 'fluent-icon-sm') ?>
                  <strong style="color: var(--text-primary); font-size: 0.9375rem;">来自 GitHub README</strong>
                </div>
              <?php endif; ?>
              <div class="github-readme-rendered">
                <?= $readmeHtml ?>
              </div>
            <?php endif; ?>
            <?php if (!$hasDescriptionMd && empty($readmeHtml)): ?>
              <p>该软件提供官方纯净下载分发，支持快速安装体验。</p>
            <?php endif; ?>
          </div>
          <div class="ms-fade-gradient"></div>
        </div>
        <button type="button" class="ms-read-more-btn" onclick="toggleReadMore('desc-wrapper', this)">
          <span>阅读更多信息</span>
          <span class="chevron-icon"><?= FluentIcon::render('chevron-down', 'fluent-icon-sm') ?></span>
        </button>
      </div>

      <!-- 卡片 3: 评分和评价 (Ratings & Reviews，严格复刻参考图) -->
      <?php
        $ratingDist = $ratingDist ?? Comment::getRatingDistribution((int)$software['id']);
        $ratingTotal = (int)$ratingDist['total'];
        $ratingAvg = (float)$ratingDist['avg'];
        $starCounts = $ratingDist['counts'];
        $hasComments = !empty($comments);
      ?>
      <div class="ms-card" id="reviews-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">评分和评价</div>
          <?php if (!empty($hasUserCommented)): ?>
            <button class="btn-store-pill" style="opacity: 0.65; cursor: default; background: var(--bg-surface-secondary); color: var(--text-muted);" title="您已对该软件发表过评价" onclick="if(window.FluentMessage) window.FluentMessage('您已对该软件发表过评价，暂不支持重复评价', 'info')">
              <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?> 已评价
            </button>
          <?php else: ?>
            <button class="btn-store-pill" onclick="toggleReviewForm()">
              <?= FluentIcon::render('edit', 'fluent-icon-sm') ?> 撰写评价
            </button>
          <?php endif; ?>
        </div>
        <div class="ms-section-divider"></div>

        <?php if ($ratingTotal === 0 && !$hasComments): ?>
          <div style="padding: 16px 0 10px; color: var(--text-secondary); font-size: 0.9375rem;">
            尚无任何评论
          </div>
        <?php else: ?>
          <!-- 评分概览图表 (左侧超大分数 + 右侧 5 级橙色条形图) -->
          <div class="ms-ratings-summary-row">
            <div class="ms-ratings-score-col">
              <div class="ms-ratings-big-num"><?= number_format($ratingAvg, 1) ?></div>
              <div class="ms-ratings-total-count"><?= $ratingTotal ?> 个评级</div>
            </div>

            <div class="ms-ratings-bars-col">
              <?php for ($s = 5; $s >= 1; $s--): 
                $count = $starCounts[$s];
                $pct = $ratingTotal > 0 ? round(($count / $ratingTotal) * 100) : 0;
              ?>
                <div class="ms-bar-row" title="<?= $s ?>星: <?= $count ?> 个评级 (<?= $pct ?>%)">
                  <span class="ms-bar-star-num"><?= $s ?></span>
                  <span class="ms-bar-star-icon">★</span>
                  <div class="ms-bar-track">
                    <div class="ms-bar-fill" style="width: <?= $pct ?>%;"></div>
                  </div>
                  <span style="font-size: 0.75rem; color: var(--text-muted); width: 34px; text-align: right; flex-shrink: 0;"><?= $pct ?>%</span>
                </div>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- 折叠的撰写评价表单 -->
        <?php if (empty($hasUserCommented)): ?>
          <form id="comment-form" style="display: none; background: var(--bg-surface-secondary); padding: 16px; border-radius: var(--radius-sm); margin: 18px 0; border: 1px solid var(--border-subtle);">
            <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">
            <input type="hidden" name="rating" id="comment-rating-input" value="5">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
              <div>
                <label class="form-label">昵称</label>
                <input type="text" name="nickname" class="form-input" placeholder="输入昵称 (默认访客)">
              </div>
              <div>
                <label class="form-label">评价标题</label>
                <input type="text" name="title" class="form-input" placeholder="简要总结您的体验评价">
              </div>
            </div>

            <div style="margin-bottom: 12px;">
              <label class="form-label">评分</label>
              <div id="star-picker" style="display: flex; gap: 4px; cursor: pointer; color: #ea580c; font-size: 1.125rem;">
                <span class="star-pick" data-val="1">★</span>
                <span class="star-pick" data-val="2">★</span>
                <span class="star-pick" data-val="3">★</span>
                <span class="star-pick" data-val="4">★</span>
                <span class="star-pick" data-val="5">★</span>
              </div>
            </div>

            <div class="form-group">
              <textarea name="content" class="form-textarea" placeholder="分享使用体验..." required style="min-height: 70px;"></textarea>
            </div>

            <?= $captchaHtml ?>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
              <button type="button" class="btn-store-secondary" onclick="toggleReviewForm()">取消</button>
              <button type="submit" class="btn-store-primary" style="padding: 6px 18px;">发表评价</button>
            </div>
          </form>
        <?php endif; ?>

        <!-- 评论列表 (完全按照参考图布局：评分 ★ | 标题，内容，作者与日期，点赞踩计数) -->
        <?php if ($hasComments): ?>
          <div class="ms-reviews-list">
            <?php foreach ($comments as $idx => $c): ?>
              <?php
                $isExtraComment = ($idx >= 3);
                $rawContent = trim((string)$c['content']);
                $isLongComment = (mb_strlen($rawContent, 'UTF-8') > 110 || substr_count($rawContent, "\n") >= 3);
              ?>
              <div class="ms-review-card <?= $isExtraComment ? 'ms-review-hidden' : '' ?>" style="<?= $isExtraComment ? 'display: none;' : '' ?>">
                <div class="ms-review-headline">
                  <span class="ms-review-score"><?= number_format((float)$c['rating'], 1) ?> <span class="ms-star-orange">★</span></span>
                  <span class="ms-review-sep">|</span>
                  <span class="ms-review-title"><?= View::e($c['title'] ?: '功能体验与使用感受') ?></span>
                </div>
                <div class="ms-review-content <?= $isLongComment ? 'collapsible' : '' ?>">
                  <?= nl2br(View::e($rawContent)) ?>
                </div>
                <?php if ($isLongComment): ?>
                  <button type="button" class="ms-review-expand-btn" onclick="toggleCommentExpand(this)">
                    展开全文
                  </button>
                <?php endif; ?>
                <div class="ms-review-footer">
                  <div class="ms-review-author-date">
                    <?php if (!empty($c['user_id'])): ?>
                      <a href="<?= Url::route('user/home', ['id' => (int)$c['user_id']]) ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 500;" title="查看用户主页"><?= View::e($c['nickname'] ?: '用户') ?></a>
                    <?php else: ?>
                      <?= View::e($c['nickname'] ?: 'TY') ?>
                    <?php endif; ?>, <?= date('Y/n/j', strtotime($c['created_at'])) ?>
                  </div>
                  <div class="ms-review-votes">
                    <button type="button" class="ms-vote-btn" onclick="voteComment(<?= (int)$c['id'] ?>, 'like', this)">
                      <?= FluentIcon::render('thumb-like', 'fluent-icon-sm') ?>
                      <span><?= (int)($c['likes'] ?? 0) ?></span>
                    </button>
                    <button type="button" class="ms-vote-btn" onclick="voteComment(<?= (int)$c['id'] ?>, 'dislike', this)">
                      <?= FluentIcon::render('thumb-dislike', 'fluent-icon-sm') ?>
                      <span><?= (int)($c['dislikes'] ?? 0) ?></span>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($comments) > 3): ?>
            <div style="margin-top: 14px;">
              <a href="javascript:void(0)" class="ms-read-more-link" onclick="toggleMoreReviews(this)">
                阅读更多信息 (共 <?= count($comments) ?> 条评价)
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- 卡片 4: 系统要求 (严格遵循用户要求：仅三行要求) -->
      <div class="ms-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">系统要求</div>
        </div>
        <div class="ms-section-divider"></div>
        <div style="font-size: 0.875rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 12px;">
          <div><span style="color: var(--text-muted); display: inline-block; width: 95px;">适用平台:</span> 电脑 (PC)</div>
          <div><span style="color: var(--text-muted); display: inline-block; width: 95px;">操作系统:</span> Windows 10 版本 17763.0 或更高版本 / Windows 11</div>
          <div><span style="color: var(--text-muted); display: inline-block; width: 95px;">架构:</span> x64.exe (最低), ARM64 (兼容)</div>
        </div>
      </div>

      <!-- 卡片 5: 其他信息 (内容距离拉大，数值严格不用粗体) -->
      <div class="ms-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">其他信息</div>
        </div>
        <div class="ms-section-divider"></div>
        <div class="ms-info-grid">
          
          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?> 维护管理
            </span>
            <span class="ms-info-val">
              <?php if (!empty($managerUser)): ?>
                <a href="<?= Url::route('user/home', ['id' => (int)$managerUser['id']]) ?>" style="color: var(--text-link); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;" title="查看用户个人主页">
                  <?php if (!empty($managerUser['avatar_url'])): ?>
                    <img src="<?= View::e($managerUser['avatar_url']) ?>" style="width: 18px; height: 18px; border-radius: 50%; object-fit: cover;" alt="">
                  <?php else: ?>
                    <?= FluentIcon::render('person', 'fluent-icon-xs') ?>
                  <?php endif; ?>
                  <?= View::e($managerUser['username']) ?>
                </a>
              <?php else: ?>
                <span style="color: var(--text-secondary);">平台官方维护</span>
              <?php endif; ?>
            </span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('person', 'fluent-icon-sm') ?> 开发者
            </span>
            <span class="ms-info-val">
              <?= View::e($developerName) ?>
            </span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('calendar', 'fluent-icon-sm') ?> 发布日期
            </span>
            <span class="ms-info-val"><?= substr($software['created_at'], 0, 10) ?></span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('money', 'fluent-icon-sm') ?> 收费模式
            </span>
            <span class="ms-info-val">
              <?= !empty($software['price_desc']) ? ($priceLabel . ' (' . View::e($software['price_desc']) . ')') : $priceLabel ?>
            </span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('code', 'fluent-icon-sm') ?> 源码与协议
            </span>
            <span class="ms-info-val">
              <?= $isOpenSource ? ('开源 (' . View::e($licenseText) . ')') : '闭源专有' ?>
            </span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('folder', 'fluent-icon-sm') ?> 类别
            </span>
            <span class="ms-info-val">
              <a href="<?= Url::category($software['category_slug'] ?? '') ?>" style="color: var(--text-link);"><?= View::e($software['category_name'] ?? '实用工具') ?></a>
            </span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?> 安装支持
            </span>
            <span class="ms-info-val"><?= View::e($software['install_type'] ?: '标准安装包 / 免安装便携版') ?></span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('globe', 'fluent-icon-sm') ?> 支持的语言
            </span>
            <span class="ms-info-val"><?= View::e($software['supported_languages'] ?: '简体中文, 英语 (多语言支持)') ?></span>
          </div>

          <div class="ms-info-item">
            <span class="ms-info-label">
              <?= FluentIcon::render('open', 'fluent-icon-sm') ?> 官方支持
            </span>
            <span class="ms-info-val">
              <a href="<?= Url::href($software['official_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-link);">访问官方网站</a>
            </span>
          </div>

          <div class="ms-info-item" style="grid-column: 1 / -1;">
            <span class="ms-info-label">
              <?= FluentIcon::render('tag', 'fluent-icon-sm') ?> 标签与关键词
            </span>
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 4px;" id="software-tags-container">
              <?php foreach ($tagList as $tg): ?>
                <span class="tag-pill"><?= View::e($tg) ?></span>
              <?php endforeach; ?>
              <button type="button" class="tag-pill tag-pill-add" onclick="openModal('add-tag-modal')" title="添加自定义标签">
                <?= FluentIcon::render('plus', 'fluent-icon-sm') ?>
                <span>打标签</span>
              </button>
            </div>
          </div>

        </div>
      </div>

      <?php if (!empty($aiSources)): ?>
      <!-- 卡片 6: 资料来源（AI 整理本页信息时读过的公开网页，逐条可核） -->
      <div class="ms-card">
        <div class="ms-card-header">
          <div class="ms-card-title-lg">资料来源</div>
        </div>
        <div class="ms-section-divider"></div>
        <ol style="margin: 0; padding-left: 22px; display: flex; flex-direction: column; gap: 10px; font-size: 0.875rem;">
          <?php foreach ($aiSources as $srcItem): ?>
            <li style="line-height: 1.5;">
              <a href="<?= Url::href($srcItem['url']) ?>" target="_blank" rel="nofollow noopener noreferrer"
                 style="color: var(--text-link); text-decoration: none;">
                <span style="color: var(--text-primary);"><?= View::e($srcItem['title']) ?></span>
                <?php if (!empty($srcItem['host'])): ?>
                  <span style="display: block; color: var(--text-muted); font-size: 0.75rem; word-break: break-all;"><?= View::e($srcItem['host']) ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
        <div style="margin-top: 12px; font-size: 0.75rem; color: var(--text-muted); line-height: 1.6;">
          本页部分信息由 AI 依据上述公开页面整理。链接失效或内容与事实不符，欢迎用页面顶部的「修改信息」提交修正，我们会复核后更新。
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- 右侧：发现更多 (Discover More，无卡片框拘束，整块标题可点击，下滑粘性固定) -->
    <aside class="ms-sidebar-column">
      <div class="ms-discover-card">
        <a href="<?= Url::category($software['category_slug'] ?? '') ?>" class="ms-discover-header-link" title="点击查看更多 <?= View::e($software['category_name'] ?? '') ?> 软件">
          <div class="ms-discover-title">
            <span>发现更多</span>
            <span style="font-size: 0.875rem; color: var(--text-muted);">&gt;</span>
          </div>
        </a>

        <div class="ms-discover-list">
          <?php foreach ($related as $rel): ?>
            <div class="ms-discover-item">
              <div class="ms-discover-left">
                <img src="<?= View::e($rel['icon_url']) ?>" class="ms-discover-icon" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23cbd5e1%22/></svg>'">
                <div class="ms-discover-meta">
                  <a href="<?= Url::detail($rel['slug']) ?>">
                    <div class="ms-discover-name" title="<?= View::e($rel['name']) ?>"><?= View::e($rel['name']) ?></div>
                  </a>
                  <div class="ms-discover-cat"><?= View::e($rel['category_name']) ?></div>
                </div>
              </div>
              <a href="<?= Url::detail($rel['slug']) ?>" class="ms-discover-action-link">
                免费下载
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

  </div>
</div>

