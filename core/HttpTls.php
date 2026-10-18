<?php
/**
 * 蓝屏钙软件导航网 - 出站连接的 TLS 校验配置
 *
 * 各处曾把 VERIFYPEER 关掉，原因是 Windows 版 PHP 的 curl 用的是自己的 OpenSSL，
 * 默认找不到 CA 列表，只开 VERIFYPEER 会以「unable to get local issuer certificate」
 * 全部失败。启用系统证书库（CURLSSLOPT_NATIVE_CA）后既能正常验证，也仍然拒绝自签证书，
 * 所以这里统一开启，不留降级开关。
 */
class HttpTls {
    /**
     * 可直接并入 curl_setopt_array 的校验选项。
     *
     * @return array<int, mixed>
     */
    public static function curlOptions(): array {
        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        // 常量在 PHP 7.4.7 / libcurl 7.71 之前不存在，缺失时退回默认行为而不是致命错误
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        return $options;
    }

    /**
     * 供 stream 系列函数（SMTP 走的就是这条路径）使用的 ssl 上下文参数。
     * PHP 的 stream 层会走系统信任库，实测既能验证正常证书也会拒绝自签证书。
     *
     * @return array{ssl: array<string, mixed>}
     */
    public static function streamContext(): array {
        return ['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI' => true,
            'capture_peer_cert' => false,
        ]];
    }
}
