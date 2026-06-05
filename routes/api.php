<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GradeController;

Route::middleware(['iae.auth'])->prefix('v1')->group(function () {
    Route::get('/curriculums', [GradeController::class, 'curriculums']);
    Route::get('/grades/{student_id}', [GradeController::class, 'show']);
    Route::post('/grades/initialize', [GradeController::class, 'initialize']);
});
