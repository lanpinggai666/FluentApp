-- v15 → v16：镜像源记住「学到的映射方式」与「学它时用的那条原始链接」
ALTER TABLE `github_mirrors`
  ADD COLUMN `url_style` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'learned: replace / prefix / direct，空=按域名清单猜',
  ADD COLUMN `sample_original` VARCHAR(500) DEFAULT NULL COMMENT '学习这条映射时用的原始 GitHub 链接';
