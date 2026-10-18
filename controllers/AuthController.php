<?php
/**
 * 蓝屏钙软件导航网 - 用户注册、登录、找回密码与第三方认证控制器
 */

class AuthController {

    public function __construct() {
        Auth::start();
    }

    /**
     * 发送邮箱验证码 (注册 / 找回密码)
     */
    public function sendVerifyCode(): void {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => '请求方式非法']);
            exit;
        }

        // 校验人机验证防恶意刷信
        $captchaScope = !empty($_POST['captcha_name']) ? (string)$_POST['captcha_name'] : 'verify_code';
        if (!CaptchaService::verify($_POST, $captchaScope)) {
            echo json_encode(['success' => false, 'error' => '安全验证计算错误，请重新计算']);
            exit;
        }

        $emailPrefix = trim($_POST['email_prefix'] ?? '');
        $emailSuffix = trim($_POST['email_suffix'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!empty($emailPrefix) && !empty($emailSuffix)) {
            $email = $emailPrefix . '@' . ltrim($emailSuffix, '@');
        }

        $email = strtolower(trim($email));
        $action = trim($_POST['action'] ?? VerifyCode::ACTION_REGISTER);

        if (!User::isAllowedEmail($email)) {
            echo json_encode(['success' => false, 'error' => '邮箱格式或后缀不受支持']);
            exit;
        }

        // 防刷信：VerifyCode 只有同邮箱 60 秒冷却，这里补上按邮箱与按 IP 的小时级上限
        $mailLimit = RateLimiter::consume('send-code-mail', $email, 8, 3600);
        $sendIpLimit = RateLimiter::consume('send-code-ip', RateLimiter::clientIp(), 20, 3600);
        if (!$mailLimit['allowed'] || !$sendIpLimit['allowed']) {
            echo json_encode(['success' => false, 'error' => '验证码发送过于频繁，请稍后再试']);
            exit;
        }

        if ($action === VerifyCode::ACTION_REGISTER) {
            if (User::findByEmail($email)) {
                echo json_encode(['success' => false, 'error' => '该邮箱已被注册，请直接登录']);
                exit;
            }
        } elseif ($action === VerifyCode::ACTION_RESET_PASSWORD) {
            if (!User::findByEmail($email)) {
                echo json_encode(['success' => false, 'error' => '该邮箱尚未注册账号']);
                exit;
            }
        }

        $res = VerifyCode::sendCode($email, $action);
        if (!empty($res['success'])) {
            $_SESSION['captcha_passed_' . $action . '_' . $email] = time() + 900;
        }
        echo json_encode($res);
        exit;
    }

    /**
     * 用户登录 (支持邮箱或昵称登录，支持常规页面与 AJAX/Modal)
     */
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                      (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

            // 登录人机验证
            $captchaScope = !empty($_POST['captcha_name']) ? (string)$_POST['captcha_name'] : 'login';
            if (!CaptchaService::verify($_POST, $captchaScope)) {
                $this->respondError('安全验证失败，请重新计算', $isAjax);
                return;
            }

            $account = trim($_POST['account'] ?? $_POST['email'] ?? $_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $remember = !empty($_POST['remember']);

            if (empty($account) || empty($password)) {
                $this->respondError('请输入登录账号和密码', $isAjax);
                return;
            }

            // 双维度限流：换账号绕过单账号限制、单 IP 大批量撞库都会被挡住
            $ip = RateLimiter::clientIp();
            $accountKey = $ip . '|' . mb_strtolower($account);
            $pairLimit = RateLimiter::consume('user-login', $accountKey, 8, 900);
            $ipLimit = RateLimiter::consume('user-login-ip', $ip, 30, 900);
            if (!$pairLimit['allowed'] || !$ipLimit['allowed']) {
                $this->respondError('登录尝试过于频繁，请稍后再试', $isAjax);
                return;
            }

            $authResult = User::authenticate($account, $password);
            if (!$authResult['success']) {
                $this->respondError($authResult['error'], $isAjax);
                return;
            }

            $user = $authResult['user'];
            RateLimiter::clear('user-login', $accountKey);
            Auth::loginUser($user, $remember);

            // 如果是管理员，默认导向管理后台；否则导向主页或指定地址
            $defaultRedirect = ($user['role'] === 'admin' || Auth::check()) ? '?r=admin/dashboard' : '?r=home';
            $redirect = Auth::internalRedirect($_POST['redirect'] ?? $_GET['redirect'] ?? null, $defaultRedirect);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'avatar_url' => $user['avatar_url'],
                        'role' => $user['role']
                    ],
                    'redirect' => $redirect
                ]);
                exit;
            }

            header('Location: ' . $redirect);
            exit;
        }

        if (Auth::checkUser()) {
            header('Location: ' . Url::target('?r=home'));
            exit;
        }

        View::render('auth.login', [
            'title' => '登录 - 蓝屏钙软件导航',
            'redirect' => $_GET['redirect'] ?? '?r=home',
            'captchaHtml' => CaptchaService::renderHtml('login')
        ]);
    }

    /**
     * 用户注册 (前缀+后缀下拉框选择，邮箱验证码，人机验证)
     */
    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                      (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

            $username = trim($_POST['username'] ?? '');
            $emailPrefix = trim($_POST['email_prefix'] ?? '');
            $emailSuffix = trim($_POST['email_suffix'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (!empty($emailPrefix) && !empty($emailSuffix)) {
                $email = $emailPrefix . '@' . ltrim($emailSuffix, '@');
            }
            $email = strtolower(trim($email));

            // 注册人机验证 (若发送验证码时已通过人机验证，则视为已校验)
            $captchaPassed = (!empty($_SESSION['captcha_passed_register_' . $email]) && $_SESSION['captcha_passed_register_' . $email] > time());
            $captchaScope = !empty($_POST['captcha_name']) ? (string)$_POST['captcha_name'] : 'register';
            if (!$captchaPassed && !CaptchaService::verify($_POST, $captchaScope)) {
                $this->respondError('安全验证失败，请重新计算', $isAjax);
                return;
            }

            $emailCode = trim($_POST['email_code'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $passwordConfirm = trim($_POST['password_confirm'] ?? '');

            if (empty($username) || empty($email) || empty($password)) {
                $this->respondError('请填写所有必填信息', $isAjax);
                return;
            }

            if ($password !== $passwordConfirm) {
                $this->respondError('两次输入的密码不一致', $isAjax);
                return;
            }

            // 校验邮箱验证码
            if (!VerifyCode::verify($email, $emailCode, VerifyCode::ACTION_REGISTER)) {
                $this->respondError('邮箱验证码错误或已失效', $isAjax);
                return;
            }

            $result = User::register($username, $email, $password);
            if (!$result['success']) {
                $this->respondError($result['error'], $isAjax);
                return;
            }

            $user = $result['user'];
            Auth::loginUser($user);

            $redirect = Auth::internalRedirect($_POST['redirect'] ?? $_GET['redirect'] ?? null, '?r=home');
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'avatar_url' => $user['avatar_url']
                    ],
                    'redirect' => $redirect
                ]);
                exit;
            }

            header('Location: ' . $redirect);
            exit;
        }

        if (Auth::checkUser()) {
            header('Location: ' . Url::target('?r=home'));
            exit;
        }

        View::render('auth.register', [
            'title' => '注册账号 - 蓝屏钙软件导航',
            'allowedDomains' => User::getAllowedDomains(),
            'redirect' => $_GET['redirect'] ?? '?r=home',
            'captchaHtml' => CaptchaService::renderHtml('register')
        ]);
    }

    /**
     * 忘记密码与重置密码
     */
    public function forgotPassword(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                      (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

            $emailPrefix = trim($_POST['email_prefix'] ?? '');
            $emailSuffix = trim($_POST['email_suffix'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (!empty($emailPrefix) && !empty($emailSuffix)) {
                $email = $emailPrefix . '@' . ltrim($emailSuffix, '@');
            }
            $email = strtolower(trim($email));

            $captchaPassed = (!empty($_SESSION['captcha_passed_reset_password_' . $email]) && $_SESSION['captcha_passed_reset_password_' . $email] > time());
            $captchaScope = !empty($_POST['captcha_name']) ? (string)$_POST['captcha_name'] : 'forgot';
            if (!$captchaPassed && !CaptchaService::verify($_POST, $captchaScope)) {
                $this->respondError('安全验证失败，请重新计算', $isAjax);
                return;
            }

            $emailCode = trim($_POST['email_code'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['new_password_confirm'] ?? '');

            if (empty($email) || empty($newPassword)) {
                $this->respondError('请填写所有必填信息', $isAjax);
                return;
            }

            if ($newPassword !== $confirmPassword) {
                $this->respondError('两次输入的新密码不一致', $isAjax);
                return;
            }

            if (!VerifyCode::verify($email, $emailCode, VerifyCode::ACTION_RESET_PASSWORD)) {
                $this->respondError('邮箱验证码错误或已失效', $isAjax);
                return;
            }

            $res = User::resetPassword($email, $newPassword);
            if (!$res['success']) {
                $this->respondError($res['error'], $isAjax);
                return;
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => '密码已成功重置，请登录',
                    'redirect' => '?r=auth/login'
                ]);
                exit;
            }

            header('Location: ' . Url::target('?r=auth/login&msg=' . urlencode('密码已重置，请使用新密码登录')));
            exit;
        }

        View::render('auth.forgot_password', [
            'title' => '重置密码 - 蓝屏钙软件导航',
            'allowedDomains' => User::getAllowedDomains(),
            'captchaHtml' => CaptchaService::renderHtml('forgot')
        ]);
    }

    /**
     * 退出登录
     */
    public function logout(): void {
        Auth::logoutUser();
        header('Location: ' . Auth::internalRedirect($_SERVER['HTTP_REFERER'] ?? null, '?r=home'));
        exit;
    }

    /**
     * 第三方 OAuth 发起跳转 (支持 Microsoft, Google, GitHub)
     */
    public function oauthLogin(): void {
        $provider = trim($_GET['provider'] ?? '');
        $redirectAfter = $_GET['redirect'] ?? null;

        $authUrl = OAuthService::getAuthorizeUrl($provider, $redirectAfter);
        if (!$authUrl) {
            http_response_code(400);
            echo "未配置第三方登录平台（" . htmlspecialchars($provider) . "）。";
            exit;
        }

        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * 第三方 OAuth 授权回调 (支持常规重定向与 JS 弹窗通讯)
     */
    public function oauthCallback(): void {
        $provider = trim($_GET['provider'] ?? '');
        $code = trim($_GET['code'] ?? '');
        $state = trim($_GET['state'] ?? '');

        if (empty($provider) || empty($code) || empty($state)) {
            $this->renderOAuthWindowResult(false, '缺少必要的回调参数或用户取消了授权');
            return;
        }

        $res = OAuthService::handleCallback($provider, $code, $state);
        if (!$res['success']) {
            $this->renderOAuthWindowResult(false, $res['error'] ?? '第三方认证失败');
            return;
        }

        $oauthUser = $res['user'];
        $currentUser = Auth::currentUser();
        if ($currentUser) {
            $bindRes = User::bindOAuthAccount(
                (int)$currentUser['id'],
                $provider,
                $oauthUser['id'],
                $oauthUser['email'],
                $oauthUser['name'],
                $oauthUser['avatar'] ?? ''
            );
            $redirectAfter = '?r=user/profile';
            if (!$bindRes['success']) {
                $this->renderOAuthWindowResult(false, $bindRes['error'], $currentUser, $redirectAfter);
                return;
            }
            $this->renderOAuthWindowResult(true, '第三方账号绑定成功', $currentUser, $redirectAfter);
            return;
        }

        $userRes = User::findOrCreateFromOAuth(
            $provider,
            $oauthUser['id'],
            $oauthUser['email'],
            $oauthUser['name'],
            $oauthUser['avatar'] ?? '',
            !empty($oauthUser['emailVerified'])
        );

        if (!$userRes['success']) {
            $this->renderOAuthWindowResult(false, $userRes['error'] ?? '登录失败');
            return;
        }

        $user = $userRes['user'];
        Auth::loginUser($user);

        $redirectAfter = OAuthService::getRedirectAfter($provider);
        $this->renderOAuthWindowResult(true, '登录成功', $user, $redirectAfter);
    }

    /**
     * Google Identity Services (GIS) JS 弹窗一键登录接口
     */
    public function googleOneTap(): void {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $credential = $input['credential'] ?? $_POST['credential'] ?? '';

        if (empty($credential)) {
            echo json_encode(['success' => false, 'error' => '缺少 Google 登录凭据']);
            exit;
        }

        $verifyRes = OAuthService::verifyGoogleIdToken($credential);
        if (!$verifyRes['success']) {
            echo json_encode(['success' => false, 'error' => $verifyRes['error']]);
            exit;
        }

        $googleUser = $verifyRes['user'];
        if (Auth::checkUser()) {
            $currentUser = Auth::currentUser();
            $bindRes = User::bindOAuthAccount(
                (int)$currentUser['id'],
                'google',
                $googleUser['id'],
                $googleUser['email'],
                $googleUser['name'],
                $googleUser['avatar'] ?? ''
            );
            if (!$bindRes['success']) {
                echo json_encode(['success' => false, 'error' => $bindRes['error']]);
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Google 账号绑定成功', 'redirect' => '?r=user/profile']);
            exit;
        }

        $userRes = User::findOrCreateFromOAuth(
            'google',
            $googleUser['id'],
            $googleUser['email'],
            $googleUser['name'],
            $googleUser['avatar'],
            !empty($googleUser['emailVerified'])
        );

        if (!$userRes['success']) {
            echo json_encode(['success' => false, 'error' => $userRes['error']]);
            exit;
        }

        $user = $userRes['user'];
        Auth::loginUser($user);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url']
            ],
            'redirect' => '?r=home'
        ]);
        exit;
    }

    /**
     * 当前登录用户状态 API
     */
    public function me(): void {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::currentUser();
        if ($user) {
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'avatar_url' => $user['avatar_url'],
                    'role' => $user['role'],
                    'badges' => User::getBadges($user),
                    'unread_notifications' => Notification::getUnreadCount($user['id'])
                ]
            ]);
        } else {
            echo json_encode([
                'logged_in' => false,
                'user' => null
            ]);
        }
        exit;
    }

    /**
     * 允许注册的邮箱后缀列表 API
     */
    public function allowedDomains(): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'domains' => User::getAllowedDomains()
        ]);
        exit;
    }

    private function respondError(string $msg, bool $isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>提示 - <?= View::e(Setting::get('site_name', '蓝屏钙软件导航')) ?></title>
          <link rel="stylesheet" href="assets/css/style.css">
          <link rel="stylesheet" href="assets/css/components.css">
        </head>
        <body style="background: var(--bg-page, #f8fafc); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
          <div class="fluent-dialog" style="max-width: 440px; box-shadow: var(--shadow-modal, 0 16px 36px rgba(0,0,0,0.16)); background: var(--bg-surface, #ffffff); border: 1px solid var(--border-subtle, rgba(0,0,0,0.08)); border-radius: 12px; overflow: hidden;">
            <div class="fluent-dialog-body" style="padding: 24px 24px 12px; display: flex; gap: 14px;">
              <svg class="fd-icon" viewBox="0 0 20 20" fill="#dc2626" style="width: 24px; height: 24px; flex-shrink: 0;">
                <path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm2.53 5.03L11.06 8.5l1.47 1.47a.75.75 0 1 1-1.06 1.06L10 9.56 8.53 11.03a.75.75 0 0 1-1.06-1.06L8.94 8.5 7.47 7.03a.75.75 0 1 1 1.06-1.06L10 7.44l1.47-1.41a.75.75 0 0 1 1.06 1Z"/>
              </svg>
              <div class="fluent-dialog-text">
                <h3 class="fluent-dialog-title" style="margin: 0 0 6px; font-size: 1.0625rem; font-weight: 600; color: var(--text-primary, #1e293b);">操作提示</h3>
                <div class="fluent-dialog-message" style="font-size: 0.875rem; color: var(--text-secondary, #64748b); line-height: 1.6;"><?= View::e($msg) ?></div>
              </div>
            </div>
            <div class="fluent-dialog-footer" style="padding: 16px 24px 20px; display: flex; justify-content: flex-end;">
              <button type="button" class="fluent-dialog-btn fluent-dialog-btn-primary" style="height: 32px; padding: 0 18px; border-radius: 6px; background: var(--ms-blue, #0078d4); color: #fff; border: none; cursor: pointer;" onclick="window.history.back();">返回上一页</button>
            </div>
          </div>
        </body>
        </html>
        <?php
        exit;
    }

    private function renderOAuthWindowResult(bool $success, string $message, ?array $user = null, string $redirect = '?r=home'): void {
        $redirect = Auth::internalRedirect($redirect);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
          <meta charset="UTF-8">
          <title><?= $success ? '登录成功' : '登录失败' ?></title>
          <style>
            body { font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #fafbfc; color: #18181b; }
            .card { background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 360px; width: 90%; }
            .spinner { width: 32px; height: 32px; border: 3px solid rgba(0,103,184,0.2); border-top-color: #0067b8; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
            @keyframes spin { to { transform: rotate(360deg); } }
            .btn { display: inline-block; margin-top: 16px; padding: 8px 16px; background: #0067b8; color: #fff; text-decoration: none; border-radius: 6px; font-size: 14px; }
          </style>
        </head>
        <body>
          <div class="card">
            <?php if ($success): ?>
              <div class="spinner"></div>
              <h3>登录成功！正在跳转...</h3>
              <p style="color: #666; font-size: 14px;">欢迎回来，<?= htmlspecialchars($user['username'] ?? '') ?></p>
            <?php else: ?>
              <h3 style="color: #dc2626;">登录失败</h3>
              <p style="color: #666; font-size: 14px;"><?= htmlspecialchars($message) ?></p>
              <a href="?r=auth/login" class="btn">返回重试</a>
            <?php endif; ?>
          </div>
          <script>
            (function() {
              const data = {
                type: 'oauth_result',
                success: <?= $success ? 'true' : 'false' ?>,
                message: <?= json_encode($message) ?>,
                user: <?= json_encode($user) ?>,
                redirect: <?= View::jsArg($redirect) ?>
              };

              if (window.opener && !window.opener.closed) {
                window.opener.postMessage(data, window.location.origin);
                setTimeout(() => window.close(), 600);
              } else {
                if (data.success) {
                  setTimeout(() => {
                    window.location.href = data.redirect || '?r=home';
                  }, 800);
                }
              }
            })();
          </script>
        </body>
        </html>
        <?php
        exit;
    }
}
