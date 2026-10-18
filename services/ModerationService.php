<?php
/**
 * 审核动作的统一实现。
 *
 * 后台网页与开放 API 都调这里：状态流转、并发认领、归属绑定、站内信、邮件与一次性修改链接，
 * 一条都不能少 —— App 少做一步，提交者就收不到驳回理由和重新提交的链接。
 */

class ModerationService
{
    /**
     * @param array $src 审核入参：id、action(approve|approve_ai|reject)、review_note、category_id、special_notes、description_md
     * @return array{ok:bool,code:string,message:string,data:array}
     */
    public static function reviewSoftware(array $src): array
    {
        $id = (int) ($src['id'] ?? 0);
        $action = (string) ($src['action'] ?? '');
        $reviewNote = trim((string) ($src['review_note'] ?? ''));

        if ($id <= 0 || !in_array($action, ['approve', 'approve_ai', 'reject'], true)) {
            return self::result(false, 'invalid_param', '审核参数无效', ['action' => 'approve|approve_ai|reject']);
        }

        $software = Software::find($id);
        if (!$software || ($software['status'] ?? '') !== 'pending') {
            return self::result(false, 'not_pending', '该提交不存在或已被处理');
        }

        if ($action === 'reject' && $reviewNote === '') {
            return self::result(false, 'review_note_required', '驳回时必须填写驳回理由');
        }

        $now = date('Y-m-d H:i:s');
        $updates = [
            'review_note' => $reviewNote !== '' ? $reviewNote : null,
            'reviewed_at' => $now,
        ];
        $isReject = $action === 'reject';

        if ($isReject) {
            $updates['status'] = 'rejected';
        } else {
            $updates['status'] = 'published';
            $updates['category_id'] = (int) ($src['category_id'] ?? $software['category_id']) ?: $software['category_id'];
            $updates['special_notes'] = trim((string) ($src['special_notes'] ?? $software['special_notes']));
            $updates['description_md'] = trim((string) ($src['description_md'] ?? $software['description_md'] ?? ''));
        }

        // 条件更新等于认领这条待审记录，避免两个管理员同时审同一个提交
        $claimed = Database::update('softwares', $updates, 'id = :id AND status = :status', [
            'id' => $id,
            'status' => 'pending',
        ]);
        if ($claimed !== 1) {
            return self::result(false, 'already_handled', '该提交已被其他管理员处理');
        }

        $aiSummary = null;
        if ($action === 'approve_ai') {
            // 审核人点「AI 补全后发布」就是要在这一刻拿到结果：当场调用 AI。
            // 换源与重试都在 AiService 里（ai_retries / ai_total_timeout），全部线路失败才落本地规则兜底。
            // 放在认领成功之后，两个管理员并发审核时不会有一个白跑一次外呼。
            $aiSummary = AiService::completeSoftware($id, 'approve_ai');
        }

        $ownerId = (int) ($software['owner_user_id'] ?? 0);
        if ($ownerId <= 0 && !empty($software['submitter_email'])) {
            $matchedUser = User::findByEmail(trim((string) $software['submitter_email']));
            if ($matchedUser) {
                $ownerId = (int) $matchedUser['id'];
                if (!$isReject) {
                    Database::update('softwares', ['owner_user_id' => $ownerId], 'id = :id', ['id' => $id]);
                }
            }
        }

        $editToken = $isReject ? Software::createEditToken($id) : null;

        if ($ownerId > 0) {
            if (!$isReject) {
                Notification::send(
                    $ownerId,
                    "软件「{$software['name']}」审核通过！",
                    '您提交的软件已通过管理员审核并正式收录发布。您现已成为该软件的主人/管理者，可在个人中心直接修改软件资料或处理访客举报。',
                    'system',
                    '?r=user/softwares'
                );
            } else {
                $reasonText = $reviewNote !== '' ? " 驳回说明：{$reviewNote}。" : "。";
                Notification::send(
                    $ownerId,
                    "软件「{$software['name']}」审核未通过",
                    "您提交的软件未通过审核{$reasonText}您可以点击链接重新调整并提交审核（有效期 7 天）。",
                    'system',
                    $editToken !== null ? '?r=submit/edit&token=' . rawurlencode((string) $editToken) : null
                );
            }
        }

        $notification = self::notifyReviewResult($id, $isReject, $reviewNote, $editToken);

        $aiNote = $aiSummary === null ? '' : AiService::describeRun($aiSummary);

        return self::result(true, '', ($isReject ? '已驳回，通知状态：' : '已通过，通知状态：') . $notification
            . ($aiNote !== '' ? '。AI 补全：' . $aiNote : ''), [
            'id' => $id,
            'name' => $software['name'],
            'status' => $updates['status'],
            'action' => $action,
            'notification' => $notification,
            // 审核页与开放 API 都直接能把「这次 AI 到底跑成了什么」显示出来，不用再猜
            'ai' => $aiSummary,
            'ai_note' => $aiNote,
            'owner_user_id' => $ownerId > 0 ? $ownerId : null,
            // 明文修改链接只在驳回时产生一次，网页端靠邮件投递，这里返回给调用方是为了让它自己转发
            'edit_link_token' => $editToken,
        ]);
    }

    /**
     * 发送审核结果邮件并记录通知状态。
     * @return string 通知状态描述
     */
    public static function notifyReviewResult(int $softwareId, bool $isReject, string $reviewNote, ?string $token = null): string
    {
        $row = Database::fetch('SELECT id, name, submitter_email FROM softwares WHERE id = :id', ['id' => $softwareId]);
        $email = trim((string) ($row['submitter_email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Software::updateNotification($softwareId, 'skipped', '提交者未填写有效邮箱');
            return '跳过（无有效邮箱）';
        }

        $siteName = Setting::get('site_name', '软件导航');
        $softwareName = htmlspecialchars((string) ($row['name'] ?? '软件'), ENT_QUOTES, 'UTF-8');
        $subject = $isReject ? '软件提交审核未通过' : '软件提交审核通过';
        $body = '<p>您提交的「' . $softwareName . '」' . ($isReject ? '未通过审核。' : '已审核通过并公开展示。') . '</p>';

        if ($reviewNote !== '') {
            $body .= '<p><strong>审核说明：</strong><br>'
                . nl2br(htmlspecialchars($reviewNote, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        if ($isReject) {
            if ($token === null) {
                $token = Software::createEditToken($softwareId);
            }
            if ($token !== null) {
                // 站点基址统一走 Url::siteBase()，一次性链接才不会跟着 Host 头跑到别人的域上
                $editUrl = Url::siteBase() . '/?r=submit/edit&token=' . rawurlencode((string) $token);
                $body .= '<p>您可以使用下方链接修改原提交内容并重新提交审核（链接有效期 7 天，仅可使用一次）：</p>';
                $body .= '<p><a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">修改并重新提交</a></p>';
            }
            $body .= '<p>若认为审核有误，可直接回复本邮件说明情况。</p>';
        } else {
            $body .= '<p>感谢您的投稿，可前往本站查看收录结果。</p>';
        }

        $result = MailService::send($email, '[' . $siteName . '] ' . $subject, MailService::wrap($body));
        Software::updateNotification($softwareId, !empty($result['success']) ? 'sent' : 'failed', $result['error'] ?? null);

        return !empty($result['success']) ? '邮件已发送' : '邮件发送失败（' . ($result['error'] ?? '未知错误') . '）';
    }

    private static function result(bool $ok, string $code, string $message, array $data = []): array
    {
        return ['ok' => $ok, 'code' => $code, 'message' => $message, 'data' => $data];
    }
}
