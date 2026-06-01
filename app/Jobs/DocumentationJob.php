<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DocumentationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        private readonly string $connectionId,
        private readonly string $provider,
        private readonly string $apiKey,
        private readonly string $systemPrompt,
        private readonly string $format = 'markdown',
    ) {}

    public function handle(
        ContextBuilder $contextBuilder,
        SchemaFormatter $formatter,
    ): void {
        DB::table('ai_job_results')->insert([
            'job_class' => self::class,
            'connection_id' => $this->connectionId,
            'status' => 'processing',
            'input' => json_encode([
                'connection_id' => $this->connectionId,
                'format' => $this->format,
                'provider' => $this->provider,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::getPdo()->lastInsertId();

        try {
            $context = $contextBuilder->build($this->connectionId, 'database');
            $schemaJson = $context->toJson();

            $prompt = <<<PROMPT
You are a database documentation generator.

Generate comprehensive documentation for the following database schema in {$this->format} format.

Schema:
{$schemaJson}

Include:
1. Database overview and purpose
2. Table listing with descriptions
3. Column details for each table (type, nullable, default, description)
4. Relationship diagram (text-based)
5. Key indexes and constraints
6. Usage examples (sample queries)

Respond with the documentation only.
PROMPT;

            $config = [
                'model' => match ($this->provider) {
                    'deepseek' => 'deepseek-chat',
                    'anthropic' => 'claude-3-haiku-20240307',
                    default => 'gpt-4o-mini',
                },
                'temperature' => 0.3,
                'max_tokens' => 4000,
            ];

            $response = $this->callAI($prompt, $config);

            DB::table('ai_job_results')
                ->where('id', $jobId)
                ->update([
                    'status' => 'completed',
                    'result' => json_encode([
                        'format' => $this->format,
                        'content' => $response,
                        'tokens' => [
                            'input' => (int) (mb_strlen($prompt) / 4),
                            'output' => (int) (mb_strlen($response) / 4),
                        ],
                    ]),
                    'progress' => 100,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            DB::table('ai_job_results')
                ->where('id', $jobId)
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
                    ['role' => 'system', 'content' => $this->systemPrompt ?: 'You are a database documentation generator.'],
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
}
