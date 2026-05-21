<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

class AIRouter
{
    public function __construct(
        private readonly RuleBasedService $ruleBased,
    ) {}

    public function route(
        string $task,
        string $prompt,
        string $systemPrompt = '',
        ?string $apiKey = null,
        string $provider = 'rule',
    ): string {
        $apiKey ??= match ($this->envKey($task)) {
            'openai' => env('OPENAI_API_KEY'),
            'anthropic' => env('ANTHROPIC_API_KEY'),
            default => null,
        };

        if (!$apiKey) {
            return $this->ruleBased->process($task, $prompt);
        }

        return match ($provider) {
            'openai' => $this->callOpenAI($prompt, $systemPrompt, $apiKey),
            'deepseek' => $this->callDeepSeek($prompt, $systemPrompt, $apiKey),
            'anthropic' => $this->callAnthropic($prompt, $systemPrompt, $apiKey),
            default => $this->ruleBased->process($task, $prompt),
        };
    }

    private function envKey(string $task): ?string
    {
        if (env('OPENAI_API_KEY')) return 'openai';
        if (env('ANTHROPIC_API_KEY')) return 'anthropic';

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

        if ($httpCode !== 200 || !$response) {
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

        if ($httpCode !== 200 || !$response) {
            return json_encode([
                'findings' => [['severity' => 'high', 'message' => "DeepSeek API error (HTTP {$httpCode})"]],
                'recommendations' => [],
                'score' => 0,
            ]);
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return json_encode([
                'findings' => [['severity' => 'high', 'message' => 'DeepSeek: ' . ($data['error']['message'] ?? 'Unknown error')]],
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

    private function callAnthropic(string $prompt, string $systemPrompt = '', ?string $overrideKey = null): string
    {
        $apiKey = $overrideKey ?? env('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return $this->ruleBased->process('general', $prompt);
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
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

        if ($httpCode !== 200 || !$response) {
            return $this->ruleBased->process('general', $prompt);
        }

        $data = json_decode($response, true);

        return $data['content'][0]['text'] ?? '';
    }
}
