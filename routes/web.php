<?php

use ApiPerformanceAnalyzer\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/requests', [DashboardController::class, 'requests'])->name('requests');
Route::get('/endpoints', [DashboardController::class, 'endpoints'])->name('endpoints');
Route::get('/n-plus-one', [DashboardController::class, 'nPlusOne'])->name('n-plus-one');
Route::get('/slow-queries', [DashboardController::class, 'slowQueries'])->name('slow-queries');
Route::get('/requests/{uuid}', [DashboardController::class, 'show'])->name('requests.show');
