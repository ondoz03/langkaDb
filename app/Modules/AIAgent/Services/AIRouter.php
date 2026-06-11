<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

use Illuminate\Support\Facades\Log;

class AIRouter
{
    public function __construct(
        private readonly RuleBasedService $ruleBased,
    ) {}

    /**
     * Route an AI task to the appropriate provider.
     *
     * STRATEGY (cost-optimized, zero-waste):
     * 1. Deterministic tasks (schema_analysis, security, optimization)
     *    → RuleBasedService is DEFAULT. Zero API cost, instant.
     *    → External AI only if user explicitly passes provider != 'rule'.
     * 2. Creative tasks (chat, documentation)
     *    → External AI if API key exists.
     *    → RuleBased fallback if no key or API fails.
     */
    public function route(
        string $task,
        string $prompt,
        string $systemPrompt = '',
        ?string $apiKey = null,
        string $provider = 'rule',
    ): string {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt ?: 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->routeMessages($task, $messages, $apiKey, $provider);
    }

    public function routeMessages(
        string $task,
        array $messages,
        ?string $apiKey = null,
        string $provider = 'rule',
    ): string {
        $deterministicTasks = ['schema_analysis', 'security_analysis', 'optimization', 'domain_clustering'];
        $isDeterministic = in_array($task, $deterministicTasks, true);

        // Resolve API key from env if not provided
        if (! $apiKey) {
            $apiKey = match ($this->envKey($task)) {
                'openai' => env('OPENAI_API_KEY'),
                'anthropic' => env('ANTHROPIC_API_KEY'),
                'deepseek' => env('DEEPSEEK_API_KEY'),
                default => null,
            };
        }

        // ── DETERMINISTIC TASKS ────────────────────────────────────────────
        // Rule-based is the DEFAULT path. Only use external AI when
        // the caller explicitly opts in (provider != 'rule').
        if ($isDeterministic) {
            if ($provider === 'rule' || ! $apiKey) {
                return $this->ruleBased->process($task, json_encode($messages));
            }

            // Explicit external AI request for deterministic task
            return $this->callExternal($task, $messages, $apiKey, $provider);
        }

        // ── CREATIVE / CHAT TASKS ──────────────────────────────────────────
        // Prefer external AI for chat/documentation if key exists.
        if ($apiKey && $provider !== 'rule') {
            $result = $this->callExternal($task, $messages, $apiKey, $provider);
            if ($result) {
                return $result;
            }
        }

        // Fallback to rule-based (zero cost, always available)
        return $this->ruleBased->process($task, json_encode($messages));
    }

    /**
     * Call external AI provider with automatic fallback.
     */
    private function callExternal(string $task, array $messages, string $apiKey, string $provider): ?string
    {
        $model = match ($provider) {
            'deepseek' => 'deepseek-chat',
            'anthropic' => 'claude-3-haiku-20240307',
            default => 'gpt-4o-mini',
        };

        $url = match ($provider) {
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            default => 'https://api.openai.com/v1/chat/completions',
        };

        if ($provider === 'anthropic') {
            return $this->callAnthropicMessages($messages, $apiKey);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiKey}",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            Log::warning('AIRouter: external API failed, falling back to rule-based', [
                'task' => $task,
                'provider' => $provider,
                'http_code' => $httpCode,
            ]);

            return null;
        }

        $data = json_decode($response, true);

        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function envKey(string $task): ?string
    {
        if (env('OPENAI_API_KEY')) {
            return 'openai';
        }
        if (env('ANTHROPIC_API_KEY')) {
            return 'anthropic';
        }

        return null;
    }

    private function callOpenAI(string $prompt, string $systemPrompt = '', string $apiKey = ''): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiKey}",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt ?: 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return $this->ruleBased->process('general', $prompt);
        }

        $data = json_decode($response, true);

        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function callDeepSeek(string $prompt, string $systemPrompt = '', string $apiKey = ''): string
    {
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiKey}",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt ?: 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return json_encode([
                'findings' => [['severity' => 'high', 'message' => "DeepSeek API error (HTTP {$httpCode})"]],
                'recommendations' => [],
                'score' => 0,
            ]);
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return json_encode([
                'findings' => [['severity' => 'high', 'message' => 'DeepSeek: '.($data['error']['message'] ?? 'Unknown error')]],
                'recommendations' => [],
                'score' => 0,
            ]);
        }

        return $data['choices'][0]['message']['content'] ?? json_encode([
            'findings' => [['severity' => 'low', 'message' => 'Empty response from DeepSeek']],
            'recommendations' => [],
            'score' => 0,
        ]);
    }

    private function callAnthropicMessages(array $messages, string $apiKey): string
    {
        $systemContent = '';
        $chatMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemContent .= $msg['content']."\n";
            } else {
                $chatMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: '.$apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'system' => $systemContent ?: 'You are a helpful assistant.',
                'messages' => $chatMessages,
                'max_tokens' => 2000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return 'Maaf, terjadi kesalahan.';
        }

        $data = json_decode($response, true);

        return $data['content'][0]['text'] ?? '';
    }

    private function callAnthropic(string $prompt, string $systemPrompt = '', ?string $overrideKey = null): string
    {
        $apiKey = $overrideKey ?? env('ANTHROPIC_API_KEY');

        if (! $apiKey) {
            return $this->ruleBased->process('general', $prompt);
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: '.$apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'system' => $systemPrompt ?: 'You are a helpful assistant.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 2000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return $this->ruleBased->process('general', $prompt);
        }

        $data = json_decode($response, true);

        return $data['content'][0]['text'] ?? '';
    }
}
