<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'running',
        'api_base' => url('/api/v1'),
    ]);
});
