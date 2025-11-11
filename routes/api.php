<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NodeApiController;

Route::get('/nodes', [NodeApiController::class, 'index']);
Route::get('/nodes/{id}', [NodeApiController::class, 'show']);