<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

use App\Modules\AIAgent\Agents\DocumentationAgent;
use App\Modules\AIAgent\Agents\MonitoringAgent;
use App\Modules\AIAgent\Agents\OptimizationAgent;
use App\Modules\AIAgent\Agents\SchemaAgent;
use App\Modules\AIAgent\Agents\SecurityAgent;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;

class Orchestrator
{
    /**
     * Weight distribution for composite health score.
     */
    private const SCORE_WEIGHTS = [
        'schema' => 0.25,
        'security' => 0.25,
        'monitoring' => 0.20,
        'optimization' => 0.20,
        'documentation' => 0.10,
    ];

    public function __construct(
        private readonly SchemaAgent $schemaAgent,
        private readonly SecurityAgent $securityAgent,
        private readonly MonitoringAgent $monitoringAgent,
        private readonly OptimizationAgent $optimizationAgent,
        private readonly DocumentationAgent $documentationAgent,
    ) {}

    /**
     * Original single-agent analyze (backward compatible).
     * Delegates to SchemaAgent only.
     */
    public function analyzeSchema(SchemaContextDTO $context): array
    {
        $result = $this->schemaAgent->analyze($context);

        return $result->toArray();
    }

    /**
     * Multi-agent full analysis — runs all 5 agents sequentially and
     * aggregates findings, recommendations, and composite health score.
     *
     * @return array{
     *   findings: array,
     *   recommendations: array,
     *   score: int,
     *   composite_score: int,
     *   agents: array<string, array>,
     *   metadata: array
     * }
     */
    public function analyzeFull(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): array
    {
        $agentResults = [
            'schema' => $this->schemaAgent->analyze($context, $apiKey, $provider),
            'security' => $this->securityAgent->analyze($context, $apiKey, $provider),
            'monitoring' => $this->monitoringAgent->analyze($context, $apiKey, $provider),
            'optimization' => $this->optimizationAgent->analyze($context, $apiKey, $provider),
            'documentation' => $this->documentationAgent->analyze($context, $apiKey, $provider),
        ];

        // Aggregate findings and recommendations
        $allFindings = [];
        $allRecommendations = [];
        $agentScores = [];

        foreach ($agentResults as $name => $result) {
            assert($result instanceof AgentResultDTO);

            // Tag each finding/recommendation with source agent
            foreach ($result->findings as $finding) {
                $finding['source_agent'] = $name;
                $allFindings[] = $finding;
            }

            foreach ($result->recommendations as $rec) {
                $rec['source_agent'] = $name;
                $allRecommendations[] = $rec;
            }

            $agentScores[$name] = $result->score;
        }

        // Calculate composite health score (weighted average)
        $compositeScore = $this->computeCompositeScore($agentScores);

        return [
            'findings' => $allFindings,
            'recommendations' => $allRecommendations,
            'score' => $compositeScore,
            'composite_score' => $compositeScore,
            'agents' => array_map(fn (AgentResultDTO $r) => [
                'agent' => $r->agent,
                'score' => $r->score,
                'findings_count' => count($r->findings),
                'recommendations_count' => count($r->recommendations),
            ], $agentResults),
            'metadata' => [
                'total_findings' => count($allFindings),
                'total_recommendations' => count($allRecommendations),
                'agent_scores' => $agentScores,
                'weight_distribution' => self::SCORE_WEIGHTS,
                'source' => 'multi-agent',
            ],
        ];
    }

    /**
     * Compute composite health score from individual agent scores.
     *
     * Each agent contributes to the overall health with a predefined weight:
     * - Schema (structure, clustering): 25%
     * - Security (PII, permissions, constraints): 25%
     * - Monitoring (performance, index coverage): 20%
     * - Optimization (missing indexes, duplicates): 20%
     * - Documentation (data dictionary completeness): 10%
     */
    private function computeCompositeScore(array $agentScores): int
    {
        $weightedSum = 0;
        $weightTotal = 0;

        foreach (self::SCORE_WEIGHTS as $agent => $weight) {
            $score = $agentScores[$agent] ?? 0;
            $weightedSum += $score * $weight;
            $weightTotal += $weight;
        }

        $composite = $weightTotal > 0 ? $weightedSum / $weightTotal : 0;

        return (int) round(min(100, max(0, $composite)));
    }
}
