<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\Services\ContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function __construct(
        private readonly AIRouter $router,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    public function analyzeSchema(string $id, Request $request): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            $prompt = <<<PROMPT
Analyze this database schema. Group tables into domain clusters.
Rate relationship quality. Identify top 3 structural issues.

Schema:
{$context->toJson()}

Respond ONLY with JSON:
{
  "findings": [{"severity": "high|medium|low", "message": "..."}],
  "recommendations": [{"priority": "high|medium|low", "message": "..."}],
  "score": 0-100
}
PROMPT;

            $customPrompt = $request->input('system_prompt') ?? '';
            $systemPrompt = $this->buildSystemPrompt($customPrompt);
            $response = $this->router->route(
                'schema_analysis',
                $prompt,
                $systemPrompt,
                $request->input('api_key'),
                $request->input('provider', 'openai'),
            );

            $parsed = $this->parseAIResponse($response);
            $score = $parsed['score'] ?? 0;

            DB::table('ai_analyses')->insert([
                'connection_id' => $id,
                'connection_name' => $request->input('connection_name', 'Unknown'),
                'provider' => $request->input('provider', 'openai'),
                'result' => json_encode($parsed),
                'score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'findings' => $parsed['findings'] ?? [],
                    'recommendations' => $parsed['recommendations'] ?? [],
                    'score' => $score,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function listAnalyses(Request $request): JsonResponse
    {
        $analyses = DB::table('ai_analyses')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'connection_id' => $a->connection_id,
                'connection_name' => $a->connection_name,
                'provider' => $a->provider,
                'score' => $a->score,
                'timestamp' => $a->created_at,
                'result' => json_decode($a->result, true),
            ]);

        return response()->json(['data' => $analyses]);
    }

    public function deleteAnalysis(int $id): JsonResponse
    {
        DB::table('ai_analyses')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function chat(string $id, Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        if ($request->input('stream')) {
            return $this->chatStream($id, $request);
        }

        try {
            $context = $this->contextBuilder->build($id, 'database');
            $userMessage = $request->input('message');

            $customPrompt = $request->input('system_prompt') ?? '';
            $systemPrompt = $this->buildSystemPrompt($customPrompt);
            $schemaContext = $context->toJson();

            $prompt = <<<PROMPT
Connected database schema:
{$schemaContext}

User question: {$userMessage}

Answer about their database. Only discuss databases, MySQL, NoSQL, Big Data.
Use markdown for code blocks.
PROMPT;

            $response = $this->router->route(
                'chat',
                $prompt,
                $systemPrompt,
                $request->input('api_key'),
                $request->input('provider', 'openai'),
            );

            DB::table('ai_chat_history')->insert([
                'connection_id' => $id,
                'connection_name' => $request->input('connection_name', 'Unknown'),
                'provider' => $request->input('provider', 'openai'),
                'user_message' => $userMessage,
                'ai_response' => $response,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'role' => 'assistant',
                    'content' => $response,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function listChatHistory(string $id): JsonResponse
    {
        $items = DB::table('ai_chat_history')
            ->where('connection_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'connection_id' => $c->connection_id,
                'provider' => $c->provider,
                'user_message' => $c->user_message,
                'ai_response' => $c->ai_response,
                'timestamp' => $c->created_at,
            ]);

        return response()->json(['data' => $items]);
    }

    private function chatStream(string $id, Request $request): StreamedResponse
    {
        $context = $this->contextBuilder->build($id, 'database');
        $userMessage = $request->input('message');
        $customPrompt = $request->input('system_prompt') ?? '';
        $systemPrompt = $this->buildSystemPrompt($customPrompt);
        $schemaContext = $context->toJson();

        $prompt = <<<PROMPT
Connected database schema:
{$schemaContext}

User question: {$userMessage}

Answer about their database. Only discuss databases, MySQL, NoSQL, Big Data.
Use markdown for code blocks.
PROMPT;

        $apiKey = $request->input('api_key') ?? env('OPENAI_API_KEY') ?? '';
        $provider = $request->input('provider', 'openai');

        return response()->stream(function () use ($prompt, $systemPrompt, $apiKey, $provider) {
            $url = $provider === 'deepseek'
                ? 'https://api.deepseek.com/v1/chat/completions'
                : 'https://api.openai.com/v1/chat/completions';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$apiKey}",
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt ?: 'You are a helpful assistant.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                    'stream' => true,
                ]),
                CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                    echo $data;
                    ob_flush();
                    flush();
                    return strlen($data);
                },
            ]);

            curl_exec($ch);
            curl_close($ch);
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }

    private function parseAIResponse(string $response): array
    {
        $data = json_decode($response, true);

        if ($data) return $data;

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $m)) {
            $data = json_decode($m[1], true);
            if ($data) return $data;
        }

        if (preg_match('/\{[^{}]*\}/s', $response, $m)) {
            $data = json_decode($m[0], true);
            if ($data) return $data;
        }

        return [
            'findings' => [['severity' => 'low', 'message' => 'AI response could not be parsed. Raw: ' . mb_substr($response, 0, 200)]],
            'recommendations' => [],
            'score' => 0,
        ];
    }

    private function buildSystemPrompt(?string $customPrompt = ''): string
    {
        $default = <<<PROMPT
You are AetherDB AI, a database expert assistant. You ONLY discuss topics related to:
- Database management (MySQL, MariaDB, SQLite, PostgreSQL)
- NoSQL databases (MongoDB, Redis, Elasticsearch)
- Big data and data engineering
- Data modeling, schema design, SQL optimization
- Database performance, indexing, query tuning

RULES:
- If a question is outside these topics, respond: "I only assist with database-related questions."
- Be concise and actionable.
- Use markdown code blocks for SQL and commands.
PROMPT;

        if (trim($customPrompt)) {
            return $default . "\n\nAdditional instructions:\n" . $customPrompt;
        }

        return $default;
    }
}
