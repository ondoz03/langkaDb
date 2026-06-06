<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\AIAnalysisJob;
use App\Jobs\AIChatJob;
use App\Jobs\DocumentationJob;
use App\Jobs\HealthScoreJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsyncJobController extends Controller
{
    /**
     * Dispatch an async schema analysis job.
     */
    public function analyzeAsync(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:openai,anthropic,deepseek,ollama,rule',
            'connection_name' => 'nullable|string|max:255',
        ]);

        $provider = $request->input('provider', 'openai');
        $apiKey = $request->input('api_key') ?? '';
        $systemPrompt = $request->input('system_prompt') ?? '';
        $connectionName = $request->input('connection_name', 'Unknown');

        // Create the job result record BEFORE dispatching so the frontend
        // can immediately get a valid job_id for polling
        $jobId = DB::table('ai_job_results')->insertGetId([
            'job_class' => AIAnalysisJob::class,
            'connection_id' => $id,
            'status' => 'pending',
            'input' => json_encode([
                'connection_id' => $id,
                'provider' => $provider,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AIAnalysisJob::dispatch(
            connectionId: $id,
            connectionName: $connectionName,
            provider: $provider,
            apiKey: $apiKey,
            systemPrompt: $systemPrompt,
            jobResultId: $jobId,
        );

        return response()->json([
            'data' => [
                'job_id' => $jobId,
                'status' => 'pending',
            ],
        ]);
    }

    /**
     * Dispatch an async AI chat job.
     */
    public function chatAsync(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'history' => 'nullable|array',
            'provider' => 'nullable|string|in:openai,anthropic,deepseek,ollama',
            'connection_name' => 'nullable|string|max:255',
        ]);

        $message = $request->input('message');
        $history = $request->input('history', []);
        $provider = $request->input('provider', 'openai');
        $apiKey = $request->input('api_key') ?? '';
        $systemPrompt = $request->input('system_prompt') ?? '';
        $connectionName = $request->input('connection_name', 'Unknown');

        AIChatJob::dispatch(
            connectionId: $id,
            connectionName: $connectionName,
            message: $message,
            history: $history,
            provider: $provider,
            apiKey: $apiKey,
            systemPrompt: $systemPrompt,
        );

        $jobResult = DB::table('ai_job_results')
            ->where('connection_id', $id)
            ->where('job_class', AIChatJob::class)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'job_id' => $jobResult?->id,
                'status' => 'processing',
            ],
        ]);
    }

    /**
     * Dispatch an async health score calculation job.
     */
    public function healthScoreAsync(string $id): JsonResponse
    {
        HealthScoreJob::dispatch(connectionId: $id);

        $jobResult = DB::table('ai_job_results')
            ->where('connection_id', $id)
            ->where('job_class', HealthScoreJob::class)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'job_id' => $jobResult?->id,
                'status' => 'processing',
            ],
        ]);
    }

    /**
     * Dispatch an async documentation generation job.
     */
    public function documentationAsync(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:openai,anthropic,deepseek,ollama',
            'format' => 'nullable|string|in:markdown,html,json',
        ]);

        $provider = $request->input('provider', 'openai');
        $apiKey = $request->input('api_key') ?? '';
        $systemPrompt = $request->input('system_prompt') ?? '';
        $format = $request->input('format', 'markdown');

        DocumentationJob::dispatch(
            connectionId: $id,
            provider: $provider,
            apiKey: $apiKey,
            systemPrompt: $systemPrompt,
            format: $format,
        );

        $jobResult = DB::table('ai_job_results')
            ->where('connection_id', $id)
            ->where('job_class', DocumentationJob::class)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'job_id' => $jobResult?->id,
                'status' => 'processing',
            ],
        ]);
    }

    /**
     * Get the status of a queued job.
     */
    public function status(int $jobId): JsonResponse
    {
        $job = DB::table('ai_job_results')->find($jobId);

        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $job->id,
                'job_class' => $job->job_class,
                'connection_id' => $job->connection_id,
                'status' => $job->status,
                'progress' => $job->progress,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
            ],
        ]);
    }

    /**
     * Get the result of a completed job.
     */
    public function result(int $jobId): JsonResponse
    {
        $job = DB::table('ai_job_results')->find($jobId);

        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        if ($job->status === 'failed') {
            return response()->json([
                'data' => [
                    'id' => $job->id,
                    'status' => 'failed',
                    'error' => $job->error,
                ],
            ], 422);
        }

        if ($job->status !== 'completed') {
            return response()->json([
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'progress' => $job->progress,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $job->id,
                'status' => 'completed',
                'result' => json_decode($job->result, true),
            ],
        ]);
    }

    /**
     * List recent jobs for a connection.
     */
    public function index(string $connectionId): JsonResponse
    {
        $jobs = DB::table('ai_job_results')
            ->where('connection_id', $connectionId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'job_class' => class_basename($j->job_class),
                'status' => $j->status,
                'progress' => $j->progress,
                'created_at' => $j->created_at,
                'updated_at' => $j->updated_at,
            ]);

        return response()->json(['data' => $jobs]);
    }
}
