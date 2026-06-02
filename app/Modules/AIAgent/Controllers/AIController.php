<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AIAgent\Services\AICacheService;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Connection\Models\Connection;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function __construct(
        private readonly AIRouter $router,
        private readonly AICacheService $cache,
        private readonly ContextBuilder $contextBuilder,
        private readonly SchemaFormatter $formatter,
    ) {}

    public function analyzeSchema(string $id, Request $request): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');
            $schemaCompact = $this->formatter->compact($context);
            $schemaHash = $this->cache->schemaHash($context->toArray());

            // Cache key: ai:schema:{connectionId}:{hash}
            $cacheKey = $this->cache->key('schema', $id, $schemaHash);
            $ttl = $this->cache->ttl('schema');

            $cached = $this->cache->get($cacheKey);

            if ($cached) {
                $result = is_string($cached) ? json_decode($cached, true) : $cached;

                return response()->json([
                    'data' => $result,
                    'cached' => true,
                ]);
            }

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
            // Default to 'rule' (deterministic, zero-cost) unless user explicitly overrides
            $provider = $request->input('provider', 'rule');
            $response = $this->router->route(
                'schema_analysis',
                $prompt,
                $systemPrompt,
                $request->input('api_key'),
                $provider,
            );

            $parsed = $this->parseAIResponse($response);
            $score = $parsed['score'] ?? 0;
            $inputTokens = (int) (mb_strlen($prompt) / 4);
            $outputTokens = (int) (mb_strlen($response) / 4);

            // Persist to DB (fire-and-forget, non-blocking)
            DB::table('ai_analyses')->insert([
                'connection_id' => $id,
                'connection_name' => $request->input('connection_name', 'Unknown'),
                'provider' => $provider,
                'result' => json_encode($parsed),
                'score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result = [
                'findings' => $parsed['findings'] ?? [],
                'recommendations' => $parsed['recommendations'] ?? [],
                'score' => $score,
                'tokens' => [
                    'input' => $inputTokens,
                    'output' => $outputTokens,
                    'total' => $inputTokens + $outputTokens,
                ],
            ];

            // Store in cache
            $this->cache->set($cacheKey, json_encode($result), $ttl);

            return response()->json([
                'data' => $result,
                'cached' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get cached health score for a connection.
     */
    public function healthScore(string $id): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');
            $schemaHash = $this->cache->schemaHash($context->toArray());

            $cacheKey = $this->cache->key('health', $id, $schemaHash);
            $ttl = $this->cache->ttl('health');

            $cached = $this->cache->get($cacheKey);

            if ($cached) {
                $result = is_string($cached) ? json_decode($cached, true) : $cached;

                return response()->json([
                    'data' => $result,
                    'cached' => true,
                ]);
            }

            // Compute health score using MonitoringAgent logic deterministically
            $tables = $context->tables;
            $totalTables = count($tables);
            $tablesWithIndexes = count(array_filter($tables, fn ($t) => count($t->indexes) > 0));
            $tablesWithPk = 0;
            $highRowNoIndex = 0;
            $totalSizeMb = 0;

            foreach ($tables as $table) {
                $totalSizeMb += $table->sizeMb;
                $hasPk = false;
                foreach ($table->columns as $col) {
                    if ($col->primary) {
                        $hasPk = true;
                        break;
                    }
                }
                if ($hasPk) {
                    $tablesWithPk++;
                }
                if ($table->rowCount > 10000 && count($table->indexes) === 0) {
                    $highRowNoIndex++;
                }
            }

            $indexCoverage = $totalTables > 0 ? round(($tablesWithIndexes / $totalTables) * 100, 1) : 100;
            $structureDim = $totalTables > 0 ? (($tablesWithPk / $totalTables) * 100) : 100;
            $indexDim = 100 - ($totalTables > 0 ? ((($totalTables - $tablesWithIndexes) / $totalTables) * 100) : 0);
            $securityDim = $totalTables > 0 ? (($tablesWithPk / $totalTables) * 100) : 100;

            $healthScore = (int) min(100,
                ($indexCoverage * 0.35) +
                ($structureDim * 0.30) +
                ($indexDim * 0.20) +
                ($securityDim * 0.15)
            );

            $grade = match (true) {
                $healthScore >= 90 => 'A',
                $healthScore >= 80 => 'B',
                $healthScore >= 70 => 'C',
                $healthScore >= 60 => 'D',
                default => 'F',
            };

            $result = [
                'health_score' => $healthScore,
                'health_grade' => $grade,
                'dimensions' => [
                    'performance' => $indexCoverage,
                    'structure' => round($structureDim, 1),
                    'index' => round($indexDim, 1),
                    'security' => round($securityDim, 1),
                ],
                'metrics' => [
                    'total_tables' => $totalTables,
                    'total_size_mb' => round($totalSizeMb, 2),
                    'tables_with_indexes' => $tablesWithIndexes,
                    'tables_without_pk' => $totalTables - $tablesWithPk,
                    'high_rows_no_index' => $highRowNoIndex,
                ],
                'source' => 'rule-based',
            ];

            $this->cache->set($cacheKey, json_encode($result), $ttl);

            return response()->json([
                'data' => $result,
                'cached' => false,
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
            'dbname' => $connection->database,
            'user' => $connection->username,
            'password' => $encryptor->decrypt($connection->password),
            'charset' => 'utf8mb4',
        ];

        // Use unix_socket for local connections
        if (empty($connection->host) || $connection->host === 'localhost' || $connection->host === '127.0.0.1') {
            $socketPath = '/var/run/mysqld/mysqld.sock';
            if (file_exists($socketPath)) {
                $config['unix_socket'] = $socketPath;
            } else {
                $config['host'] = $connection->host ?: '127.0.0.1';
                $config['port'] = (int) ($connection->port ?: 3306);
            }
        } else {
            $config['host'] = $connection->host;
            $config['port'] = (int) ($connection->port ?: 3306);
        }

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

    public function chatStream(string $id, Request $request): StreamedResponse
    {
        $userMessage = $request->input('message', '');
        $history = $request->input('history', []);
        $customPrompt = $request->input('system_prompt') ?? '';
        $systemPrompt = $this->buildSystemPrompt($customPrompt);
        $apiKey = $request->input('api_key') ?? env('OPENAI_API_KEY') ?? '';
        $provider = $request->input('provider', 'openai');
        $connectionName = $request->input('connection_name', 'Unknown');

        // Build AI messages array with schema context
        $aiMessages = [['role' => 'system', 'content' => $systemPrompt]];

        $connection = \App\Modules\Connection\Models\Connection::find($id);
        $dbInfo = '';

        if ($connection) {
            $dbInfo = "Terhubung ke: {$connection->name} ({$connection->driver})";
            try {
                $context = $this->contextBuilder->build($id, 'database');
                $allTableNames = array_map(fn($t) => $t->name, $context->tables);
                $dbInfo .= "\nSemua tabel: " . implode(', ', $allTableNames);

                $detailTables = array_slice($context->tables, 0, 15);
                $dbInfo .= "\n\nDetail kolom:";
                foreach ($detailTables as $table) {
                    $colNames = array_map(fn($c) => $c->name, $table->columns);
                    $dbInfo .= "\n- {$table->name}(" . implode(', ', $colNames) . ')';
                }
            } catch (\Throwable $e) {
                $dbInfo .= "\n(Gunakan [QUERY]SHOW TABLES[/QUERY] untuk lihat tabel)";
            }
        }

        if ($dbInfo) {
            $aiMessages[] = ['role' => 'system', 'content' => $dbInfo];
        }

        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
            $content = $msg['content'] ?? '';
            if (mb_strlen($content) > 500) {
                $content = mb_substr($content, 0, 500) . '...';
            }
            $aiMessages[] = ['role' => $msg['role'] ?? 'user', 'content' => $content];
        }

        $aiMessages[] = ['role' => 'user', 'content' => $userMessage];

        $fullResponse = '';

        return response()->stream(function () use ($aiMessages, $apiKey, $provider, &$fullResponse, $userMessage, $id, $connectionName) {
            $url = $provider === 'deepseek'
                ? 'https://api.deepseek.com/v1/chat/completions'
                : 'https://api.openai.com/v1/chat/completions';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini',
                    'messages' => $aiMessages,
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                    'stream' => true,
                ]),
                CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullResponse) {
                    $lines = explode("\n", $data);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || $line === 'data: [DONE]') continue;
                        if (str_starts_with($line, 'data: ')) {
                            $json = substr($line, 6);
                            $parsed = json_decode($json, true);
                            $delta = $parsed['choices'][0]['delta']['content'] ?? '';
                            if ($delta) {
                                $fullResponse .= $delta;
                                echo "data: " . json_encode(['type' => 'chunk', 'content' => $delta]) . "\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }
                    return strlen($data);
                },
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                echo "data: " . json_encode(['type' => 'error', 'message' => "API error (HTTP {$httpCode})"]) . "\n\n";
                ob_flush();
                flush();
            } elseif ($fullResponse) {
                // Save to chat history
                try {
                    \Illuminate\Support\Facades\DB::table('ai_chat_history')->insert([
                        'connection_id' => $id,
                        'connection_name' => $connectionName,
                        'provider' => $provider,
                        'user_message' => $userMessage,
                        'ai_response' => $fullResponse,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Non-blocking — don't fail the stream for DB error
                }

                echo "data: " . json_encode(['type' => 'done', 'content' => $fullResponse]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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

    /**
     * Get overall monitoring dashboard stats
     */
    public function monitoringStats(): JsonResponse
    {
        try {
            $connections = Connection::all();

            $totalConnections = $connections->count();
            $activeConnections = $connections->where('status', 'connected')->count();
            $warningConnections = $connections->where('status', 'warning')->count();
            $disconnectedConnections = $connections->where('status', 'disconnected')->count();

            $slowQueries = 0;
            $totalQueries = 0;
            try {
                $slowQueries = DB::table('ai_job_results')
                    ->where('type', 'health_analysis')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count();
                $totalQueries = DB::table('ai_chat_history')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count();
            } catch (\Throwable $e) {
                Log::warning('Monitoring: stats query error: ' . $e->getMessage());
            }

            $healthScores = [];
            foreach ($connections as $conn) {
                try {
                    $cached = $this->cache->get("ai:health:{$conn->id}");
                    if ($cached && isset($cached['health_score'])) {
                        $healthScores[] = (float) $cached['health_score'];
                    } elseif ($conn->status === 'connected') {
                        $healthScores[] = 85.0;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            $avgHealthScore = !empty($healthScores)
                ? round(array_sum($healthScores) / count($healthScores), 1)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_connections' => $totalConnections,
                    'active_connections' => $activeConnections,
                    'warning_connections' => $warningConnections,
                    'disconnected_connections' => $disconnectedConnections,
                    'slow_queries_24h' => $slowQueries,
                    'total_queries_24h' => $totalQueries,
                    'avg_health_score' => $avgHealthScore,
                    'avg_health_grade' => $this->gradeScore($avgHealthScore),
                    'connections' => $connections->map(fn($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'driver' => $c->driver,
                        'host' => $c->host ?: 'localhost',
                        'port' => $c->port ?? 3306,
                        'database' => $c->database,
                        'status' => $c->status,
                        'created_at' => $c->created_at?->toIso8601String(),
                        'updated_at' => $c->updated_at?->toIso8601String(),
                    ])->values()->toArray(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Monitoring stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monitoring stats',
            ], 500);
        }
    }

    /**
     * Get recent alerts
     */
    public function monitoringAlerts(): JsonResponse
    {
        try {
            $connections = Connection::all();
            $alerts = [];

            foreach ($connections as $conn) {
                if ($conn->status === 'disconnected') {
                    $alerts[] = [
                        'type' => 'error',
                        'severity' => 'high',
                        'message' => "Connection '{$conn->name}' is disconnected",
                        'connection' => $conn->name,
                        'connection_id' => $conn->id,
                        'time' => $conn->updated_at?->diffForHumans() ?? 'N/A',
                    ];
                } elseif ($conn->status === 'warning') {
                    $alerts[] = [
                        'type' => 'warning',
                        'severity' => 'medium',
                        'message' => "Connection '{$conn->name}' has warnings",
                        'connection' => $conn->name,
                        'connection_id' => $conn->id,
                        'time' => $conn->updated_at?->diffForHumans() ?? 'N/A',
                    ];
                }
            }

            usort($alerts, fn($a, $b) => ($a['severity'] === 'high' ? 0 : 1) <=> ($b['severity'] === 'high' ? 0 : 1));

            return response()->json([
                'success' => true,
                'data' => array_slice($alerts, 0, 10),
            ]);
        } catch (\Throwable $e) {
            Log::error('Monitoring alerts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch alerts',
            ], 500);
        }
    }

    private function gradeScore(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }
}
   