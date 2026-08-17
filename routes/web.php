<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\VideoKycWebController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// ✅ VKYC pages
Route::get('/vkyc/{session_id}', [VideoKycWebController::class, 'page'])->name('vkyc.page');
Route::get('/vkyc/{session_id}/completed', [VideoKycWebController::class, 'completed'])->name('vkyc.completed');

require __DIR__.'/auth.php';