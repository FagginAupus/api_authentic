<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Outras rotas da API podem ser adicionadas aqui
// As rotas do DocumentController estão em web.php no grupo api