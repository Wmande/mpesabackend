<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\Route;

Route::middleware('cors')->group(function () {
    Route::post('/mpesa/stk', [MpesaController::class, 'stkPush']);
    Route::post('/mpesa/callback', [MpesaController::class, 'callback']);
});