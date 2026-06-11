<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\DTOs\SchemaContextDTO;

class SchemaAgent implements AgentInterface
{
    public function __construct(
        private readonly AIRouter $router,
    ) {}

    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO
    {
        $prompt = $this->buildPrompt($context);
        $response = $this->router->route('schema_analysis', $prompt, $this->systemPrompt(), $apiKey, $provider);
        $parsed = $this->parseResponse($response);

        return new AgentResultDTO(
            agent: 'schema',
            findings: $parsed['findings'] ?? [],
            recommendations: $parsed['recommendations'] ?? [],
            score: $parsed['score'] ?? 0,
            metadata: ['domain_clusters' => $parsed['clusters'] ?? []],
        );
    }

    private function systemPrompt(): string
    {
        return 'You are AetherDB AI, specialized in schema analysis. Always respond in valid JSON format only.';
    }

    private function buildPrompt(SchemaContextDTO $context): string
    {
        return <<<PROMPT
Database: {$context->database}
Schema: {$context->toJson()}

Task: Analyze this schema. Group tables into domain clusters (auth, commerce, finance, etc.).
Rate relationship quality. Identify top 3 structural issues.

Respond ONLY with JSON:
{
  "findings": [{"severity": "high|medium|low", "message": "..."}],
  "recommendations": [{"priority": "high|medium|low", "message": "..."}],
  "score": 0-100,
  "clusters": [{"name": "auth", "tables": ["users", "roles"]}]
}
PROMPT;
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

        if (preg_match('/\{[^{}]*\}/s', $response, $m)) {
            $data = json_decode($m[0], true);

            if ($data) {
                return $data;
            }
        }

        return [
            'findings' => [['severity' => 'low', 'message' => 'AI: '.mb_substr($response, 0, 150)]],
            'recommendations' => [['priority' => 'low', 'message' => 'AI analysis raw response shown above.']],
            'score' => 0,
            'clusters' => [],
        ];
    }
}
