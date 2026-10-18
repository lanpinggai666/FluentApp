-- v15 → v16：镜像源记住「学到的映射方式」与「学它时用的那条原始链接」
-- 之前映射方式是靠内置域名清单猜的，没收录过的镜像站只能拼成前缀形态，学不会也不会报错
ALTER TABLE github_mirrors ADD COLUMN url_style VARCHAR(12) NOT NULL DEFAULT '';
ALTER TABLE github_mirrors ADD COLUMN sample_original VARCHAR(500) DEFAULT NULL;
