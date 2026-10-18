<?php
/**
 * 蓝屏钙软件导航网 - AI 智能元数据总结与补全服务
 * 兼容 OpenAI / DeepSeek / 通义千问 / Claude 代理等标准 Chat Completions API
 * 用户未填写的全部详情页字段均由 AI 自动分析、总结并补全
 */

class AiService {
    /** 预取上下文附加在用户提问后面的那句要求 */
    private const RAG_REQUIREMENT = "\n\n要求：请优先基于以上最新抓取与实时检索信息进行客观总结，"
        . "确保开发者、功能特性、是否开源、授权协议及收费模式真实准确，严禁虚构。"
        . "信息不足以核实时宁可写得保守，也不要编造开发者、协议或收费方式。";

    /**
     * 「数据库列名 ↔ 给管理员看的中文标签」的对照表。
     * applyParsedMetadata 用它写 ai_generated_fields，clearAiFields() 再按标签反向清列 ——
     * 两边共用一份，改文案不会让「覆盖重生成」清错字段。
     */
    public const FIELD_LABELS = [
        'developer' => '开发者',
        'publisher' => '发布者',
        'category_id' => '软件分类',
        'special_notes' => '详细说明',
        'tags' => '标签体系',
        'install_type' => '安装包类型',
        'supported_languages' => '支持语言',
        'os_requirements' => '系统要求',
        'other_urls' => '获取渠道',
    ];

    /**
     * 获取所有配置的 AI 服务商列表（按优先级排序）
     */
    public static function getProviders(): array {
        $raw = Setting::get('ai_providers_json', '');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // 兼容单提供商旧设置
        $legacyKey = Setting::get('ai_api_key', '');
        $legacyBase = Setting::get('ai_api_base', 'https://api.openai.com/v1');
        $legacyModel = Setting::get('ai_model', 'gpt-4o-mini');
        $legacyProvider = Setting::get('ai_provider', '默认提供商');

        if (!empty($legacyKey)) {
            return [
                [
                    'name' => $legacyProvider ?: '默认提供商',
                    'api_base' => $legacyBase,
                    'api_key' => $legacyKey,
                    'model' => $legacyModel,
                    'enabled' => '1'
                ]
            ];
        }

        // 默认预设参考列表
        return [
            [
                'name' => 'DeepSeek (主路线)',
                'api_base' => 'https://api.deepseek.com/v1',
                'api_key' => '',
                'model' => 'deepseek-chat',
                'enabled' => '1'
            ],
            [
                'name' => '通义千问 DashScope (备用路线)',
                'api_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'api_key' => '',
                'model' => 'qwen-plus',
                'enabled' => '1'
            ],
            [
                'name' => 'OpenAI (兜底路线)',
                'api_base' => 'https://api.openai.com/v1',
                'api_key' => '',
                'model' => 'gpt-4o-mini',
                'enabled' => '1'
            ]
        ];
    }

