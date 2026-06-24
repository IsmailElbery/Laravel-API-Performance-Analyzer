<?php

use ApiPerformanceAnalyzer\Http\Controllers\Api\EndpointsController;
use ApiPerformanceAnalyzer\Http\Controllers\Api\RequestsController;
use ApiPerformanceAnalyzer\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('requests', [RequestsController::class, 'index'])->name('requests.index');
Route::get('requests/{uuid}', [RequestsController::class, 'show'])->name('requests.show');

Route::get('endpoints', [EndpointsController::class, 'index'])->name('endpoints.index');

Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
Route::get('stats/slow-queries', [StatsController::class, 'slowQueries'])->name('stats.slow-queries');
Route::get('stats/n-plus-one', [StatsController::class, 'nPlusOne'])->name('stats.n-plus-one');
