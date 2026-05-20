<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('/connections', 'Connections/Index')->name('connections.index');
    Route::inertia('/graph', 'Graph/Index')->name('graph.index');
    Route::inertia('/insights', 'Insights/Index')->name('insights.index');
    Route::inertia('/queries', 'Queries/Index')->name('queries.index');
    Route::inertia('/monitoring', 'Monitoring/Index')->name('monitoring.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/api.php';