    /**
     * 执行底层 Chat Completions HTTP 请求（普通问答形态；签名保持原样以兼容既有调用方）。
     *
     * $purpose / $providerName 只为了写 ai_logs：没有这两个标签，日志就只能看到「调了一次 AI」，
     * 看不出是提交补全、审核补全还是后台连通性测试，也看不出容灾切到了第几路。
     */
    public static function callChatCompletions(string $apiBase, string $apiKey, string $model, string $systemPrompt, string $userPrompt, int $timeout = 15, string $purpose = 'chat', string $providerName = ''): array {
        return self::chatRequest($apiBase, $apiKey, $model, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], [], [], $timeout, $purpose, $providerName);
    }

    /**
     * 唯一的一次 AI 外呼出口：拼请求、发 HTTP、解析、落 ai_logs。
     * 给了 $tools 就按 OpenAI tools 协议发；助手回合里带 tool_calls 时 content 为空也算成功。
     *
     * @param array<int,array<string,mixed>> $messages 完整消息数组（可含 role=tool 的回填）
     * @param array<int,array<string,mixed>> $tools 工具 schema；空数组 = 普通对话
     * @param array<string,mixed> $extra 追加进请求体的字段（tool_choice 等）
     * @param array<string,mixed> $meta 只用于日志打标：tool_rounds / tool_calls
     */
    public static function chatRequest(string $apiBase, string $apiKey, string $model, array $messages,
                                      array $tools = [], array $extra = [], int $timeout = 15,
                                      string $purpose = 'chat', string $providerName = '', array $meta = []): array {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 800
        ] + $extra;
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $started = microtime(true);
        // 每个返回出口都要落一行日志，所以把记录动作收在这一个闭包里
        $record = static function (bool $ok, int $httpStatus, string $error = '', array $usage = []) use (
            $apiBase, $model, $purpose, $providerName, $started, $meta
        ): void {
            AiLog::write([
                'provider' => $providerName !== '' ? $providerName : (parse_url($apiBase, PHP_URL_HOST) ?: ''),
                'api_base' => $apiBase,
                'model' => $model,
                'purpose' => $purpose,
                'http_status' => $httpStatus,
                'result' => $ok ? 'ok' : 'error',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
                'tool_rounds' => (int) ($meta['tool_rounds'] ?? 0),
                'tool_calls' => (int) ($meta['tool_calls'] ?? 0),
                'detail' => $error,
            ]);
            // 对话面板：每次外呼一行。这里同样不写正文，只写「哪条线路、多久、通没通、花了多少 token」
            AiTrace::push($ok ? 'request' : 'error', $ok
                ? "{$providerName} · {$model} 应答 {$httpStatus}，" . round((microtime(true) - $started) * 1000) . 'ms'
                : "{$providerName} · {$model} 调用失败", [
                'provider' => $providerName,
                'model' => $model,
                'http' => $httpStatus,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'tokens' => (int) ($usage['total_tokens'] ?? 0),
                'round' => (int) ($meta['tool_rounds'] ?? 0),
                'error' => $error !== '' ? mb_substr($error, 0, 160) : '',
            ]);
        };

        $url = rtrim($apiBase, '/') . '/chat/completions';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
        ] + HttpTls::curlOptions());

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $record(false, 0, "网络连接异常: {$curlError}");
            return ['success' => false, 'http' => 0, 'error' => "网络连接异常: {$curlError}", 'body' => ''];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = "HTTP 响应码 {$httpCode}";
            if ($response) {
                $errJson = json_decode($response, true);
                if (!empty($errJson['error']['message'])) {
                    $msg .= " (" . $errJson['error']['message'] . ")";
                } elseif (!empty($errJson['message'])) {
                    $msg .= " (" . $errJson['message'] . ")";
                }
            }
            $record(false, $httpCode, $msg);
            return ['success' => false, 'http' => $httpCode, 'error' => $msg, 'body' => (string) $response];
        }

        $json = json_decode((string) $response, true);
        $message = is_array($json['choices'][0]['message'] ?? null) ? $json['choices'][0]['message'] : [];
        $content = is_string($message['content'] ?? null) ? $message['content'] : '';
        $toolCalls = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
        // 服务商一般会在 usage 里回 token，以前整段被丢掉，计费与「为什么变慢」只能靠猜
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        if ($content === '' && $toolCalls === []) {
            $record(false, $httpCode, 'API 响应结构中缺少有效 content', $usage);
            return ['success' => false, 'http' => $httpCode, 'error' => 'API 响应结构中缺少有效 content', 'body' => (string) $response];
        }

        $record(true, $httpCode, '', $usage);
        return [
            'success' => true, 'http' => $httpCode, 'content' => $content,
            'message' => $message, 'tool_calls' => $toolCalls, 'usage' => $usage,
        ];
    }

    /**
     * 给模型用的工具清单 —— 这就是「跟 AI 网页端一样的联网体验」的那半：
     * 不是我们替它抓完再喂，而是它自己决定搜什么、翻几轮、打开哪条结果。
     * @return array<int,array<string,mixed>>
     */
    public static function toolSpecs(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'web_search',
                    'description' => '搜索这个软件相关的网页，返回标题、链接与摘要列表。不确定开发者、官网、是否开源、协议或收费方式时先用它。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => '搜索词，建议带上「官网」「GitHub」这类限定词'],
                            'count' => ['type' => 'integer', 'description' => '想要几条结果，1-8，默认 5']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'open_page',
                    'description' => '打开一个网页并返回正文纯文本。搜索结果不够定论时，用它读官网或 GitHub 仓库页。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string', 'description' => '完整 http/https 地址，必须来自搜索结果或已知官网']
                        ],
                        'required' => ['url']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'github_readme',
                    'description' => '读取某个 GitHub 仓库的 README 正文，用来确认项目定位、开源协议与维护方。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'repo_url' => ['type' => 'string', 'description' => 'https://github.com/owner/repo 形式的仓库地址']
                        ],
                        'required' => ['repo_url']
                    ]
                ]
            ]
        ];
    }

    /**
     * 这次失败像是「本线路不支持 tools」吗？
     * 不支持就得立刻回落到「服务端预取 + 单次问答」，而不是拿同样的请求重试到预算耗尽。
     */
    public static function looksLikeNoTools(array $res): bool {
        $status = (int) ($res['http'] ?? 0);
        if ($status !== 400 && $status !== 422) {
            return false;
        }
        $text = strtolower((string) ($res['error'] ?? '') . ' ' . ($res['body'] ?? ''));
        foreach (['tool', 'function', 'functions', 'not supported', 'unsupported', 'is not valid'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 带工具的多轮对话。模型不再只有一次机会：它可以先搜、再翻网页、再下结论。
     * 轮数与总时长都受设置约束（ai_tool_rounds / ai_total_timeout），到点就带着已收集到的东西收尾。
     *
     * @return array{success:bool,http:int,content?:string,error?:string,unsupported?:bool,
     *               sources:array,tool_rounds:int,tool_calls:int}
     */
    public static function callWithTools(string $apiBase, string $apiKey, string $model, string $systemPrompt,
                                        string $userPrompt, int $timeoutBudget, string $purpose = 'chat',
                                        string $providerName = ''): array {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
        $tools = self::toolSpecs();
        $maxRounds = max(0, min(4, (int) Setting::get('ai_tool_rounds', '3')));
        $startedAt = microtime(true);
        $sources = [];
        $calls = 0;

        for ($round = 0; $round <= $maxRounds; $round++) {
            $elapsed = (int) round(microtime(true) - $startedAt);
            $remaining = max(5, min(20, $timeoutBudget - $elapsed));
            if ($timeoutBudget - $elapsed <= 0) {
                break;
            }
            $res = self::chatRequest($apiBase, $apiKey, $model, $messages, $tools, ['tool_choice' => 'auto'],
                $remaining, $purpose, $providerName, ['tool_rounds' => $round, 'tool_calls' => $calls]);

            if (!$res['success']) {
                return [
                    'success' => false, 'http' => (int) $res['http'], 'error' => (string) $res['error'],
                    'unsupported' => self::looksLikeNoTools($res),
                    'sources' => $sources, 'tool_rounds' => $round, 'tool_calls' => $calls,
                ];
            }
            $toolCalls = $res['tool_calls'];
            if ($toolCalls === []) {
                AiTrace::push('answer', '模型给出最终答案（' . mb_strlen((string) $res['content']) . ' 字），开始解析', [
                    'rounds' => $round, 'tool_calls' => $calls,
                ]);
                return [
                    'success' => true, 'http' => (int) $res['http'], 'content' => (string) $res['content'],
                    'sources' => $sources, 'tool_rounds' => $round, 'tool_calls' => $calls,
                ];
            }

            // 助手回合要原样回填（含 tool_calls），否则下一轮模型看不懂自己刚才要了什么
            $messages[] = [
                'role' => 'assistant',
                'content' => $res['message']['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];
            foreach ($toolCalls as $call) {
                $calls++;
                $fn = (string) ($call['function']['name'] ?? '');
                $args = (string) ($call['function']['arguments'] ?? '');
                AiTrace::push('assistant', '模型第 ' . ($round + 1) . ' 轮要求调用 ' . ($fn !== '' ? $fn : '未知工具'), [
                    'args' => mb_substr(preg_replace('/\s+/u', ' ', $args) ?? $args, 0, 200),
                ]);
                $outcome = self::runTool(is_array($call) ? $call : [], $sources);
                $sources = $outcome['sources'];
                AiTrace::push('tool', $fn . ' → ' . mb_substr(trim(preg_replace('/\s+/u', ' ', $outcome['text']) ?? $outcome['text']), 0, 120), [
                    'chars' => mb_strlen($outcome['text']),
                    'sources_total' => count($sources),
                ]);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($call['id'] ?? ''),
                    'content' => $outcome['text'],
                ];
            }
        }

        return [
            'success' => false, 'http' => 0,
            'error' => '工具轮数或时间预算已用尽，模型仍未给出最终答案',
            'retryable' => true, 'sources' => $sources, 'tool_rounds' => $maxRounds, 'tool_calls' => $calls,
        ];
    }

    /**
     * 执行一次工具调用。抓取一律走 WebSearch / WebFetcher：
     * 地址是模型给的，所以 SSRF 校验、robots 与同主机间隔都必须在这一层生效，不能因为「模型说要访问」就放行。
     * @param array<string,mixed> $call
     * @return array{text:string, sources:array}
     */
    public static function runTool(array $call, array $sources = []): array {
        $name = (string) ($call['function']['name'] ?? '');
        $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
        if (!is_array($args)) {
            $args = [];
        }

        if ($name === 'web_search') {
            $items = WebSearch::search((string) ($args['query'] ?? ''), max(1, min(8, (int) ($args['count'] ?? 5))));
            if ($items === []) {
                return ['text' => '没有搜到相关结果。', 'sources' => $sources];
            }
            $lines = [];
            foreach ($items as $i => $item) {
                $lines[] = ($i + 1) . '. ' . $item['title'] . "\n   " . $item['url'] . "\n   " . $item['snippet'];
                $sources[$item['url']] = ['title' => $item['title'], 'url' => $item['url']];
            }
            return ['text' => implode("\n", $lines), 'sources' => $sources];
        }

        if ($name === 'open_page') {
            $url = trim((string) ($args['url'] ?? ''));
            if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
                return ['text' => '地址无效（只允许完整的 http/https 链接）。', 'sources' => $sources];
            }
            $page = WebFetcher::read($url, 3000);
            if (!$page['ok']) {
                return ['text' => '打开失败：' . $page['error'], 'sources' => $sources];
            }
            $sources[$page['url']] = ['title' => $page['title'] !== '' ? $page['title'] : $page['url'], 'url' => $page['url']];
            return ['text' => '标题: ' . $page['title'] . "\n\n正文:\n" . $page['text'], 'sources' => $sources];
        }

        if ($name === 'github_readme') {
            $repo = trim((string) ($args['repo_url'] ?? ''));
            if ($repo === '' || !class_exists('GithubService')) {
                return ['text' => '无法读取该仓库。', 'sources' => $sources];
            }
            $html = GithubService::getReadme($repo);
            if (!$html) {
                return ['text' => 'README 取不到（仓库不存在、私有或网络失败）。', 'sources' => $sources];
            }
            $text = WebFetcher::parsePage($html)['text'];
            $sources[$repo] = ['title' => 'GitHub README ' . $repo, 'url' => $repo];
            return ['text' => mb_substr($text, 0, 3000), 'sources' => $sources];
        }

        return ['text' => '未知工具：' . $name, 'sources' => $sources];
    }

    /**
     * 这次失败值不值得在同一条线路上重试。
     *
     * 429/5xx/0（连不上、超时）都是一次性的抖动，重试有意义；
     * 其它 4xx 基本是密钥不对、模型名写错、地址不是 /v1 这类配置问题 ——
     * 重试三次只会让提交页多转十几秒，所以直接换线路。
     */
    public static function isRetryable(array $callResult): bool
    {
        if (!empty($callResult['success'])) {
            return false;
        }
        $status = (int) ($callResult['http'] ?? 0);
        if ($status === 429 || $status >= 500) {
            return true;
        }
        if ($status >= 400 && $status < 500) {
            return false;
        }
        return true;
    }

    /**
     * 单个 AI 服务商连通性测试
     */
    public static function testProvider(string $apiBase, string $apiKey, string $model): array {
        $start = microtime(true);
        $res = self::callChatCompletions(
            $apiBase,
            $apiKey,
            $model,
            "You are an API healthchecker. Reply only: {\"status\":\"ok\"}",
            "ping",
            10,
            'test_provider',
            (string) (parse_url($apiBase, PHP_URL_HOST) ?: $apiBase)
        );
        $duration = round((microtime(true) - $start) * 1000);
        if ($res['success']) {
            return [
                'success' => true,
                'message' => "连通成功！模型 [{$model}] 正常响应 (耗时 {$duration}ms)"
            ];
        }
        return [
            'success' => false,
            'message' => "连接失败: " . ($res['error'] ?? '未知错误') . " (耗时 {$duration}ms)"
        ];
    }

    /**
     * 智能补全软件详情元数据（多服务商自动切换与容灾兜底）
     */
    public static function completeSoftwareMetadata(array $software, array $allCategories = [], string $purpose = 'submit_enrich'): array {
        $missingFields = [];
        if (empty($software['special_notes'])) $missingFields[] = 'special_notes';
        if (empty($software['developer'])) $missingFields[] = 'developer';
        if (empty($software['publisher'])) $missingFields[] = 'publisher';
        if (empty($software['category_id']) || $software['category_id'] <= 0) $missingFields[] = 'category_id';
        if (empty($software['tags'])) $missingFields[] = 'tags';
        if (empty($software['install_type'])) $missingFields[] = 'install_type';
        if (empty($software['supported_languages'])) $missingFields[] = 'supported_languages';
        if (empty($software['os_requirements'])) $missingFields[] = 'os_requirements';
        if (empty($software['other_urls']) || $software['other_urls'] === '[]') $missingFields[] = 'other_urls';

        // 如果无需补全，直接返回
        if (empty($missingFields)) {
            return [];
        }

        // 分类名称对照
        $catNames = array_column($allCategories, 'name', 'id');
        $catListStr = implode('、', array_values($catNames));

        $enabled = Setting::get('ai_enabled', '1');
        if (!$enabled || $enabled === '0') {
            return self::offlineFallbackComplete($software, $missingFields, $allCategories);
        }

        $allProviders = self::getProviders();
        $activeProviders = [];
        foreach ($allProviders as $p) {
            if (!empty($p['enabled']) && !empty(trim($p['api_key'] ?? ''))) {
                $activeProviders[] = $p;
            }
        }

        // 未配置有效 API Key，直接使用本地离线知识库
        if (empty($activeProviders)) {
            return self::offlineFallbackComplete($software, $missingFields, $allCategories);
        }

        $systemPrompt = "你是一个精通 Windows 软件生态的信息架构与总结专家。你的任务是根据软件名称、官网及开源信息，全面总结并补全该软件缺失的全部元数据。
要求：
1. 语言：简体中文。
2. 风格：专业、客观、精炼、微软 Fluent 风格，禁止空话套话。
3. 必须输出严格的合法 JSON，不要加 markdown 代码块包裹，字段列表：
   - developer: 开发者或开发团队名称（如 Microsoft、个人开发者、开源团队）
   - publisher: 发布者（如 Microsoft Corporation、官方社区）
   - category_id: 最佳匹配分类ID (从可选分类中选取其ID：{$catListStr})
   - special_notes: 详细说明，必须使用 Markdown 语法撰写，包含「## 功能特性」列表、运行环境与注意事项
   - is_open_source: 1(开源) 或 0(闭源)
   - license: 开源协议（开源时填写，如 MIT, Apache-2.0, GPL-3.0，闭源写 null）
   - price_type: 'free'(免费) 或 'paid'(付费) 或 'freemium'(免费增值/内购)
   - price_desc: 收费方式与参考价格描述（如 '免费获取'、'一次性买断 ￥99'、'订阅制 ￥19/月'）
   - install_type: 安装包类型（如 '标准安装包 / 免安装便携版'、'MSIX 商店打包'、'单文件免安装'）
   - supported_languages: 支持语言（如 '简体中文, 英语 (多语言支持)'）
   - os_requirements: 系统要求（如 'Windows 10 版本 17763.0 或更高版本 / Windows 11 (x64)'）
   - tags: 3到5个精炼中文标签数组，如 [\"开源\", \"效率工具\", \"便携版\"]
   - other_urls: 其它常见合法分发渠道数组（格式：[{\"type\":\"Github\",\"name\":\"渠道名\",\"url\":\"链接\"}]，若无则为空数组）";

        $userPrompt = "软件名称: {$software['name']}
官网: " . ($software['official_url'] ?? '未提供') . "
GitHub: " . ($software['github_url'] ?? '未提供') . "
目前需要补全总结的字段: " . implode(', ', $missingFields);

        // RAG 的两条路：① 线路支持 tools → 模型自己搜、自己翻网页（这才是网页端那种体验）；
        // ② 不支持或关了工具 → 服务端先抓完再一次性喂。抓取动作都收敛在 WebSearch / WebFetcher 两层里。
        $sources = [];
        $useTools = Setting::get('ai_tool_use', '1') !== '0';
        $prefetched = null;

        $attemptErrors = [];
        $startedAt = microtime(true);
        $attempts = max(1, min(5, (int) Setting::get('ai_retries', '3')));
        // 总预算：重试很容易把一次请求拖成几十秒，超预算就换线路/兜底，不再排队等
        $budget = max(10, min(120, (int) Setting::get('ai_total_timeout', '20')));

        AiTrace::push('plan', '待补 ' . count($missingFields) . ' 个字段，'
            . count($activeProviders) . ' 条线路可用，工具调用' . ($useTools ? '开' : '关'), [
            'fields' => array_map(static fn(string $f): string => self::FIELD_LABELS[$f] ?? $f, $missingFields),
            'budget' => $budget,
            'retries' => $attempts,
            'providers' => array_map(static fn(array $p): string => (string) ($p['name'] ?? '?') . '·' . (string) ($p['model'] ?? '?'), $activeProviders),
            'tools' => $useTools,
        ]);

        // 依次尝试所有启用的 AI 提供商，出错时无缝自动切换
        foreach ($activeProviders as $provider) {
            $providerName = $provider['name'] ?? '提供商';
            $apiBase = trim($provider['api_base'] ?? 'https://api.openai.com/v1');
            $apiKey = trim($provider['api_key'] ?? '');
            $model = trim($provider['model'] ?? 'gpt-4o-mini');

            AiTrace::push('switch', '现在轮到线路 ' . $providerName . '（' . $model . '）', [
                'provider' => $providerName,
                'model' => $model,
                'elapsed' => round(microtime(true) - $startedAt, 1),
            ]);

            // 同一条线路上重试：服务商偶发 5xx / 限流 / 超时 / 吐回的不是能用的 JSON，都很常见，
            // 以前一次失败就跳走，等于把「再问一次就好」的请求丢给下一条线路或离线兜底。
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $elapsed = microtime(true) - $startedAt;
                if ($elapsed >= $budget) {
                    $attemptErrors[] = "{$providerName} 已用时 " . round($elapsed) . "s，超出总预算 {$budget}s，停止重试";
                    AiTrace::push('error', '已用 ' . round($elapsed) . 's，超出总预算 ' . $budget . 's，不再等下去', [
                        'provider' => $providerName, 'budget' => $budget,
                    ]);
                    break 2;
                }
                $remaining = min(20, max(5, $budget - (int) ceil($elapsed)));

                if ($useTools) {
                    $callResult = self::callWithTools($apiBase, $apiKey, $model, $systemPrompt, $userPrompt,
                        $remaining, $purpose, $providerName);
                    foreach ((array) ($callResult['sources'] ?? []) as $url => $item) {
                        $sources[(string) $url] = $item;
                    }
                    if (!$callResult['success'] && !empty($callResult['unsupported'])) {
                        // 这条线路不认 tools：同一次尝试里立刻改用「服务端预取 + 单次问答」，不多耗一轮
                        $useTools = false;
                        $attemptErrors[] = "{$providerName} 不支持 tools，已改用服务端预取上下文的方式";
                        error_log('[AiService] ' . end($attemptErrors));
                    }
                }

                if (!$useTools) {
                    if ($prefetched === null) {
                        // 抓一次网页要好几秒，只有真要走这条路才抓
                        AiTrace::push('tool', '这条线路不认工具，改由服务端先检索再一次性喂', ['mode' => 'prefetch']);
                        $prefetched = self::retrieveWebContext($software, $sources);
                        AiTrace::push('tool', '服务端预取完成，参考正文 ' . mb_strlen($prefetched) . ' 字', [
                            'chars' => mb_strlen($prefetched), 'sources_total' => count($sources),
                        ]);
                    }
                    $prompt = $userPrompt . ($prefetched !== ''
                        ? "\n\n【最新联网检索与实测参考信息】:\n" . $prefetched . self::RAG_REQUIREMENT : '');
                    $callResult = self::callChatCompletions($apiBase, $apiKey, $model, $systemPrompt, $prompt,
                        $remaining, $purpose, $providerName);
                }

                if (!empty($callResult['success']) && !empty($callResult['content'])) {
                    $content = trim($callResult['content']);
                    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
                    $content = preg_replace('/\s*```$/', '', $content);

                    $parsed = json_decode($content, true);
                    $answered = is_array($parsed) && !empty($parsed);
                    $result = $answered ? self::applyParsedMetadata($parsed, $software, $missingFields) : [];
                    if ($answered || !empty($result)) {
                        // 线路正常答了、只是没有新东西可补（字段都被人工填过了）—— 这是成功，不是失败。
                        // 以前会一路降级到本地规则兜底，并在结论里写「所有线路都未应答」，把人误导去查日志。
                        $result['ai_provider_used'] = $providerName;
                        $result['ai_attempts'] = $attempt;
                        $result['ai_sources'] = array_values($sources);
                        $filled = array_map(
                            static fn(string $f): string => (string) (self::FIELD_LABELS[$f] ?? $f),
                            array_values(array_intersect(array_keys($result), self::WRITABLE_COLUMNS))
                        );
                        AiTrace::push('applied', $answered
                            ? '答案已解析，写入 ' . count($filled) . ' 个字段'
                            : '应答正常，但没有需要新补的空缺字段', [
                            'filled' => $filled,
                            'provider' => $providerName,
                            'seconds' => round(microtime(true) - $startedAt, 1),
                            'sources' => count($sources),
                        ]);
                        return $result;
                    }
                    // 线路是通的，只是这一轮答案不能用 —— 这种失败重试有意义
                    $reason = is_array($parsed) && empty($parsed)
                        ? '返回的 JSON 里没有可用字段'
                        : '解析 JSON 失败';
                } else {
                    $reason = '请求失败: ' . ($callResult['error'] ?? '未知错误');
                    if (!self::isRetryable($callResult)) {
                        // 401/403/404/400 属于配置或模型名不对，重试三次只是让访客多等十几秒
                        $attemptErrors[] = "{$providerName} {$reason}（不可重试，直接换线路）";
                        break;
                    }
                }

                if ($attempt >= $attempts) {
                    $attemptErrors[] = "{$providerName} {$reason}（已重试 {$attempt} 次）";
                    AiTrace::push('error', $providerName . ' 重试 ' . $attempt . ' 次仍未成功，换下一条线路', [
                        'provider' => $providerName, 'reason' => mb_substr($reason, 0, 160),
                    ]);
                    break;
                }
                usleep($attempt === 1 ? 400000 : 1200000);   // 退避：400ms、1.2s
                AiTrace::push('retry', $providerName . ' 第 ' . $attempt . ' 次没成功，退避后再问一次', [
                    'provider' => $providerName, 'reason' => mb_substr($reason, 0, 160),
                ]);
                error_log("[AiService 重试] 提供商 [{$providerName}] 第 {$attempt} 次失败（{$reason}），退避后再试一次。");
            }

            // 本线路用尽，继续循环触发下一备用线路
            error_log("[AiService 容灾切换] 提供商 [{$providerName}] 异常，自动切换下一节点。详情: " . end($attemptErrors));
        }

        // 所有提供商均失败时，由本地知识库与规则引擎兜底
        error_log("[AiService] 所有配置的 AI 提供商均无法正常响应 (" . implode('; ', $attemptErrors) . ")，已无缝切换到本地离线规则引擎完成补全。");
        AiTrace::push('fallback', '线路都没能给出结果，改用站内离线规则补全', [
            'reasons' => array_map(static fn(string $s): string => mb_substr($s, 0, 160), $attemptErrors),
        ]);
        $fallback = self::offlineFallbackComplete($software, $missingFields, $allCategories);
        // 就算 AI 一行没写成，抓到的网页也值得留下 —— 后台能据此判断「兜底不是凭空猜的」
        $fallback['ai_sources'] = array_values($sources);
        return $fallback;
    }

    /**
     * AI 允许写入的软件列。任何「把补全结果落库」的路径都只认这张表里的键，
     * 不让提示词里冒出来的野字段被拼进 UPDATE。
     */
    public const WRITABLE_COLUMNS = [
        'developer', 'publisher', 'category_id', 'special_notes', 'tags', 'install_type',
        'supported_languages', 'os_requirements', 'other_urls', 'is_open_source', 'license',
        'price_type', 'price_desc',
    ];

    /**
     * 把 softwares.ai_sources 里存的引用解成能直接渲染的列表。
     * 链接是搜索结果与模型带回来的第三方地址，这里**重新过一遍校验**：入库时的校验挡不住
     * 库里躺着的历史脏数据，也挡不住有人直接改库 —— 输出侧永远不能信任存储侧。
     *
     * @param array|string|null $json
     * @return array<int,array{title:string,url:string,host:string}>
     */
    public static function normalizeSources($json, int $limit = 8): array {
        if (is_array($json)) {
            $decoded = $json;
        } else {
            $trimmed = trim((string) $json);
            $decoded = ($trimmed === '' || $trimmed === '[]') ? [] : (json_decode($trimmed, true) ?: []);
        }

        $out = [];
        $seen = [];
        $cap = max(1, min(20, $limit));
        foreach ((array) $decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            // Url::isSafe 已经限定 http(s)、拒控制字符与引号空格；再显式要求协议，双保险
            if ($url === '' || !Url::isSafe($url) || preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }
            $key = strtolower($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($row['title'] ?? '')) ?? '');
            $out[] = [
                'title' => mb_substr($title !== '' ? $title : ($host !== '' ? $host : $url), 0, 120),
                'url' => $url,
                'host' => mb_substr($host, 0, 80),
            ];
            if (count($out) >= $cap) {
                break;
            }
        }
        return $out;
    }

    /**
     * 把一次补全的结果翻译成人话：用了哪条线路、第几次成功、补了几个字段、为什么降级成本地兜底。
     * 审核页、后台「重新生成」按钮与开放 API 都显示同一句话，别各写一套。
     */
    public static function describeRun(array $ai): string {
        if (empty($ai['ok'])) {
            return '失败（' . (string) ($ai['error'] ?: '未知错误') . '）';
        }
        $count = count($ai['filled'] ?? []);
        if (($ai['source'] ?? '') === 'ai') {
            $which = '线路「' . (string) $ai['provider'] . '」第 ' . (int) $ai['attempts'] . ' 次应答';
            return $count === 0
                ? $which . '，但没有需要补的空缺（人工与审核填过的内容不会被覆盖）'
                : $which . '，补全 ' . $count . ' 个字段';
        }
        if (($ai['source'] ?? '') === 'offline') {
            return '所有线路都未应答，已用本地规则兜底补 ' . $count . ' 个字段（原因看【AI 调用日志】：密钥、地址或服务器出网）';
        }
        return '没有需要补的空缺，资料保持原样';
    }

    /**
     * 立刻为某条软件跑一次 AI 补全并写库。
     * 审核通过时的「AI 补全后发布」、后台的「重新生成」按钮、开放 API 的 aiRetry 全走这里 ——
     * 「怎么写」的规则只有一份，不会出现三条路径行为不一致的情况。
     *
     * @return array{ok:bool,filled:array,source:string,provider:string,attempts:int,error:?string,cleared:array}
     *               source：ai=线路真的答了 / offline=本地规则兜底 / none=没有需要补的
     */
    public static function completeSoftware(int $softwareId, string $purpose = 'approve_ai', bool $overwrite = false): array {
        $result = ['ok' => false, 'filled' => [], 'cleared' => [], 'source' => 'none',
            'provider' => '', 'attempts' => 0, 'error' => null];

        $software = Software::find($softwareId);
        if (!$software) {
            $result['error'] = '软件不存在';
            return $result;
        }

        if ($overwrite) {
            $result['cleared'] = self::clearAiFields($softwareId, $software);
            $software = Software::find($softwareId) ?: $software;   // 清空后要重读，AI 才看得到哪些字段真的空了
        }

        $ai = self::completeSoftwareMetadata($software, Category::getAll(), $purpose);

        $fill = [];
        foreach (self::WRITABLE_COLUMNS as $column) {
            if (array_key_exists($column, $ai) && $ai[$column] !== null && $ai[$column] !== '') {
                $fill[$column] = $ai[$column];
            }
        }
        $result['source'] = !empty($ai['ai_provider_used']) ? 'ai' : (!empty($ai) ? 'offline' : 'none');
        $result['provider'] = (string) ($ai['ai_provider_used'] ?? '');
        $result['attempts'] = (int) ($ai['ai_attempts'] ?? 0);

        if (!empty($ai['ai_generated_fields'])) {
            $fill['is_ai_generated'] = 1;
            $fill['ai_generated_fields'] = $ai['ai_generated_fields'];
        }
        if (!empty($ai['ai_sources'])) {
            $fill['ai_sources'] = json_encode(array_values((array) $ai['ai_sources']), JSON_UNESCAPED_UNICODE);
        }

        // 静态兜底只填 AI 也没拿到的那些空缺，而且必须在 AI 之后 ——
        // 先占位会让 AI 的「非空即跳过」永远填不进去
        $fill = array_merge(self::fillStaticDefaults($software, $fill), $fill);

        try {
            if ($fill !== []) {
                // 走 Software::update()：description_md 变了要顺带重算 summary
                Software::update($softwareId, $fill);
            }
            $result['ok'] = true;
            $result['filled'] = array_keys($fill);
        } catch (Throwable $e) {
            $result['error'] = mb_substr($e->getMessage(), 0, 200);
        }
        return $result;
    }

    /**
     * 「覆盖重生成」：按 ai_generated_fields 的中文标签反查列名，只清上次 AI 写过的文本字段。
     * 用户和审核人手工填的内容不在标签映射里，不会被清掉；分类也不清（清成空会连带影响归属与筛选）。
     * @return array<int,string> 被清空的列
     */
    public static function clearAiFields(int $softwareId, array $software): array {
        $labels = is_string($software['ai_generated_fields'] ?? null)
            ? (json_decode((string) $software['ai_generated_fields'], true) ?: []) : [];
        $clear = [];
        foreach (self::FIELD_LABELS as $column => $label) {
            if ($column === 'category_id') {
                continue;
            }
            if (in_array($label, $labels, true) && array_key_exists($column, $software)) {
                $clear[$column] = $column === 'other_urls' ? '[]' : null;
            }
        }
        if ($clear !== []) {
            Software::update($softwareId, $clear);
        }
        return array_keys($clear);
    }

    /**
     * AI 也没补上的空缺用固定兜底填掉，保证详情页不出现空白。
     * 这些值以前写在提交请求里，结果先把字段占满、AI 再进来时永远不动 —— 兜底必须在生成之后做。
     */
    public static function fillStaticDefaults(array $software, array $already = []): array {
        $name = trim((string) ($software['name'] ?? ''));
        $pool = [
            'category_id' => 1,
            'developer' => $name !== '' ? $name . ' 官方团队' : '未知开发者',
            'publisher' => $name !== '' ? $name . ' 官方团队' : '未知发布者',
            'install_type' => '标准安装包 / 免安装便携版',
            'supported_languages' => '简体中文, 英语 (多语言支持)',
            'os_requirements' => 'Windows 10 版本 17763.0 或更高版本 / Windows 11',
        ];
        $out = [];
        foreach ($pool as $column => $fallback) {
            $current = $already[$column] ?? $software[$column] ?? null;
            if ($current === null || $current === '' || ($column === 'category_id' && (int) $current <= 0)) {
                $out[$column] = $fallback;
            }
        }
        return $out;
    }

    /**
     * 服务端检索增强（RAG）：线路不支持 tools、或后台关了工具时走这一条 ——
     * 服务端替模型「先搜再答」：定向抓官网/GitHub，再主动搜一次并读头号结果的正文。
     * 抓取全部经 WebSearch / WebFetcher：SSRF 校验、robots、同主机间隔都在那两层里生效。
     *
     * @param array<string,array{title:string,url:string}> $sources 引用来源累加器（键是 URL），最后写进 softwares.ai_sources
     */
    public static function retrieveWebContext(array $software, array &$sources = []): string {
        if (Setting::get('ai_web_search_enabled', '1') === '0') {
            return '';
        }

        $sections = [];
        $pageChars = max(600, min(8000, (int) Setting::get('ai_page_chars', '3000')));

        // 1. 定向抓官网：地址是用户自己填的，这是最可信的一手信息
        $official = trim((string) ($software['official_url'] ?? ''));
        if ($official !== '' && filter_var($official, FILTER_VALIDATE_URL)) {
            $page = WebFetcher::read($official, $pageChars);
            if ($page['ok'] && $page['text'] !== '') {
                $sources[$page['url']] = ['title' => $page['title'] !== '' ? $page['title'] : $page['url'], 'url' => $page['url']];
                $sections[] = "【官网实测抓取 ({$page['url']})】
标题: {$page['title']}
{$page['text']}";
            }
        }

        // 2. 定向抓 GitHub README：开源软件的开发方、协议、项目定位在这里最准
        $github = trim((string) ($software['github_url'] ?? ''));
        if ($github !== '' && class_exists('GithubService')) {
            $readme = GithubService::getReadme($github);
            if ($readme) {
                $text = mb_substr(WebFetcher::parsePage($readme)['text'], 0, $pageChars);
                if ($text !== '') {
                    $sources[$github] = ['title' => 'GitHub 仓库 ' . $github, 'url' => $github];
                    $sections[] = "【GitHub 仓库实测抓取 ({$github})】
" . $text;
                }
            }
        }

        // 3. 主动搜索一次 —— 以前是「前面没抓到才搜」，等于搜索基本不参与；
        //    改成无条件搜才接近网页端「先查资料再动笔」的行为
        $name = trim((string) ($software['name'] ?? ''));
        if ($name === '') {
            return implode("

", $sections);
        }
        $results = WebSearch::search($name . ' 官网 介绍 Windows 软件', 5);
        if ($results === []) {
            return implode("

", $sections);
        }
        $lines = ['【网络搜索结果（可直接引用其中的链接）】'];
        foreach ($results as $i => $item) {
            $lines[] = ($i + 1) . '. ' . $item['title'] . "
   链接: {$item['url']}
   摘要: {$item['snippet']}";
            $sources[$item['url']] = ['title' => $item['title'] !== '' ? $item['title'] : $item['url'], 'url' => $item['url']];
        }
        $sections[] = implode("
", $lines);

        // 摘要往往不足以定开发者与协议 —— 再把头号结果读一遍正文
        if (count($sections) < 3 && !empty($results[0]['url'])) {
            $page = WebFetcher::read((string) $results[0]['url'], $pageChars, self::searchReferer((string) ($results[0]['source'] ?? '')));
            if ($page['ok'] && $page['text'] !== '') {
                $sources[$page['url']] = ['title' => $page['title'] !== '' ? $page['title'] : $page['url'], 'url' => $page['url']];
                $sections[] = "【搜索结果正文实测 ({$page['url']})】
标题: {$page['title']}
{$page['text']}";
            }
        }

        return implode("

", $sections);
    }

    /** 从搜索结果点进正文页时该带哪个 Referer（带对了才不像脚本） */
    private static function searchReferer(string $backend): string
    {
        if (str_contains($backend, 'duckduckgo')) {
            return 'https://duckduckgo.com/';
        }
        if ($backend === 'bing') {
            return 'https://www.bing.com/';
        }
        return '';
    }

    /**
     * 将 AI 提取的合法字段映射到软件数据中
     */
    public static function applyParsedMetadata(array $parsed, array $software, array $missingFields): array {
        $result = [];
        $filled = [];

        if (empty($software['developer']) && !empty($parsed['developer'])) {
            $result['developer'] = $parsed['developer'];
            $filled[] = self::FIELD_LABELS['developer'];
        }
        if (empty($software['publisher']) && !empty($parsed['publisher'])) {
            $result['publisher'] = $parsed['publisher'];
            $filled[] = self::FIELD_LABELS['publisher'];
        }
        if ((empty($software['category_id']) || $software['category_id'] <= 0) && !empty($parsed['category_id'])) {
            $result['category_id'] = (int)$parsed['category_id'];
            $filled[] = self::FIELD_LABELS['category_id'];
        }
        if (empty($software['special_notes']) && !empty($parsed['special_notes'])) {
            $result['special_notes'] = $parsed['special_notes'];
            $filled[] = self::FIELD_LABELS['special_notes'];
        }
        if (empty($software['tags']) && !empty($parsed['tags']) && is_array($parsed['tags'])) {
            $result['tags'] = implode(',', array_slice($parsed['tags'], 0, 6));
            $filled[] = self::FIELD_LABELS['tags'];
        }
        if (empty($software['install_type']) && !empty($parsed['install_type'])) {
            $result['install_type'] = $parsed['install_type'];
            $filled[] = self::FIELD_LABELS['install_type'];
        }
        if (empty($software['supported_languages']) && !empty($parsed['supported_languages'])) {
            $result['supported_languages'] = $parsed['supported_languages'];
            $filled[] = self::FIELD_LABELS['supported_languages'];
        }
        if (empty($software['os_requirements']) && !empty($parsed['os_requirements'])) {
            $result['os_requirements'] = $parsed['os_requirements'];
            $filled[] = self::FIELD_LABELS['os_requirements'];
        }
        if (isset($parsed['is_open_source']) && !isset($software['is_open_source'])) {
            $result['is_open_source'] = (int)$parsed['is_open_source'];
        }
        if (!empty($parsed['license']) && empty($software['license']) && ($result['is_open_source'] ?? $software['is_open_source'] ?? 1) == 1) {
            $result['license'] = $parsed['license'];
        }
        if (isset($parsed['price_type']) && in_array($parsed['price_type'], ['free', 'paid', 'freemium']) && empty($software['price_type'])) {
            $result['price_type'] = $parsed['price_type'];
        }
        if (!empty($parsed['price_desc']) && empty($software['price_desc'])) {
            $result['price_desc'] = $parsed['price_desc'];
        }
        if ((empty($software['other_urls']) || $software['other_urls'] === '[]') && !empty($parsed['other_urls'])) {
            $result['other_urls'] = is_array($parsed['other_urls']) ? json_encode($parsed['other_urls'], JSON_UNESCAPED_UNICODE) : $parsed['other_urls'];
            $filled[] = self::FIELD_LABELS['other_urls'];
        }

        if (!empty($filled)) {
            $result['is_ai_generated'] = 1;
            $result['ai_generated_fields'] = json_encode($filled, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    /**
     * 智能离线知识库与规则引擎：在没有配置 AI Key 或网络异常时，全自动精准补全
     */
    public static function offlineFallbackComplete(array $software, array $missing, array $allCategories): array {
        $result = [];
        $filled = [];
        $name = trim($software['name'] ?? '');
        $lowerName = strtolower($name);

        // 知名软件精确预设知识库
        $knownApps = [
            'vs code' => [
                'summary' => '微软推出的现代化跨平台轻量级代码编辑器，拥有庞大繁荣的插件生态。',
                'developer' => 'Microsoft',
                'publisher' => 'Microsoft Corporation',
                'category' => '开发编程',
                'special_notes' => '支持丰富扩展插件、代码高亮、智能补全与内置 Git 版本控制。',
                'tags' => ['开发工具', '开源', '代码编辑', '微软'],
                'install_type' => '独立用户安装包 / 便携免安装版',
                'supported_languages' => '简体中文, 英语 (多语言支持)',
                'os_requirements' => 'Windows 10 / Windows 11 (x64 / ARM64)',
                'is_open_source' => 1,
                'license' => 'MIT',
                'price_type' => 'free'
            ],
            'git' => [
                'summary' => '分布式版本控制系统官方 Windows 客户端，包含 Git Bash 与图形操作套件。',
                'developer' => 'Git Community',
                'publisher' => 'Software Freedom Conservancy',
                'category' => '开发编程',
                'special_notes' => '开发必备版本管理工具，支持 GitHub / GitLab 远程仓库协同。',
                'tags' => ['开源', '版本控制', '开发必备', '命令行'],
                'install_type' => '标准安装包 / 便携版',
                'supported_languages' => '英语 (含中文文档)',
                'os_requirements' => 'Windows 10 / 11 (x64)',
                'is_open_source' => 1,
                'license' => 'GPL-2.0',
                'price_type' => 'free'
            ],
            '7-zip' => [
                'summary' => '高压缩比的开源文件解压缩工具，支持 7z、ZIP、RAR、TAR 等主流格式。',
                'developer' => 'Igor Pavlov',
                'publisher' => 'Igor Pavlov',
                'category' => '系统工具',
                'special_notes' => '纯净无任何弹窗广告，占用极小内存，支持多线程高压缩率算法。',
                'tags' => ['开源', '解压缩', '装机必备', '小巧高效'],
                'install_type' => '标准安装包 / 便携解压即用',
                'supported_languages' => '简体中文, 英语 (多语言支持)',
                'os_requirements' => 'Windows 7 / 8 / 10 / 11 (x64)',
                'is_open_source' => 1,
                'license' => 'LGPL-2.1',
                'price_type' => 'free'
            ],
            'potplayer' => [
                'summary' => '功能强大的专业级本地音视频播放器，内置庞大解码器，支持倍速与高画质渲染。',
                'developer' => 'Kakao Corporation',
                'publisher' => 'Kakao',
                'category' => '影音媒体',
                'special_notes' => '支持硬件加速解码、自定义滤镜、外挂字幕与高刷画质增强。',
                'tags' => ['播放器', '影音媒体', '高码率解码', '无广告'],
                'install_type' => '标准安装包 / 便携绿色版',
                'supported_languages' => '简体中文, 英语, 韩语',
                'os_requirements' => 'Windows 10 / 11 (x64)',
                'is_open_source' => 0,
                'license' => null,
                'price_type' => 'free'
            ],
            'everything' => [
                'summary' => '基于 NTFS 快速建立索引的毫秒级本地文件搜索利器。',
                'developer' => 'voidtools',
                'publisher' => 'David Carpenter',
                'category' => '系统工具',
                'special_notes' => '建立全盘百万文件索引仅需数秒，内存极小，支持正则表达式。',
                'tags' => ['搜索神器', '效率工具', '装机必备', '毫秒响应'],
                'install_type' => '便携免安装版 / 标准安装包',
                'supported_languages' => '简体中文, 英语 (多语言支持)',
                'os_requirements' => 'Windows 7 / 8 / 10 / 11 (x64)',
                'is_open_source' => 0,
                'license' => null,
                'price_type' => 'free'
            ],
            'snipaste' => [
                'summary' => '极简但无比强大的截图与贴图神器，支持屏幕取色、马赛克及标注。',
                'developer' => 'Snipaste 团队',
                'publisher' => 'Levie',
                'category' => '效率办公',
                'special_notes' => '按下 F1 截图，F3 将截图直接贴在屏幕上，极大提升对照与办公效率。',
                'tags' => ['截图贴图', '效率办公', '免安装', '屏幕取色'],
                'install_type' => '单文件免安装 / 微软商店版',
                'supported_languages' => '简体中文, 英语 (多语言)',
                'os_requirements' => 'Windows 10 / 11 (x64)',
                'is_open_source' => 0,
                'license' => null,
                'price_type' => 'free'
            ]
        ];

        // 匹配已知软件
        $matched = null;
        foreach ($knownApps as $k => $v) {
            if (str_contains($lowerName, $k)) {
                $matched = $v;
                break;
            }
        }

        // 分类映射
        $catMap = [];
        foreach ($allCategories as $c) {
            $catMap[$c['name']] = $c['id'];
        }

        if ($matched) {
            if (in_array('developer', $missing)) {
                $result['developer'] = $matched['developer'];
                $filled[] = '开发者';
            }
            if (in_array('publisher', $missing)) {
                $result['publisher'] = $matched['publisher'];
                $filled[] = '发布者';
            }
            if (in_array('category_id', $missing) && !empty($catMap[$matched['category']])) {
                $result['category_id'] = $catMap[$matched['category']];
                $filled[] = '软件分类';
            }
            if (in_array('special_notes', $missing)) {
                // 软件简介字段已移除，知识库的一句话概述并入 Markdown 详细说明
                $result['special_notes'] = self::composeNotes($matched['summary'] ?? '', $matched['special_notes'] ?? '');
                $filled[] = '详细说明';
            }
            if (in_array('tags', $missing)) {
                $result['tags'] = implode(',', $matched['tags']);
                $filled[] = '标签体系';
            }
            if (in_array('install_type', $missing)) {
                $result['install_type'] = $matched['install_type'];
                $filled[] = '安装包类型';
            }
            if (in_array('supported_languages', $missing)) {
                $result['supported_languages'] = $matched['supported_languages'];
                $filled[] = '支持语言';
            }
            if (in_array('os_requirements', $missing)) {
                $result['os_requirements'] = $matched['os_requirements'];
                $filled[] = '系统要求';
            }
        } else {
            // 通用智能语义推断引擎
            $inferredCat = 1; // 默认效率办公
            $inferredTags = ['实用软件', 'Windows'];

            if (preg_match('/(code|git|dev|ide|editor|compiler|python|java|php|rust|node)/i', $name)) {
                $inferredCat = $catMap['开发编程'] ?? 4;
                $inferredTags = ['开发编程', '代码工具', '效率提升'];
            } elseif (preg_match('/(zip|rar|clean|uninstall|speed|optimi|bench|disk|file|search)/i', $name)) {
                $inferredCat = $catMap['系统工具'] ?? 2;
                $inferredTags = ['系统工具', '优化清理', '实用辅助'];
            } elseif (preg_match('/(player|media|video|audio|music|obs|stream|sound)/i', $name)) {
                $inferredCat = $catMap['影音媒体'] ?? 3;
                $inferredTags = ['影音媒体', '视频播放', '高清解码'];
            } elseif (preg_match('/(paint|photo|draw|cad|screen|shot|icon|image|design)/i', $name)) {
                $inferredCat = $catMap['图形设计'] ?? 5;
                $inferredTags = ['图形设计', '图片处理', '创意创作'];
            } elseif (preg_match('/(net|vpn|proxy|chat|browser|ftp|cloud|download)/i', $name)) {
                $inferredCat = $catMap['网络通信'] ?? 6;
                $inferredTags = ['网络通信', '高速传输', '纯净连接'];
            } elseif (preg_match('/(security|antivirus|guard|protect|shield|safe)/i', $name)) {
                $inferredCat = $catMap['安全防护'] ?? 7;
                $inferredTags = ['安全防护', '隐私保护', '系统防毒'];
            }

            if (!empty($software['github_url'])) {
                $inferredTags[] = '开源';
            }

            if (in_array('developer', $missing)) {
                $result['developer'] = "{$name} 官方开发团队";
                $filled[] = '开发者';
            }
            if (in_array('publisher', $missing)) {
                $result['publisher'] = "{$name} 官方发布机构";
                $filled[] = '发布者';
            }
            if (in_array('category_id', $missing)) {
                $result['category_id'] = $inferredCat;
                $filled[] = '软件分类';
            }
            if (in_array('special_notes', $missing)) {
                $result['special_notes'] = self::composeNotes(
                    "{$name} 是一款功能丰富且体验流畅的 Windows 软件，提供纯净安全的操作环境与官方可靠的下载分发通道。",
                    '建议从官方或授权直链下载最新版本，首次运行请确保系统兼容性与运行库完整。'
                );
                $filled[] = '详细说明';
            }
            if (in_array('tags', $missing)) {
                $result['tags'] = implode(',', $inferredTags);
                $filled[] = '标签体系';
            }
            if (in_array('install_type', $missing)) {
                $result['install_type'] = "标准安装包 / 免安装便携版";
                $filled[] = '安装包类型';
            }
            if (in_array('supported_languages', $missing)) {
                $result['supported_languages'] = "简体中文, 英语 (多语言支持)";
                $filled[] = '支持语言';
            }
            if (in_array('os_requirements', $missing)) {
                $result['os_requirements'] = "Windows 10 版本 17763.0 或更高版本 / Windows 11 (x64)";
                $filled[] = '系统要求';
            }
        }

        if (in_array('other_urls', $missing) && !empty($software['github_url'])) {
            $result['other_urls'] = json_encode([
                ['type' => 'Github', 'name' => 'GitHub Releases', 'url' => rtrim($software['github_url'], '/') . '/releases']
            ], JSON_UNESCAPED_UNICODE);
            $filled[] = '获取渠道';
        }

        if (!empty($filled)) {
            $result['is_ai_generated'] = 1;
            $result['ai_generated_fields'] = json_encode($filled, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    /**
     * 把旧版的一句话概述与要点合并为 Markdown 详细说明
     */
    private static function composeNotes(string $intro, string $points): string
    {
        $intro = trim($intro);
        $points = trim($points);
        if ($intro === '' && $points === '') {
            return '';
        }
        if ($intro === '') {
            return "## 功能特性\n\n- {$points}";
        }
        if ($points === '') {
            return $intro;
        }
        $bullets = preg_split('/[\r\n]+/', $points) ?: [$points];
        $bullets = array_values(array_filter(array_map('trim', $bullets), static function ($line) {
            return $line !== '';
        }));
        if (!$bullets) {
            return $intro;
        }
        if (count($bullets) > 1) {
            $body = implode("\n", array_map(static function ($line) {
                return '- ' . ltrim($line, "- \t");
            }, $bullets));
        } else {
            $body = '- ' . ltrim($bullets[0], "- \t");
        }
        return $intro . "\n\n## 功能特性\n\n" . $body;
    }
}
