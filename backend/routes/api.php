<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationTransitionController;
use App\Http\Controllers\LlmSummaryController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('statuses', [StatusController::class, 'index']);

    // Se declara antes que {application} para que "summary" no se interprete como id.
    Route::get('applications/summary/llm', LlmSummaryController::class);

    Route::get('applications', [ApplicationController::class, 'index']);
    Route::post('applications', [ApplicationController::class, 'store']);

    Route::whereNumber('application')->group(function () {
        Route::get('applications/{application}', [ApplicationController::class, 'show']);
        Route::put('applications/{application}', [ApplicationController::class, 'update']);
        Route::patch('applications/{application}', [ApplicationController::class, 'update']);

        Route::get('applications/{application}/transitions', [ApplicationTransitionController::class, 'index']);
        Route::post('applications/{application}/transition', [ApplicationTransitionController::class, 'store']);
    });
});
