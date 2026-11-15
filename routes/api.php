<?php

use Domain\Execution\Presentation\Controllers\JobController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do Execution (prefixo /execution/api). Os comandos da saga chegam
| pela fila SQS; aqui ficam as ações do mecânico.
*/

Route::middleware('staff')->prefix('jobs')->group(function () {
    Route::get('/', [JobController::class, 'index']);
    Route::get('{id}', [JobController::class, 'show'])->whereNumber('id');
    Route::post('{id}/start-diagnosis', [JobController::class, 'startDiagnosis'])->whereNumber('id');
    Route::post('{id}/start-repair', [JobController::class, 'startRepair'])->whereNumber('id');
    Route::post('{id}/notes', [JobController::class, 'addNote'])->whereNumber('id');
    Route::post('{id}/finish', [JobController::class, 'finish'])->whereNumber('id');
    Route::post('{id}/fail', [JobController::class, 'fail'])->whereNumber('id');
});
