# Skill: make-ai-agent

## Trigger
Gunakan skill ini ketika diminta membuat AI Agent baru di sistem multi-agent AetherDB AI.

## Instruksi untuk Agent

Buat file di `app/Modules/AIAgent/Agents/{NamaAgent}.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;

class {NamaAgent} implements AgentInterface
{
    public function __construct(
        private readonly AIRouter $router,
    ) {}

    public function analyze(SchemaContextDTO $context): AgentResultDTO
    {
        $prompt = $this->buildPrompt($context);

        $response = $this->router->route(
            task: '{task_type}',   // 'reasoning', 'sql_analysis', 'quick'
            prompt: $prompt,
            systemPrompt: $this->systemPrompt(),
        );

        return $this->parseResponse($response);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
        You are AetherDB AI, specialized in {domain}.
        Always respond in valid JSON format only.
        PROMPT;
    }

    private function buildPrompt(SchemaContextDTO $context): string
    {
        return <<<PROMPT
        Database: {$context->database}
        Schema:
        {$context->toJson()}

        Task: {task description}

        Respond ONLY with JSON in this format:
        {
          "findings": [...],
          "recommendations": [...],
          "score": 0-100
        }
        PROMPT;
    }

    private function parseResponse(string $response): AgentResultDTO
    {
        $data = json_decode($response, true);
        return new AgentResultDTO(
            findings: $data['findings'] ?? [],
            recommendations: $data['recommendations'] ?? [],
            score: $data['score'] ?? 0,
        );
    }
}
```

## Checklist Setelah Membuat Agent
1. Buat Prompt class di `app/Modules/AIAgent/Prompts/{NamaAgent}Prompt.php`
2. Daftarkan agent di `app/Modules/AIAgent/Orchestrator.php`
3. Buat API endpoint di `AgentController.php`
4. Tambahkan unit test di `tests/Unit/Agents/{NamaAgent}Test.php`
5. Cache hasil dengan Redis: `ai:{agent_name}:{connectionId}:{schemaHash}` TTL: 1800

## AI Router Task Types
- `reasoning` → DeepSeek R1 (complex analysis)
- `sql_analysis` → Claude (SQL + schema specialist)
- `quick` → DeepSeek V3 (fast response)
- `embedding` → text-embedding-3-large
