<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function __construct(
        private readonly AIRouter $router,
        private readonly ContextBuilder $contextBuilder,
        private readonly SchemaFormatter $formatter,
    ) {}

    public function analyzeSchema(string $id, Request $request): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            $schemaCompact = $this->formatter->compact($context);

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
            $inputTokens = (int) (mb_strlen($prompt) / 4);
            $outputTokens = (int) (mb_strlen($response) / 4);

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
                    'tokens' => [
                        'input' => $inputTokens,
                        'output' => $outputTokens,
                        'total' => $inputTokens + $outputTokens,
                    ],
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

        try {
            $userMessage = $request->input('message');
            $history = $request->input('history', []);
            $customPrompt = $request->input('system_prompt') ?? '';
            $systemPrompt = $this->buildSystemPrompt($customPrompt);
            $apiKey = $request->input('api_key') ?? env('OPENAI_API_KEY') ?? '';
            $provider = $request->input('provider', 'openai');

            $connection = \App\Modules\Connection\Models\Connection::find($id);
            $dbInfo = '';

            if ($connection) {
                $dbInfo = "Terhubung ke: {$connection->name} ({$connection->driver})";

                try {
                    $context = $this->contextBuilder->build($id, 'database');
                    $schemaLines = [];

                    $allTableNames = array_map(fn ($t) => $t->name, $context->tables);

                    // Show ALL table names (cheap - just names)
                    $dbInfo .= "\nSemua tabel: " . implode(', ', $allTableNames);

                    // Column details for first 15 tables only
                    $detailTables = array_slice($context->tables, 0, 15);
                    $dbInfo .= "\n\nDetail kolom:";

                    foreach ($detailTables as $table) {
                        $colNames = [];
                        foreach ($table->columns as $col) {
                            $colNames[] = $col->name;
                        }
                        $dbInfo .= "\n- {$table->name}(" . implode(', ', $colNames) . ')';
                    }

                    if (count($context->tables) > 15) {
                        $dbInfo .= "\n(Gunakan [QUERY]SHOW COLUMNS FROM nama_tabel[/QUERY] untuk lihat kolom tabel lain)";
                    }
                } catch (\Throwable $e) {
                    $dbInfo .= "\n(Gunakan [QUERY]SHOW TABLES[/QUERY] untuk lihat tabel)";
                }
            }

            // Build messages array with history
            $aiMessages = [['role' => 'system', 'content' => $systemPrompt]];

            if ($dbInfo) {
                $aiMessages[] = ['role' => 'system', 'content' => $dbInfo];
            }

            // Add conversation history (last 10 messages, keep it relevant)
            $recentHistory = array_slice($history, -10);
            foreach ($recentHistory as $msg) {
                $content = $msg['content'] ?? '';
                if (mb_strlen($content) > 500) {
                    $content = mb_substr($content, 0, 500) . '...';
                }
                $aiMessages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $content,
                ];
            }

            // Add current user message
            $aiMessages[] = ['role' => 'user', 'content' => $userMessage];

            $response = $this->router->routeMessages('chat', $aiMessages, $apiKey, $provider);
            $finalResponse = $this->processToolCalls($response, $id, $systemPrompt, $apiKey, $provider);

            $inputTokens = (int) (mb_strlen(json_encode($aiMessages)) / 4);
            $outputTokens = (int) (mb_strlen($finalResponse) / 4);

            DB::table('ai_chat_history')->insert([
                'connection_id' => $id,
                'connection_name' => $request->input('connection_name', 'Unknown'),
                'provider' => $provider,
                'user_message' => $userMessage,
                'ai_response' => $finalResponse,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'role' => 'assistant',
                    'content' => $finalResponse,
                    'timestamp' => now()->toIso8601String(),
                    'tokens' => [
                        'input' => $inputTokens,
                        'output' => $outputTokens,
                        'total' => $inputTokens + $outputTokens,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function processToolCalls(string $response, string $connectionId, string $systemPrompt, string $apiKey, string $provider): string
    {
        if (!preg_match('/\[QUERY\]([\s\S]*?)\[\/QUERY\]/', $response, $matches)) {
            return $response;
        }

        $sql = trim($matches[1]);
        $sqlUpper = strtoupper($sql);
        $textBefore = explode('[QUERY]', $response)[0];
        $sqlBlock = "```sql\n{$sql}\n```";

        // Only auto-execute read-only queries
        $readOnlyPrefixes = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'WITH', 'DESC'];
        $isReadOnly = false;

        foreach ($readOnlyPrefixes as $prefix) {
            if (str_starts_with($sqlUpper, $prefix)) {
                $isReadOnly = true;
                break;
            }
        }

        if (!$isReadOnly) {
            return $textBefore . "\n\n{$sqlBlock}\n\n⚠️ *Query ini tidak dijalankan otomatis. Jalankan manual jika yakin.*";
        }

        $connection = \App\Modules\Connection\Models\Connection::find($connectionId);

        if (!$connection) {
            return $textBefore . "\n\n{$sqlBlock}\n\n*(Koneksi tidak ditemukan)*";
        }

        $driverMap = ['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql'];
        $encryptor = app(\App\Modules\Connection\Services\ConnectionEncryptor::class);

        $config = [
            'driver' => $driverMap[$connection->driver] ?? 'pdo_mysql',
            'host' => $connection->host,
            'port' => (int) $connection->port,
            'dbname' => $connection->database,
            'user' => $connection->username,
            'password' => $encryptor->decrypt($connection->password),
            'charset' => 'utf8mb4',
        ];

        try {
            $conn = \Doctrine\DBAL\DriverManager::getConnection($config);
            $stmt = $conn->executeQuery($sql);
            $rows = $stmt->fetchAllAssociative();
            $columns = !empty($rows) ? array_keys($rows[0]) : [];
            $count = count($rows);
            $displayRows = array_slice($rows, 0, 15);

            $header = '| ' . implode(' | ', $columns) . ' |';
            $separator = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';
            $dataRows = array_map(fn ($row) => '| ' . implode(' | ', array_map(fn ($col) => $row[$col] ?? 'NULL', $columns)) . ' |', $displayRows);
            $table = "[Hasil: {$count} baris]\n\n{$header}\n{$separator}\n" . implode("\n", $dataRows);

            $resultBlock = "```\n{$table}\n```";
        } catch (\Throwable $e) {
            $resultBlock = "```\nError: {$e->getMessage()}\n```";
        }

        return $textBefore . "\n\n{$sqlBlock}\n\n{$resultBlock}";
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

    public function deleteChatHistory(int $id): JsonResponse
    {
        DB::table('ai_chat_history')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function clearChatHistory(string $id): JsonResponse
    {
        DB::table('ai_chat_history')->where('connection_id', $id)->delete();

        return response()->json(['message' => 'Cleared']);
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
Kamu adalah database expert. Jawab dalam Bahasa Indonesia.

Kamu adalah database expert. Jawab dalam Bahasa Indonesia.

PERHATIKAN KONTEKS PERCAKAPAN SEBELUMNYA.

Untuk menampilkan data/analisis:
- Gunakan [QUERY]SQL[/QUERY] — otomatis dijalankan, hasil langsung tampil.
- Contoh: [QUERY]SELECT * FROM users LIMIT 5[/QUERY]
- HANYA untuk SELECT, SHOW, DESCRIBE, EXPLAIN, WITH.
- Query lain (INSERT, UPDATE, DELETE, ALTER, DROP) TIDAK boleh disarankan sama sekali.
PROMPT;

        if (trim($customPrompt)) {
            return $default . "\n\nAdditional instructions:\n" . $customPrompt;
        }

        return $default;
    }
}
