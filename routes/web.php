<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\Route;

Route::post('/api/mpesa/stk', [MpesaController::class, 'stkPush']);
Route::post('/api/mpesa/callback', [MpesaController::class, 'callback']);

