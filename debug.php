<?php
/**
 * Script de debug simples - execute com: php debug_simples.php
 */

echo "=== DEBUG SIMPLES ===\n\n";

// 1. Verificações básicas
echo "1. AMBIENTE:\n";
echo "   PHP Version: " . phpversion() . "\n";

// 2. Verificar arquivo .env
echo "\n2. ARQUIVO .ENV:\n";
$envPath = __DIR__ . '/.env';
echo "   .env exists: " . (file_exists($envPath) ? 'OK' : 'MISSING') . "\n";

if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    // Verifica configurações importantes
    $hasToken = strpos($envContent, 'AUTENTIQUE_API_TOKEN') !== false;
    $hasApiUrl = strpos($envContent, 'AUTENTIQUE_API_URL') !== false;
    $hasDebug = strpos($envContent, 'APP_DEBUG=true') !== false;
    
    echo "   AUTENTIQUE_API_TOKEN presente: " . ($hasToken ? 'OK' : 'MISSING') . "\n";
    echo "   AUTENTIQUE_API_URL presente: " . ($hasApiUrl ? 'OK' : 'MISSING') . "\n";
    echo "   APP_DEBUG ativo: " . ($hasDebug ? 'OK' : 'NO') . "\n";
    
    // Mostra as linhas da Autentique (sem expor o token completo)
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        if (strpos($line, 'AUTENTIQUE') !== false) {
            if (strpos($line, 'TOKEN') !== false) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2 && strlen($parts[1]) > 10) {
                    echo "   " . $parts[0] . "=" . substr($parts[1], 0, 10) . "...(hidden)\n";
                } else {
                    echo "   " . $line . "\n";
                }
            } else {
                echo "   " . $line . "\n";
            }
        }
    }
}

// 3. Verificações de diretórios
echo "\n3. DIRETÓRIOS:\n";
$dirs = [
    'storage/app' => __DIR__ . '/storage/app',
    'storage/app/temp' => __DIR__ . '/storage/app/temp',
    'storage/logs' => __DIR__ . '/storage/logs',
    'public' => __DIR__ . '/public'
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    echo "   {$name}: " . ($exists ? 'EXISTS' : 'MISSING') . 
         ($exists ? ($writable ? ' + WRITABLE' : ' + READ-ONLY') : '') . "\n";
    
    // Cria diretórios que não existem
    if (!$exists && strpos($name, 'temp') !== false) {
        mkdir($path, 0755, true);
        echo "      -> Criado: " . (is_dir($path) ? 'OK' : 'FAILED') . "\n";
    }
}

// 4. PDF Template
echo "\n4. PDF TEMPLATE:\n";
$pdfPath = __DIR__ . '/public/PROCURACAO_E_TERMO_DE_ADESAO.pdf';
echo "   Template: " . (file_exists($pdfPath) ? 'OK' : 'MISSING') . "\n";
if (file_exists($pdfPath)) {
    echo "   Size: " . number_format(filesize($pdfPath)) . " bytes\n";
}

// 5. Teste básico de requisição
echo "\n5. TESTE DE REQUISIÇÃO LOCAL:\n";
$testUrls = [
    'http://localhost:8000/',
    'http://localhost:8000/test',
    'http://localhost:8000/test-sandbox'
];

foreach ($testUrls as $url) {
    echo "   Testing {$url}:\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result !== false) {
        $isHtml = strpos($result, '<!DOCTYPE') !== false || strpos($result, '<html') !== false;
        $isJson = json_decode($result) !== null;
        
        echo "      Status: OK\n";
        echo "      Type: " . ($isJson ? 'JSON' : ($isHtml ? 'HTML' : 'OTHER')) . "\n";
        echo "      Size: " . strlen($result) . " bytes\n";
        
        if ($isJson) {
            $json = json_decode($result, true);
            echo "      JSON keys: " . implode(', ', array_keys($json)) . "\n";
        }
    } else {
        echo "      Status: FAILED\n";
        echo "      Error: Servidor não responde ou rota não existe\n";
    }
    echo "\n";
}

// 6. Verificar se o servidor está rodando
echo "6. VERIFICAÇÃO DO SERVIDOR:\n";
$socket = @fsockopen('localhost', 8000, $errno, $errstr, 1);
if ($socket) {
    echo "   Porta 8000: ABERTA\n";
    fclose($socket);
} else {
    echo "   Porta 8000: FECHADA\n";
    echo "   Execute: php artisan serve\n";
}

// 7. Logs recentes
echo "\n7. LOGS RECENTES:\n";
$logPath = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logPath)) {
    echo "   Log file: OK (" . number_format(filesize($logPath)) . " bytes)\n";
    
    $lines = file($logPath);
    $recentLines = array_slice($lines, -10); // Últimas 10 linhas
    
    echo "   Últimas entradas:\n";
    foreach ($recentLines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            echo "      " . substr($line, 0, 100) . "...\n";
        }
    }
} else {
    echo "   Log file: MISSING\n";
}

echo "\n=== RECOMENDAÇÕES ===\n";

if (!file_exists($envPath)) {
    echo "❌ Crie o arquivo .env copiando de .env.example\n";
}

if (!file_exists($pdfPath)) {
    echo "❌ Adicione PROCURACAO_E_TERMO_DE_ADESAO.pdf na pasta public/\n";
}

$socket = @fsockopen('localhost', 8000, $errno, $errstr, 1);
if (!$socket) {
    echo "❌ Inicie o servidor: php artisan serve\n";
} else {
    fclose($socket);
}

echo "✅ Execute: php artisan config:clear\n";
echo "✅ Execute: php artisan route:clear\n";
echo "✅ Monitore logs: tail -f storage/logs/laravel.log\n";

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Certifique-se que php artisan serve está rodando\n";
echo "2. Teste http://localhost:8000/ no navegador\n";
echo "3. Teste http://localhost:8000/test no navegador\n";
echo "4. Se funcionarem, teste o formulário\n";

echo "\n";