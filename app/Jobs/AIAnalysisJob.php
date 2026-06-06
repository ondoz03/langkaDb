<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\AIAgent\Services\Orchestrator;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AIAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        private readonly string $connectionId,
        private readonly string $connectionName,
        private readonly string $provider,
        private readonly string $apiKey,
        private readonly string $systemPrompt,
        private readonly int $jobResultId,
    ) {}

    public function handle(
        Orchestrator $orchestrator,
        ContextBuilder $contextBuilder,
        SchemaFormatter $formatter,
    ): void {
        // Mark as processing
        DB::table('ai_job_results')
            ->where('id', $this->jobResultId)
            ->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);

        try {
            $context = $contextBuilder->build($this->connectionId, 'database');
            $schemaCompact = $formatter->compact($context);

            $prompt = <<<PROMPT
Analyze this database schema.

{$schemaCompact}

Identify problematic tables and their specific issues.
For each finding, mention the table name and what's wrong.
For each recommendation, mention which table to apply it to.

Respond ONLY with JSON:
{
  "findings": [{"severity": "high|medium|low", "table": "table_name", "message": "specific issue with this table"}],
  "recommendations": [{"priority": "high|medium|low", "table": "table_name", "message": "what to do and why"}],
  "score": 0-100
}
PROMPT;

            if ($this->provider === 'rule') {
                // Use the multi-agent Orchestrator (zero-cost, rule-based)
                $result = $orchestrator->analyzeFull($context);
                $parsed = [
                    'findings' => $result['findings'] ?? [],
                    'recommendations' => $result['recommendations'] ?? [],
                    'score' => $result['composite_score'] ?? $result['score'] ?? 0,
                ];
            } else {
                $config = [
                    'model' => match ($this->provider) {
                        'deepseek' => 'deepseek-chat',
                        'anthropic' => 'claude-3-haiku-20240307',
                        'ollama' => 'ollama-local',
                        default => 'gpt-4o-mini',
                    },
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                ];

                $response = $this->callAI($prompt, $config);
                $parsed = $this->parseResponse($response);
            }

            $score = $parsed['score'] ?? 0;

            DB::table('ai_job_results')
                ->where('id', $this->jobResultId)
                ->update([
                    'status' => 'completed',
                    'result' => json_encode([
                        'findings' => $parsed['findings'] ?? [],
                        'recommendations' => $parsed['recommendations'] ?? [],
                        'score' => $score,
                    ]),
                    'updated_at' => now(),
                ]);

            DB::table('ai_analyses')->insert([
                'connection_id' => $this->connectionId,
                'connection_name' => $this->connectionName,
                'provider' => $this->provider,
                'result' => json_encode([
                    'findings' => $parsed['findings'] ?? [],
                    'recommendations' => $parsed['recommendations'] ?? [],
                    'score' => $score,
                ]),
                'score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('ai_job_results')
                ->where('id', $this->jobResultId)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function callAI(string $prompt, array $config): string
    {
        $url = match ($this->provider) {
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
            default => 'https://api.openai.com/v1/chat/completions',
        };

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt ?: 'You are a database expert.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $config['temperature'],
                'max_tokens' => $config['max_tokens'],
            ]),
            CURLOPT_TIMEOUT => 240,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("AI API error (HTTP {$httpCode})");
        }

        $data = json_decode($response, true);

        return $data['choices'][0]['message']['content'] ?? throw new \RuntimeException('Empty AI response');
    }

    private function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if ($data) {
            return $data;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $m)) {
            $data = json_decode($m[1], true);
            if ($data) {
                return $data;
            }
        }

        return [
            'findings' => [['severity' => 'low', 'table' => 'N/A', 'message' => 'AI: ' . mb_substr($response, 0, 150)]],
            'recommendations' => [],
            'score' => 0,
        ];
    }
}
