<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\DocumentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal
Route::get('/', [DocumentController::class, 'index']);

// Dashboard de documentos
Route::get('/dashboard', [DocumentController::class, 'dashboard']);
Route::get('/documents', [DocumentController::class, 'dashboard']);

// Testes da API
Route::get('/test', [DocumentController::class, 'test']);
Route::get('/test-sandbox', [DocumentController::class, 'testSandbox']);

// API Routes (todas com CSRF desabilitado via middleware)
Route::prefix('api')->middleware(['api'])->group(function () {
    Route::post('/documents/create-with-pdf', [DocumentController::class, 'createWithPDF']);
    Route::get('/documents/{id}', [DocumentController::class, 'getDocument']);
    Route::get('/documents', [DocumentController::class, 'listDocuments']);
    Route::post('/documents/resend', [DocumentController::class, 'resendSignatures']);
    Route::post('/documents/{id}/sync', [DocumentController::class, 'syncDocument']); // Rota de sincronização
    Route::post('/webhook', [DocumentController::class, 'webhook']); // Webhook principal
    Route::post('/webhook/autentique', [DocumentController::class, 'webhook']); // Webhook alternativo
});

// Fallback para debug
Route::fallback(function () {
    \Log::error('Route not found', [
        'url' => request()->url(),
        'method' => request()->method(),
        'headers' => request()->headers->all()
    ]);
    
    return response()->json([
        'error' => 'Route not found',
        'url' => request()->url(),
        'method' => request()->method(),
        'available_routes' => [
            'GET /' => 'Página principal',
            'GET /dashboard' => 'Dashboard de documentos',
            'GET /test' => 'Teste da API',
            'GET /test-sandbox' => 'Teste sandbox',
            'POST /api/documents/create-with-pdf' => 'Criar documento',
            'POST /api/documents/{id}/sync' => 'Sincronizar documento',
            'POST /api/webhook' => 'Webhook da Autentique',
            'GET /api/documents' => 'Listar documentos',
            'GET /api/documents/{id}' => 'Buscar documento'
        ]
    ], 404);
});

Route::get('/teste-escrita', function () {
    try {
        $conteudo = "Arquivo gerado em " . now();
        Storage::disk('local')->put('temp/teste.txt', $conteudo);
        return "✅ Arquivo criado com sucesso em storage/app/temp/teste.txt";
    } catch (\Exception $e) {
        return "❌ Erro ao criar arquivo: " . $e->getMessage();
    }
});