<?php

use App\Modules\Connection\Controllers\ConnectionController;
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
});
