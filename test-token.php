<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AutentiqueService;

try {
    $service = new AutentiqueService();
    
    // Dados simples do documento
    $documentData = [
        'name' => 'Teste de Documento',
        'refusable' => true,
        'sortable' => false,
        'message' => 'Documento de teste'
    ];
    
    // Signatários simples
    $signers = [
        [
            'email' => 'teste@exemplo.com',
            'action' => 'SIGN'
        ]
    ];
    
    // Usar um arquivo PDF simples (crie um arquivo vazio para teste)
    $testPdfPath = 'test.pdf';
    file_put_contents($testPdfPath, '%PDF-1.4 fake pdf content for testing');
    
    echo "=== TESTE DE CRIAÇÃO DE DOCUMENTO ===\n";
    echo "Tentando criar documento...\n";
    
    $result = $service->createDocument($documentData, $signers, $testPdfPath);
    
    echo "SUCESSO!\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    
    // Remove arquivo de teste
    unlink($testPdfPath);
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    
    // Remove arquivo de teste se existir
    if (file_exists('test.pdf')) {
        unlink('test.pdf');
    }
}
?>