<?php

use App\Modules\AIAgent\Controllers\AIController;
use App\Modules\AIAgent\Controllers\AsyncJobController;
use App\Modules\Connection\Controllers\ConnectionController;
use App\Modules\Connection\Controllers\QueryController;
use App\Modules\Schema\Controllers\ExportController;
use App\Modules\Schema\Controllers\ImportController;
use App\Modules\Schema\Controllers\SchemaController;
use App\Modules\Schema\Controllers\SnapshotController;
use App\Modules\Designer\Controllers\DesignerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::get('connections', [ConnectionController::class, 'index'])->name('api.connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])->name('api.connections.store');
    Route::get('connections/{id}', [ConnectionController::class, 'show'])->name('api.connections.show');
    Route::put('connections/{id}', [ConnectionController::class, 'update'])->name('api.connections.update');
    Route::delete('connections/{id}', [ConnectionController::class, 'destroy'])->name('api.connections.destroy');
    Route::post('connections/{id}/test', [ConnectionController::class, 'test'])->name('api.connections.test');
    Route::post('connections/test-connection', [ConnectionController::class, 'testConnection'])->name('api.connections.test-connection');

    Route::get('connections/{id}/schema', [SchemaController::class, 'schema'])->name('api.connections.schema');
    Route::get('connections/{id}/schema/tables', [SchemaController::class, 'tables'])->name('api.connections.schema.tables');
    Route::get('connections/{id}/schema/tables/{table}', [SchemaController::class, 'tableDetail'])->name('api.connections.schema.table');
    Route::get('connections/{id}/schema/context', [SchemaController::class, 'context'])->name('api.connections.schema.context');
    Route::post('connections/{id}/query', [QueryController::class, 'execute'])->name('api.connections.query');
    Route::post('connections/{id}/explain', [\App\Modules\Query\Controllers\ExplainController::class, 'analyze'])->name('api.connections.explain');

    // Export routes
    Route::get('connections/{id}/export/sql', [ExportController::class, 'exportSql'])->name('api.connections.export.sql');
    Route::get('connections/{id}/export/sql/{table}', [ExportController::class, 'exportTableSql'])->name('api.connections.export.sql.table');

    // Import routes
    Route::post('schema/import-sql', [ImportController::class, 'importSql'])->name('api.schema.import-sql');

    // Snapshot routes
    Route::get('connections/{id}/snapshots', [SnapshotController::class, 'index'])->name('api.connections.snapshots.index');
    Route::post('connections/{id}/snapshots', [SnapshotController::class, 'store'])->name('api.connections.snapshots.store');
    Route::get('snapshots/{id}', [SnapshotController::class, 'show'])->name('api.snapshots.show');
    Route::delete('snapshots/{id}', [SnapshotController::class, 'destroy'])->name('api.snapshots.destroy');
    Route::get('snapshots/{id}/diff', [SnapshotController::class, 'diff'])->name('api.snapshots.diff');

    Route::post('connections/{id}/ai/analyze', [AIController::class, 'analyzeSchema'])->name('api.connections.ai.analyze');
    Route::get('connections/{id}/ai/health', [AIController::class, 'healthScore'])->name('api.connections.ai.health');
    Route::post('connections/{id}/ai/chat', [AIController::class, 'chat'])->name('api.connections.ai.chat');
    Route::post('connections/{id}/ai/chat-stream', [AIController::class, 'chatStream'])->name('api.connections.ai.chat-stream');
    Route::get('connections/{id}/ai/chat-history', [AIController::class, 'listChatHistory'])->name('api.connections.ai.chat-history');
    Route::delete('ai/chat-history/{id}', [AIController::class, 'deleteChatHistory'])->name('api.ai.chat-history.delete');
    Route::delete('connections/{id}/ai/chat-history', [AIController::class, 'clearChatHistory'])->name('api.connections.ai.chat-history.clear');
    Route::get('ai/analyses', [AIController::class, 'listAnalyses'])->name('api.ai.analyses');
    Route::delete('ai/analyses/{id}', [AIController::class, 'deleteAnalysis'])->name('api.ai.analyses.delete');

    // Monitoring routes
    Route::get('monitoring/stats', [AIController::class, 'monitoringStats'])->name('api.monitoring.stats');
    Route::get('monitoring/alerts', [AIController::class, 'monitoringAlerts'])->name('api.monitoring.alerts');

    // Async job routes — queue-based AI processing
    Route::post('connections/{id}/ai/analyze-async', [AsyncJobController::class, 'analyzeAsync'])->name('api.connections.ai.analyze-async');
    Route::post('connections/{id}/ai/chat-async', [AsyncJobController::class, 'chatAsync'])->name('api.connections.ai.chat-async');
    Route::post('connections/{id}/ai/health-score', [AsyncJobController::class, 'healthScoreAsync'])->name('api.connections.ai.health-score');
    Route::post('connections/{id}/ai/documentation', [AsyncJobController::class, 'documentationAsync'])->name('api.connections.ai.documentation');
    Route::get('connections/{id}/ai/jobs', [AsyncJobController::class, 'index'])->name('api.connections.ai.jobs');
    Route::get('jobs/{jobId}/status', [AsyncJobController::class, 'status'])->name('api.jobs.status');
    Route::get('jobs/{jobId}/result', [AsyncJobController::class, 'result'])->name('api.jobs.result');

    // AI Schema generation from natural language (Prompt to ERD)
    Route::post('ai/generate-schema', [AIController::class, 'generateSchema'])->name('api.ai.generate-schema');

    // Designer / Diagram routes
    Route::get('designer/diagrams', [DesignerController::class, 'index'])->name('api.designer.diagrams.index');
    Route::post('designer/diagrams', [DesignerController::class, 'store'])->name('api.designer.diagrams.store');
    Route::get('designer/diagrams/{id}', [DesignerController::class, 'show'])->name('api.designer.diagrams.show');
    Route::put('designer/diagrams/{id}', [DesignerController::class, 'update'])->name('api.designer.diagrams.update');
    Route::delete('designer/diagrams/{id}', [DesignerController::class, 'destroy'])->name('api.designer.diagrams.destroy');
    Route::get('connections/{connectionId}/designer/diagrams', [DesignerController::class, 'byConnection'])->name('api.connections.designer.diagrams');
    Route::post('designer/save-layout', [DesignerController::class, 'saveLayout'])->name('api.designer.save-layout');
});
