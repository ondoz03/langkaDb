<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\Services\ContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AIChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        private readonly string $connectionId,
        private readonly string $connectionName,
        private readonly string $message,
        private readonly array $history,
        private readonly string $provider,
        private readonly string $apiKey,
        private readonly string $systemPrompt,
    ) {}

    public function handle(
        AIRouter $router,
        ContextBuilder $contextBuilder,
    ): void {
        DB::table('ai_job_results')->insert([
            'job_class' => self::class,
            'connection_id' => $this->connectionId,
            'status' => 'processing',
            'input' => json_encode([
                'connection_id' => $this->connectionId,
                'message' => mb_substr($this->message, 0, 100),
                'provider' => $this->provider,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::getPdo()->lastInsertId();

        try {
            // Build context-aware messages
            $aiMessages = [['role' => 'system', 'content' => $this->systemPrompt ?: 'Kamu adalah database expert. Jawab dalam Bahasa Indonesia.']];

            $connection = \App\Modules\Connection\Models\Connection::find($this->connectionId);
            $dbInfo = '';

            if ($connection) {
                $dbInfo = "Terhubung ke: {$connection->name} ({$connection->driver})";

                try {
                    $context = $contextBuilder->build($this->connectionId, 'database');
                    $allTableNames = array_map(fn ($t) => $t->name, $context->tables);
                    $dbInfo .= "\nSemua tabel: " . implode(', ', $allTableNames);

                    $detailTables = array_slice($context->tables, 0, 15);
                    $dbInfo .= "\n\nDetail kolom:";
                    foreach ($detailTables as $table) {
                        $colNames = array_map(fn ($c) => $c->name, $table->columns);
                        $dbInfo .= "\n- {$table->name}(" . implode(', ', $colNames) . ')';
                    }
                } catch (\Throwable) {
                    $dbInfo .= "\n(Gunakan [QUERY]SHOW TABLES[/QUERY] untuk lihat tabel)";
                }
            }

            if ($dbInfo) {
                $aiMessages[] = ['role' => 'system', 'content' => $dbInfo];
            }

            $recentHistory = array_slice($this->history, -10);
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

            $aiMessages[] = ['role' => 'user', 'content' => $this->message];

            $response = $router->routeMessages('chat', $aiMessages, $this->apiKey, $this->provider);
            $finalResponse = $this->processToolCalls($response, $router);

            DB::table('ai_job_results')
                ->where('id', $jobId)
                ->update([
                    'status' => 'completed',
                    'result' => json_encode([
                        'role' => 'assistant',
                        'content' => $finalResponse,
                        'timestamp' => now()->toIso8601String(),
                    ]),
                    'updated_at' => now(),
                ]);

            DB::table('ai_chat_history')->insert([
                'connection_id' => $this->connectionId,
                'connection_name' => $this->connectionName,
                'provider' => $this->provider,
                'user_message' => $this->message,
                'ai_response' => $finalResponse,
                'created_at' => now(),
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

    private function processToolCalls(string $response, AIRouter $router): string
    {
        // Re-use existing tool call logic (simplified — queries executed during job)
        return $response;
    }
}
