<?php
/**
 * 蓝屏钙软件导航网 - 人机验证服务
 * 支持 Cloudflare Turnstile、hCaptcha 与轻量级本地算术验证；geetest 仅有前端控件、
 * 尚无服务端校验，已在 getProvider() 归一为 local。
 */

class CaptchaService {
    /**
     * 实际生效的验证提供方。
     *
     * 极验 v4 的服务端二次校验（captcha-validate + HMAC-SHA256 签名比对）尚未实现，
     * 若照原样「取到 lot_number 就算通过」等于把全站防机器人能力关掉；这里统一归一到
     * local，让控件渲染与校验都走算术题，避免出现「弹出极验控件、服务端却无人可校验」。
     */
    public static function getProvider(): string {
        $provider = (string) Setting::get('captcha_provider', 'local');
        if ($provider === 'geetest') {
            static $warned = false;
            if (!$warned) {
                $warned = true;
                error_log('[CaptchaService] captcha_provider=geetest 服务端校验未实现，已按 local 处理');
            }
            return 'local';
        }
        return $provider;
    }

    /**
     * 渲染人机验证控件。
     * $name 用于同一页面存在多个验证码实例时（如评价表单与举报弹窗），
     * 本地算术验证按实例名分别存储答案，互不覆盖。
     */
    public static function renderHtml(string $name = 'default'): string {
        $provider = self::getProvider();
        $siteKey = Setting::get('captcha_site_key', '');

        if ($provider === 'none') {
            return '';
        }

        if ($provider === 'turnstile') {
            if (empty($siteKey)) return self::renderLocalMath($name);
            return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <div class="cf-turnstile" data-sitekey="' . htmlspecialchars($siteKey) . '" data-theme="auto"></div>';
        }

        if ($provider === 'hcaptcha') {
            if (empty($siteKey)) return self::renderLocalMath($name);
            return '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                    <div class="h-captcha" data-sitekey="' . htmlspecialchars($siteKey) . '"></div>';
        }

        // 默认 local 算术验证
        return self::renderLocalMath($name);
    }

    private static function renderLocalMath(string $name = 'default'): string {
        Auth::start();
        $num1 = random_int(1, 10);
        $num2 = random_int(1, 10);
        if (!isset($_SESSION['captcha_math_answers']) || !is_array($_SESSION['captcha_math_answers'])) {
            $_SESSION['captcha_math_answers'] = [];
        }
        $_SESSION['captcha_math_answers'][$name] = $num1 + $num2;

        return '<div class="captcha-math-row">
                    <input type="hidden" name="captcha_name" value="' . htmlspecialchars($name) . '">
                    <label class="captcha-label">' . FluentIcon::render('shield-checkmark', 'fluent-icon-sm') . ' 安全验证: ' . $num1 . ' + ' . $num2 . ' = ?</label>
                    <input type="number" name="captcha_math_input" class="form-input form-input-sm" style="width: 100px;" placeholder="结果" required autocomplete="off">
                </div>';
    }

    public static function verify(array $requestData, string $name = 'default'): bool {
        $provider = self::getProvider();
        if ($provider === 'none') {
            return true;
        }

        $secretKey = Setting::get('captcha_secret_key', '');

        if ($provider === 'turnstile') {
            $token = $requestData['cf-turnstile-response'] ?? '';
            if (empty($token) || empty($secretKey)) {
                // 如果未配置 key，尝试回退校验 local
                return self::verifyLocalMath($requestData['captcha_math_input'] ?? '', $name);
            }
            return self::postVerify('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        }

        if ($provider === 'hcaptcha') {
            $token = $requestData['h-captcha-response'] ?? '';
            if (empty($token) || empty($secretKey)) {
                return self::verifyLocalMath($requestData['captcha_math_input'] ?? '', $name);
            }
            return self::postVerify('https://hcaptcha.com/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        }

        $scope = !empty($requestData['captcha_name']) ? (string)$requestData['captcha_name'] : $name;
        return self::verifyLocalMath($requestData['captcha_math_input'] ?? '', $scope);
    }

    private static function verifyLocalMath($input, string $name): bool {
        Auth::start();
        $expected = $_SESSION['captcha_math_answers'][$name] ?? ($_SESSION['captcha_math_answer'] ?? null);
        if ($expected === null) {
            // 兜底检查任意未匹配的验证码，避免同页单验证码因名不完全一致误拦截
            if (!empty($_SESSION['captcha_math_answers']) && count($_SESSION['captcha_math_answers']) === 1) {
                $expected = reset($_SESSION['captcha_math_answers']);
            } else {
                return false;
            }
        }
        unset($_SESSION['captcha_math_answers'][$name], $_SESSION['captcha_math_answer']);
        return (int) $input === (int) $expected;
    }

    private static function postVerify(string $url, array $params): bool {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ] + HttpTls::curlOptions());
        $res = curl_exec($ch);
        curl_close($ch);
        if (!$res) return false;
        $data = json_decode($res, true);
        return !empty($data['success']);
    }
}
