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

class HealthScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        private readonly string $connectionId,
    ) {}

    public function handle(
        ContextBuilder $contextBuilder,
        SchemaFormatter $formatter,
    ): void {
        DB::table('ai_job_results')->insert([
            'job_class' => self::class,
            'connection_id' => $this->connectionId,
            'status' => 'processing',
            'input' => json_encode(['connection_id' => $this->connectionId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::getPdo()->lastInsertId();

        try {
            $context = $contextBuilder->build($this->connectionId, 'database');

            $healthScore = $this->calculateHealthScore($context);
            $inputTokens = 0;
            $outputTokens = 0;

            DB::table('ai_job_results')
                ->where('id', $jobId)
                ->update([
                    'status' => 'completed',
                    'result' => json_encode($healthScore),
                    'progress' => 100,
                    'updated_at' => now(),
                ]);

            DB::table('ai_analyses')->insert([
                'connection_id' => $this->connectionId,
                'connection_name' => 'Health: ' . $context->database,
                'provider' => 'rule-based',
                'result' => json_encode($healthScore),
                'score' => $healthScore['score'],
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

    private function calculateHealthScore(object $context): array
    {
        $tables = $context->tables;
        $totalTables = count($tables);

        if ($totalTables === 0) {
            return [
                'score' => 0,
                'grade' => 'F',
                'dimensions' => [
                    'structure' => ['score' => 0, 'weight' => 30, 'issues' => ['No tables found']],
                    'performance' => ['score' => 0, 'weight' => 35, 'issues' => ['No data']],
                    'index' => ['score' => 0, 'weight' => 20, 'issues' => ['No indexes']],
                    'security' => ['score' => 100, 'weight' => 15, 'issues' => []],
                ],
            ];
        }

        // Structure Quality (30%)
        $tablesWithPK = 0;
        $tablesWithFK = 0;
        foreach ($tables as $table) {
            $hasPK = false;
            foreach ($table->indexes ?? [] as $index) {
                if ($index->type === 'primary' || (isset($index->primary) && $index->primary)) {
                    $hasPK = true;
                    break;
                }
            }
            if ($hasPK) {
                $tablesWithPK++;
            }
            if (!empty($table->foreignKeys ?? [])) {
                $tablesWithFK++;
            }
        }
        $structureScore = (int) round(
            (($tablesWithPK / $totalTables) * 50) +
            (($tablesWithFK / $totalTables) * 50)
        );

        // Performance Quality (35%)
        $totalColumns = 0;
        $totalIndexes = 0;
        foreach ($tables as $table) {
            $totalColumns += count($table->columns ?? []);
            $totalIndexes += count($table->indexes ?? []);
        }
        $indexRatio = $totalColumns > 0 ? $totalIndexes / $totalColumns : 0;
        $performanceScore = (int) round(min(100, ($indexRatio / 0.5) * 100));

        // Index Quality (20%)
        $tablesWithIndex = 0;
        foreach ($tables as $table) {
            if (count($table->indexes ?? []) > 0) {
                $tablesWithIndex++;
            }
        }
        $indexQualityScore = (int) round(($tablesWithIndex / $totalTables) * 100);

        // Security Quality (15%)
        $securityIssues = [];
        $securityScore = 100;

        foreach ($tables as $table) {
            foreach ($table->columns ?? [] as $col) {
                $colName = strtolower($col->name);
                if (in_array($colName, ['password', 'secret', 'token', 'api_key', 'credit_card', 'ssn'])) {
                    $securityIssues[] = "{$table->name}.{$col->name} contains sensitive data";
                    $securityScore = max(0, $securityScore - 15);
                }
            }
        }

        $finalScore = (int) round(
            ($structureScore * 0.30) +
            ($performanceScore * 0.35) +
            ($indexQualityScore * 0.20) +
            ($securityScore * 0.15)
        );

        $grade = match (true) {
            $finalScore >= 90 => 'A',
            $finalScore >= 75 => 'B',
            $finalScore >= 60 => 'C',
            $finalScore >= 40 => 'D',
            default => 'F',
        };

        return [
            'score' => $finalScore,
            'grade' => $grade,
            'dimensions' => [
                'structure' => [
                    'score' => $structureScore,
                    'weight' => 30,
                    'issues' => $tablesWithPK < $totalTables
                        ? [($totalTables - $tablesWithPK) . ' tables missing primary key']
                        : [],
                ],
                'performance' => [
                    'score' => $performanceScore,
                    'weight' => 35,
                    'issues' => $performanceScore < 50
                        ? ['Low index-to-column ratio (' . round($indexRatio, 2) . ')']
                        : [],
                ],
                'index' => [
                    'score' => $indexQualityScore,
                    'weight' => 20,
                    'issues' => $tablesWithIndex < $totalTables
                        ? [($totalTables - $tablesWithIndex) . ' tables have no indexes']
                        : [],
                ],
                'security' => [
                    'score' => $securityScore,
                    'weight' => 15,
                    'issues' => $securityIssues,
                ],
            ],
        ];
    }
}
