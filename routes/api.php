<?php

use App\Http\Controllers\Api\SensorController;
use Illuminate\Support\Facades\Route;

Route::get('/sensor', [SensorController::class, 'index']);
Route::post('/sensor', [SensorController::class, 'store']);
Route::get('/sensor/history', [SensorController::class, 'history']);