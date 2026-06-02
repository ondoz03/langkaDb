<?php

use App\Modules\AIAgent\Controllers\AIController;
use App\Modules\AIAgent\Controllers\AsyncJobController;
use App\Modules\Connection\Controllers\ConnectionController;
use App\Modules\Connection\Controllers\QueryController;
use App\Modules\Schema\Controllers\SchemaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::get('connections', [ConnectionController::class, 'index'])->name('api.connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])->name('api.connections.store');
    Route::get('connections/{id}', [ConnectionController::class, 'show'])->name('api.connections.show');
    Route::put('connections/{id}', [ConnectionController::class, 'update'])->name('api.connections.update');
    Route::delete('connections/{id}', [ConnectionController::class, 'destroy'])->name('api.connections.destroy');
    Route::post('connections/{id}/test', [ConnectionController::class, 'test'])->name('api.connections.test');

    Route::get('connections/{id}/schema', [SchemaController::class, 'schema'])->name('api.connections.schema');
    Route::get('connections/{id}/schema/tables', [SchemaController::class, 'tables'])->name('api.connections.schema.tables');
    Route::get('connections/{id}/schema/tables/{table}', [SchemaController::class, 'tableDetail'])->name('api.connections.schema.table');
    Route::get('connections/{id}/schema/context', [SchemaController::class, 'context'])->name('api.connections.schema.context');
    Route::post('connections/{id}/query', [QueryController::class, 'execute'])->name('api.connections.query');
    Route::post('connections/{id}/explain', [\App\Modules\Query\Controllers\ExplainController::class, 'analyze'])->name('api.connections.explain');

    Route::post('connections/{id}/ai/analyze', [AIController::class, 'analyzeSchema'])->name('api.connections.ai.analyze');
    Route::get('connections/{id}/ai/health', [AIController::class, 'healthScore'])->name('api.connections.ai.health');
    Route::post('connections/{id}/ai/chat', [AIController::class, 'chat'])->name('api.connections.ai.chat');
    Route::get('connections/{id}/ai/chat-history', [AIController::class, 'listChatHistory'])->name('api.connections.ai.chat-history');
    Route::delete('ai/chat-history/{id}', [AIController::class, 'deleteChatHistory'])->name('api.ai.chat-history.delete');
    Route::delete('connections/{id}/ai/chat-history', [AIController::class, 'clearChatHistory'])->name('api.connections.ai.chat-history.clear');
    Route::get('ai/analyses', [AIController::class, 'listAnalyses'])->name('api.ai.analyses');
    Route::delete('ai/analyses/{id}', [AIController::class, 'deleteAnalysis'])->name('api.ai.analyses.delete');

    // Async job routes — queue-based AI processing
    Route::post('connections/{id}/ai/analyze-async', [AsyncJobController::class, 'analyzeAsync'])->name('api.connections.ai.analyze-async');
    Route::post('connections/{id}/ai/chat-async', [AsyncJobController::class, 'chatAsync'])->name('api.connections.ai.chat-async');
    Route::post('connections/{id}/ai/health-score', [AsyncJobController::class, 'healthScoreAsync'])->name('api.connections.ai.health-score');
    Route::post('connections/{id}/ai/documentation', [AsyncJobController::class, 'documentationAsync'])->name('api.connections.ai.documentation');
    Route::get('connections/{id}/ai/jobs', [AsyncJobController::class, 'index'])->name('api.connections.ai.jobs');
    Route::get('jobs/{jobId}/status', [AsyncJobController::class, 'status'])->name('api.jobs.status');
    Route::get('jobs/{jobId}/result', [AsyncJobController::class, 'result'])->name('api.jobs.result');
});
