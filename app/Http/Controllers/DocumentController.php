<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AutentiqueService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Document;

class DocumentController extends Controller
{
    private $autentiqueService;

    public function __construct(AutentiqueService $autentiqueService)
    {
        $this->autentiqueService = $autentiqueService;
        
        // Garante que o diretório temp existe
        $this->ensureTempDirectoryExists();
        
        // Log de inicialização
        Log::info('DocumentController inicializado', [
            'timestamp' => now(),
            'token_configured' => !empty(env('AUTENTIQUE_API_TOKEN')),
            'api_url' => env('AUTENTIQUE_API_URL', 'https://api.autentique.com.br/v2/graphql')
        ]);
    }

    /**
     * Garante que o diretório temp existe
     */
    private function ensureTempDirectoryExists()
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            \Illuminate\Support\Facades\Storage::makeDirectory('temp');
            Log::info('Diretório temp criado via Storage', ['path' => $tempDir]);
        }
    }

    /**
     * Mostra a página principal
     */
    public function index()
    {
        Log::info('Página principal acessada');
        return view('document-form');
    }

    /**
     * Recebe PDF do frontend e envia para assinatura (versão com logs detalhados)
     */
    public function createWithPDF(Request $request): JsonResponse
{
    Log::info('=== INÍCIO createWithPDF FINAL ===', [
        'timestamp' => now(),
        'method' => $request->method(),
        'url' => $request->url(),
        'code_version' => 'v4.0_final_working'
    ]);

    // Validação
    $validator = Validator::make($request->all(), [
        'pdf_file' => 'required|file|mimes:pdf|max:20480',
        'document_data' => 'required|string',
        'signers' => 'required|string'
    ]);

    if ($validator->fails()) {
        Log::error('Validação falhou', $validator->errors()->all());
        return response()->json([
            'error' => 'Dados inválidos: ' . implode(', ', $validator->errors()->all())
        ], 422);
    }

    try {
        // Decodifica JSON
        $documentData = json_decode($request->document_data, true);
        $signers = json_decode($request->signers, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
        }

        Log::info('Dados decodificados', [
            'document_name' => $documentData['document_name'] ?? 'N/A',
            'signers_count' => count($signers)
        ]);

        // Usa arquivo temporário original do PHP (funciona sempre)
        $uploadedFile = $request->file('pdf_file');
        $filePath = $uploadedFile->getRealPath();
        
        Log::info('Arquivo temporário', [
            'path' => $filePath,
            'size' => filesize($filePath),
            'exists' => file_exists($filePath)
        ]);

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception('Arquivo temporário não está acessível');
        }

        // Configura documento para Autentique
        $autentiqueDocumentData = [
            'name' => $documentData['document_name'] ?? 'Documento para Assinatura',
            'message' => 'Documento para assinatura digital - ' . ($documentData['nome_associado'] ?? 'Cliente'),
            'refusable' => true,
            'sortable' => false,
            'qualified' => false,  // Impede autor automático como signatário
            'ignore_cpf' => true,  // Desabilita verificação CPF
            'reminder' => 'WEEKLY',
            'new_signature_style' => true,
            'show_audit_page' => true,
            'scrolling_required' => true,
            'locale' => [
                'country' => 'BR',
                'language' => 'pt-BR',
                'timezone' => 'America/Sao_Paulo',
                'date_format' => 'DD_MM_YYYY'
            ]
        ];

        // Processa signatários (email, WhatsApp e SMS)
        $autentiqueSigners = [];
        foreach ($signers as $index => $signer) {
            $signerData = [
                'action' => $signer['action'] ?? 'SIGN'
            ];

            // Verifica se é email, WhatsApp ou SMS
            if (!empty($signer['email'])) {
                // Signatário por email
                $signerData['email'] = $signer['email'];
                
                Log::info("Signatário por email configurado", [
                    'email' => $signer['email'],
                    'index' => $index
                ]);
                
            } elseif (!empty($signer['phone'])) {
                // Signatário por WhatsApp ou SMS
                $signerData['phone'] = $signer['phone'];
                
                if (!empty($signer['name'])) {
                    $signerData['name'] = $signer['name'];
                }
                
                if (!empty($signer['delivery_method'])) {
                    $signerData['delivery_method'] = $signer['delivery_method'];
                }
                
                Log::info("Signatário por telefone configurado", [
                    'phone' => $signer['phone'],
                    'delivery_method' => $signer['delivery_method'] ?? 'SMS',
                    'name' => $signer['name'] ?? 'N/A',
                    'index' => $index
                ]);
                
            } else {
                Log::warning("Signatário ignorado - sem email nem telefone", ['index' => $index]);
                continue;
            }

            // Adiciona CPF se fornecido
            if (!empty($documentData['cpf_cnpj'])) {
                $cpf = preg_replace('/[^0-9]/', '', $documentData['cpf_cnpj']);
                if (strlen($cpf) === 11) {
                    $signerData['configs'] = ['cpf' => $cpf];
                }
            }

            // Posições de assinatura
            $signerData['positions'] = [
                [
                    'x' => '50.0',
                    'y' => (90 - ($index * 5)) . '.0',
                    'z' => 1,
                    'element' => 'SIGNATURE'
                ]
            ];

            $autentiqueSigners[] = $signerData;
        }

        if (empty($autentiqueSigners)) {
            throw new \Exception('Pelo menos um signatário deve ser informado (email, WhatsApp ou SMS)');
        }

        // Determina modo sandbox
        $sandbox = env('AUTENTIQUE_SANDBOX', false) || 
                   $request->get('sandbox', false) ||
                   str_contains(strtolower($autentiqueDocumentData['name']), 'teste');

        Log::info('Criando documento na Autentique', [
            'document_name' => $autentiqueDocumentData['name'],
            'signers_count' => count($autentiqueSigners),
            'file_size' => filesize($filePath),
            'sandbox' => $sandbox
        ]);

        // Chama a API usando o método simples
        $result = $this->autentiqueService->createSimpleDocument(
            $autentiqueDocumentData,
            $autentiqueSigners,
            $filePath,
            $sandbox
        );

        if (!isset($result['createDocument'])) {
            throw new \Exception('Resposta inválida da API Autentique');
        }

        $document = $result['createDocument'];

        Log::info('Documento criado com sucesso!', [
            'document_id' => $document['id'],
            'sandbox' => $document['sandbox'] ?? $sandbox
        ]);

        // Salva documento no banco local
        $localDocument = Document::create([
            'autentique_id' => $document['id'],
            'name' => $document['name'],
            'status' => Document::STATUS_PENDING,
            'is_sandbox' => $document['sandbox'] ?? $sandbox,
            'document_data' => $documentData,
            'signers' => $signers,
            'autentique_response' => $document,
            'total_signers' => count($document['signatures']),
            'signed_count' => 0,
            'rejected_count' => 0,
            'autentique_created_at' => $document['created_at']
        ]);

        Log::info('Documento salvo no banco local', [
            'local_id' => $localDocument->id,
            'autentique_id' => $localDocument->autentique_id
        ]);

        // Monta resposta de sucesso
        $response = [
            'success' => true,
            'message' => 'Documento criado e enviado para assinatura com sucesso!',
            'document' => $document,
            'local_document_id' => $localDocument->id,
            'sandbox' => $document['sandbox'] ?? $sandbox,
            'summary' => [
                'document_id' => $document['id'],
                'document_name' => $document['name'],
                'total_signers' => count($document['signatures']),
                'created_at' => $document['created_at'],
                'is_sandbox' => $document['sandbox'] ?? $sandbox
            ],
            'signers_info' => []
        ];

        // Adiciona informações dos signatários
        foreach ($document['signatures'] as $signature) {
            $signerInfo = [
                'public_id' => $signature['public_id'],
                'name' => $signature['name'] ?? 'Signatário',
                'email' => $signature['email'] ?? null,
                'action' => $signature['action']['name'] ?? 'SIGN',
                'created_at' => $signature['created_at']
            ];

            if (isset($signature['link']['short_link'])) {
                $signerInfo['signature_link'] = $signature['link']['short_link'];
            }

            $response['signers_info'][] = $signerInfo;
        }

        return response()->json($response);

    } catch (\Exception $e) {
        Log::error('Erro ao criar documento', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return response()->json([
            'error' => $e->getMessage(),
            'details' => 'Verifique os logs para mais detalhes',
            'timestamp' => now()
        ], 500);
    }
}

    /**
     * Teste detalhado da API com logs
     */
    public function test(): JsonResponse
    {
        Log::info('=== INÍCIO TESTE API ===');
        
        try {
            // Verifica configurações básicas
            $tokenConfigured = !empty(env('AUTENTIQUE_API_TOKEN'));
            $tokenLength = strlen(env('AUTENTIQUE_API_TOKEN') ?? '');
            $apiUrl = env('AUTENTIQUE_API_URL', 'https://api.autentique.com.br/v2/graphql');
            
            Log::info('Configurações verificadas', [
                'token_configured' => $tokenConfigured,
                'token_length' => $tokenLength,
                'api_url' => $apiUrl
            ]);

            // Garante diretório temp
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
                Log::info('Diretório temp criado', ['path' => $tempDir]);
            }

            // Teste de conexão
            Log::info('Testando conexão...');
            $tokenTest = $this->autentiqueService->testConnection();
            
            Log::info('Resultado do teste de conexão', $tokenTest);

            $response = [
                'status' => $tokenTest['success'] ? 'OK' : 'ERROR',
                'message' => 'Teste da API Autentique',
                'token_configured' => $tokenConfigured,
                'token_length' => $tokenLength,
                'api_url' => $apiUrl,
                'sandbox_mode' => env('AUTENTIQUE_SANDBOX', false),
                'token_test' => $tokenTest,
                'environment_check' => [
                    'php_version' => phpversion(),
                    'curl_enabled' => extension_loaded('curl'),
                    'json_enabled' => extension_loaded('json'),
                    'temp_dir_exists' => is_dir($tempDir),
                    'temp_dir_writable' => is_writable($tempDir),
                    'storage_path' => storage_path(),
                    'current_time' => now()
                ]
            ];
            
            // Informações adicionais se conectou
            if ($tokenTest['success'] && isset($tokenTest['data']['documents'])) {
                $response['api_info'] = [
                    'documents_query_works' => true,
                    'total_documents' => $tokenTest['data']['documents']['total'] ?? 'N/A'
                ];
            }
            
            Log::info('=== FIM TESTE API - SUCESSO ===', ['response_keys' => array_keys($response)]);
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('=== FIM TESTE API - ERRO ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Erro no teste da API',
                'token_configured' => !empty(env('AUTENTIQUE_API_TOKEN')),
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Teste sandbox com logs detalhados
     */
    public function testSandbox(): JsonResponse
    {
        Log::info('=== INÍCIO TESTE SANDBOX ===');
        
        try {
            // Criar PDF de teste
            $testPdfPath = storage_path('app/temp/teste_sandbox_' . time() . '.pdf');
            
            // PDF mínimo válido
            $testPdfContent = '%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</ProcSet[/PDF/Text]/Font<</F1<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>>>>>endobj
4 0 obj<</Length 55>>stream
BT /F1 12 Tf 100 700 Td (Documento de Teste Sandbox) Tj ET
endstream endobj
xref 0 5
0000000000 65535 f 
0000000015 00000 n 
0000000068 00000 n 
0000000125 00000 n 
0000000331 00000 n 
trailer<</Size 5/Root 1 0 R>>
startxref 425
%%EOF';

            // Garante que o diretório existe
            $tempDir = dirname($testPdfPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            file_put_contents($testPdfPath, $testPdfContent);
            
            Log::info('PDF de teste criado', [
                'path' => $testPdfPath,
                'size' => filesize($testPdfPath),
                'exists' => file_exists($testPdfPath)
            ]);

            $documentData = [
                'name' => 'Teste Sandbox - ' . date('d/m/Y H:i:s'),
                'message' => 'Este é um documento de teste em modo sandbox',
                'refusable' => true,
                'new_signature_style' => true
            ];

            $signers = [
                [
                    'email' => 'teste@exemplo.com',
                    'action' => 'SIGN'
                ]
            ];

            Log::info('Criando documento sandbox...', [
                'document_data' => $documentData,
                'signers' => $signers
            ]);

            $result = $this->autentiqueService->createDocument(
                $documentData,
                $signers,
                $testPdfPath,
                true // Sandbox = true
            );

            Log::info('Documento sandbox criado', [
                'result_keys' => array_keys($result ?? []),
                'document_id' => $result['createDocument']['id'] ?? 'unknown'
            ]);

            // Remove arquivo de teste
            if (file_exists($testPdfPath)) {
                unlink($testPdfPath);
                Log::info('Arquivo de teste removido');
            }

            Log::info('=== FIM TESTE SANDBOX - SUCESSO ===');

            return response()->json([
                'success' => true,
                'message' => 'Documento de teste criado com sucesso em modo sandbox!',
                'document' => $result['createDocument'],
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('=== FIM TESTE SANDBOX - ERRO ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Remove arquivo em caso de erro
            if (isset($testPdfPath) && file_exists($testPdfPath)) {
                unlink($testPdfPath);
                Log::info('Arquivo de teste removido após erro');
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    // Outros métodos permanecem iguais...
    public function getDocument($documentId): JsonResponse
    {
        try {
            $document = $this->autentiqueService->getDocument($documentId);
            return response()->json(['success' => true, 'document' => $document['document']]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar documento:', ['document_id' => $documentId, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao buscar documento: ' . $e->getMessage()], 500);
        }
    }

    public function listDocuments(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 60), 100);
        $page = max($request->get('page', 1), 1);
        $showSandbox = $request->get('show_sandbox', false);

        try {
            $documents = $this->autentiqueService->listDocuments($limit, $page, $showSandbox);
            return response()->json([
                'success' => true,
                'documents' => $documents['documents'],
                'pagination' => ['current_page' => $page, 'limit' => $limit, 'total' => $documents['documents']['total'] ?? 0]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar documentos:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao listar documentos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Webhook para receber notificações da Autentique
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('=== WEBHOOK AUTENTIQUE RECEBIDO ===', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'timestamp' => now()
        ]);

        try {
            $payload = $request->all();
            $rawPayload = $request->getContent();
            
            // Verificação opcional de assinatura HMAC (descomente se configurar)
            // if (!$this->verifyWebhookSignature($request->headers->all(), $rawPayload)) {
            //     Log::warning('Webhook com assinatura inválida');
            //     return response()->json(['error' => 'Invalid signature'], 401);
            // }

            // Verifica estrutura do payload
            if (!isset($payload['event'])) {
                Log::warning('Webhook sem campo event');
                return response()->json(['error' => 'Invalid payload - missing event'], 400);
            }

            $event = $payload['event'];
            $eventType = $event['type'] ?? 'unknown';
            $eventData = $event['data'] ?? [];

            Log::info('Processando evento', [
                'event_type' => $eventType,
                'event_id' => $event['id'] ?? 'N/A'
            ]);

            // Processa evento baseado no tipo
            $this->processWebhookEvent($eventType, $eventData, $event);

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            Log::error('Erro no webhook', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Processa eventos do webhook baseado no tipo
     */
    private function processWebhookEvent(string $eventType, array $eventData, array $fullEvent)
    {
        switch ($eventType) {
            // EVENTOS DE DOCUMENTO
            case 'document.created':
                $this->handleDocumentCreated($eventData, $fullEvent);
                break;
                
            case 'document.updated':
                $this->handleDocumentUpdated($eventData, $fullEvent);
                break;
                
            case 'document.finished':
                $this->handleDocumentFinished($eventData, $fullEvent);
                break;
                
            case 'document.deleted':
                $this->handleDocumentDeleted($eventData, $fullEvent);
                break;

            // EVENTOS DE ASSINATURA
            case 'signature.created':
                $this->handleSignatureCreated($eventData, $fullEvent);
                break;
                
            case 'signature.viewed':
                $this->handleSignatureViewed($eventData, $fullEvent);
                break;
                
            case 'signature.accepted':
                $this->handleSignatureAccepted($eventData, $fullEvent);
                break;
                
            case 'signature.rejected':
                $this->handleSignatureRejected($eventData, $fullEvent);
                break;
                
            case 'signature.updated':
            case 'signature.deleted':
            case 'signature.biometric_approved':
            case 'signature.biometric_unapproved':
            case 'signature.biometric_rejected':
                $this->handleSignatureEvent($eventType, $eventData, $fullEvent);
                break;

            // EVENTOS DE MEMBRO
            case 'member.created':
            case 'member.deleted':
                $this->handleMemberEvent($eventType, $eventData, $fullEvent);
                break;
                
            default:
                Log::warning('Tipo de evento desconhecido', ['event_type' => $eventType]);
        }
    }

    /**
     * Handle document.updated e document.finished
     */
    private function handleDocumentUpdated(array $eventData, array $fullEvent)
    {
        $documentId = $eventData['id'] ?? null;
        if (!$documentId) {
            Log::warning('Evento document.updated sem ID');
            return;
        }

        $localDocument = Document::where('autentique_id', $documentId)->first();
        if (!$localDocument) {
            Log::warning('Documento não encontrado localmente', ['document_id' => $documentId]);
            return;
        }

        $this->updateDocumentFromEventData($localDocument, $eventData, $fullEvent);
    }

    /**
     * Handle document.finished - documento totalmente assinado
     */
    private function handleDocumentFinished(array $eventData, array $fullEvent)
    {
        $documentId = $eventData['id'] ?? null;
        if (!$documentId) return;

        $localDocument = Document::where('autentique_id', $documentId)->first();
        if (!$localDocument) return;

        $oldStatus = $localDocument->status;
        $this->updateDocumentFromEventData($localDocument, $eventData, $fullEvent);

        // Notifica se status mudou para assinado
        if ($oldStatus !== Document::STATUS_SIGNED && $localDocument->status === Document::STATUS_SIGNED) {
            $this->sendSignedNotification($localDocument);
        }

        Log::info('🎉 DOCUMENTO TOTALMENTE ASSINADO!', [
            'document_id' => $localDocument->autentique_id,
            'document_name' => $localDocument->name,
            'old_status' => $oldStatus,
            'new_status' => $localDocument->status
        ]);
    }

    /**
     * Handle signature.accepted - signatário assinou
     */
    private function handleSignatureAccepted(array $eventData, array $fullEvent)
    {
        $documentId = $eventData['document'] ?? null;
        $signerEmail = $eventData['user']['email'] ?? null;
        $signerName = $eventData['user']['name'] ?? 'Signatário';

        if (!$documentId) return;

        $localDocument = Document::where('autentique_id', $documentId)->first();
        if (!$localDocument) {
            Log::warning('Documento da assinatura não encontrado', ['document_id' => $documentId]);
            return;
        }

        // Atualiza documento via API para ter dados atuais
        $this->syncDocumentFromAPI($localDocument);

        Log::info('✍️ ASSINATURA ACEITA!', [
            'document_id' => $documentId,
            'document_name' => $localDocument->name,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'signed_at' => $eventData['signed'] ?? now(),
            'progress' => $localDocument->signing_progress . '%'
        ]);

        // Envia notificação específica de assinatura
        $this->sendSignatureAcceptedNotification($localDocument, $signerName, $signerEmail);
    }

    /**
     * Handle signature.rejected - signatário recusou
     */
    private function handleSignatureRejected(array $eventData, array $fullEvent)
    {
        $documentId = $eventData['document'] ?? null;
        $signerEmail = $eventData['user']['email'] ?? null;
        $signerName = $eventData['user']['name'] ?? 'Signatário';

        if (!$documentId) return;

        $localDocument = Document::where('autentique_id', $documentId)->first();
        if (!$localDocument) return;

        $this->syncDocumentFromAPI($localDocument);

        Log::warning('❌ ASSINATURA RECUSADA!', [
            'document_id' => $documentId,
            'document_name' => $localDocument->name,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'rejected_at' => $eventData['rejected'] ?? now()
        ]);

        $this->sendSignatureRejectedNotification($localDocument, $signerName, $signerEmail);
    }

    /**
     * Handle signature.viewed - signatário visualizou
     */
    private function handleSignatureViewed(array $eventData, array $fullEvent)
    {
        $documentId = $eventData['document'] ?? null;
        $signerName = $eventData['user']['name'] ?? 'Signatário';

        Log::info('👀 DOCUMENTO VISUALIZADO!', [
            'document_id' => $documentId,
            'signer_name' => $signerName,
            'viewed_at' => $eventData['viewed'] ?? now()
        ]);
    }

    /**
     * Handle outros eventos de assinatura
     */
    private function handleSignatureEvent(string $eventType, array $eventData, array $fullEvent)
    {
        Log::info('📝 Evento de assinatura', [
            'event_type' => $eventType,
            'document_id' => $eventData['document'] ?? 'N/A',
            'signer' => $eventData['user']['name'] ?? 'N/A'
        ]);
    }

    /**
     * Handle eventos de documento criado/deletado
     */
    private function handleDocumentCreated(array $eventData, array $fullEvent)
    {
        Log::info('📄 Documento criado via webhook', ['document_id' => $eventData['id'] ?? 'N/A']);
    }

    private function handleDocumentDeleted(array $eventData, array $fullEvent)
    {
        Log::info('🗑️ Documento deletado via webhook', ['document_id' => $eventData['id'] ?? 'N/A']);
    }

    /**
     * Handle eventos de membro
     */
    private function handleMemberEvent(string $eventType, array $eventData, array $fullEvent)
    {
        Log::info('👥 Evento de membro', [
            'event_type' => $eventType,
            'user' => $eventData['user']['name'] ?? 'N/A'
        ]);
    }

    /**
     * Atualiza documento local com dados do evento
     */
    private function updateDocumentFromEventData(Document $document, array $eventData, array $fullEvent)
    {
        // Atualiza contadores se houver assinaturas
        if (isset($eventData['signatures'])) {
            $signatures = $eventData['signatures'];
            $signedCount = 0;
            $rejectedCount = 0;

            foreach ($signatures as $signature) {
                if (isset($signature['signed']) && !empty($signature['signed'])) {
                    $signedCount++;
                }
                if (isset($signature['rejected']) && !empty($signature['rejected'])) {
                    $rejectedCount++;
                }
            }

            $document->signed_count = $signedCount;
            $document->rejected_count = $rejectedCount;
            $document->total_signers = count($signatures);
        }

        // Atualiza campos do documento
        if (isset($eventData['name'])) {
            $document->name = $eventData['name'];
        }

        // Atualiza resposta completa
        $document->autentique_response = $eventData;
        $document->last_checked_at = now();

        // Atualiza status
        $document->updateStatus();
        $document->save();
    }

    /**
     * Sincroniza documento com API da Autentique para dados atuais
     */
    private function syncDocumentFromAPI(Document $document)
    {
        try {
            $autentiqueData = $this->autentiqueService->getDocument($document->autentique_id);
            if (isset($autentiqueData['document'])) {
                $this->updateDocumentFromEventData($document, $autentiqueData['document'], []);
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao sincronizar documento da API', [
                'document_id' => $document->autentique_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Envia notificação quando documento é totalmente assinado
     */
    private function sendSignedNotification(Document $document)
    {
        Log::info('🎉 DOCUMENTO TOTALMENTE ASSINADO!', [
            'document_id' => $document->autentique_id,
            'document_name' => $document->name,
            'signed_at' => now(),
            'signers_emails' => $document->getSignerEmails(),
            'signers_phones' => $document->getSignerPhones(),
            'associado' => $document->document_data['nome_associado'] ?? 'N/A'
        ]);

        // Aqui você pode implementar notificações específicas:
        $this->sendNotificationToAdmin($document, 'document_fully_signed');
        $this->sendNotificationToClient($document, 'your_document_ready');
    }

    /**
     * Envia notificação quando uma assinatura é aceita
     */
    private function sendSignatureAcceptedNotification(Document $document, string $signerName, ?string $signerEmail)
    {
        Log::info('✅ NOTIFICAÇÃO: Assinatura aceita', [
            'document_name' => $document->name,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'progress' => $document->signing_progress . '%',
            'remaining' => $document->total_signers - $document->signed_count . ' restantes'
        ]);

        // Notifica admin sobre progresso
        $this->sendNotificationToAdmin($document, 'signature_accepted', [
            'signer_name' => $signerName,
            'progress' => $document->signing_progress
        ]);
    }

    /**
     * Envia notificação quando uma assinatura é rejeitada
     */
    private function sendSignatureRejectedNotification(Document $document, string $signerName, ?string $signerEmail)
    {
        Log::warning('❌ NOTIFICAÇÃO: Assinatura rejeitada', [
            'document_name' => $document->name,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'status' => $document->status
        ]);

        // Notifica admin sobre rejeição
        $this->sendNotificationToAdmin($document, 'signature_rejected', [
            'signer_name' => $signerName,
            'document_name' => $document->name
        ]);
    }

    /**
     * Envia notificação para admin/sistema
     */
    private function sendNotificationToAdmin(Document $document, string $type, array $extra = [])
    {
        $message = match($type) {
            'document_fully_signed' => "✅ Documento '{$document->name}' foi totalmente assinado!",
            'signature_accepted' => "✍️ {$extra['signer_name']} assinou '{$document->name}' - Progresso: {$extra['progress']}%",
            'signature_rejected' => "❌ {$extra['signer_name']} rejeitou '{$document->name}'",
            default => "📄 Evento no documento '{$document->name}'"
        };

        // Aqui implementar envio real:
        // - Email
        // - Slack
        // - WhatsApp Business API
        // - SMS
        // - Push notification
        // - Webhook para outros sistemas
        
        Log::info('📧 NOTIFICAÇÃO ADMIN', [
            'type' => $type,
            'message' => $message,
            'document_id' => $document->autentique_id,
            'extra_data' => $extra
        ]);
    }

    /**
     * Envia notificação para cliente
     */
    private function sendNotificationToClient(Document $document, string $type, array $extra = [])
    {
        $clientEmails = $document->getSignerEmails();
        $clientPhones = $document->getSignerPhones();
        $associado = $document->document_data['nome_associado'] ?? 'Cliente';

        $message = match($type) {
            'your_document_ready' => "🎉 Olá {$associado}! Seu documento '{$document->name}' foi assinado por todos os signatários e está pronto!",
            default => "📄 Atualização sobre seu documento '{$document->name}'"
        };

        Log::info('📱 NOTIFICAÇÃO CLIENTE', [
            'type' => $type,
            'message' => $message,
            'client_emails' => $clientEmails,
            'client_phones' => $clientPhones,
            'associado' => $associado
        ]);

        // Implementar envios reais aqui
    }

    /**
     * Verifica assinatura HMAC do webhook (opcional)
     */
    private function verifyWebhookSignature(array $headers, string $payload): bool
    {
        $secret = env('AUTENTIQUE_WEBHOOK_SECRET');
        if (!$secret) {
            return true; // Se não configurado, aceita (desenvolvimento)
        }

        $signature = $headers['x-autentique-signature'] ?? null;
        if (!$signature) {
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Handle outros eventos de assinatura não implementados
     */
    private function handleSignatureCreated(array $eventData, array $fullEvent)
    {
        Log::info('📝 Nova assinatura criada', [
            'document_id' => $eventData['document'] ?? 'N/A',
            'signer' => $eventData['user']['name'] ?? 'N/A'
        ]);
    }

    /**
     * Dashboard - lista documentos locais
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status', 'all');
        $sandbox = $request->get('sandbox', 'all');
        
        $query = Document::query()->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($sandbox === 'yes') {
            $query->sandbox();
        } elseif ($sandbox === 'no') {
            $query->production();
        }

        $documents = $query->paginate(20);
        
        return view('documents.dashboard', compact('documents', 'status', 'sandbox'));
    }

    /**
     * Sincronizar documento específico com a Autentique
     */
    public function syncDocument($id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);
            
            // Busca dados atualizados na Autentique
            $autentiqueData = $this->autentiqueService->getDocument($document->autentique_id);
            
            if (isset($autentiqueData['document'])) {
                $this->updateDocumentFromWebhook($document, ['document' => $autentiqueData['document']]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Documento sincronizado com sucesso!',
                    'document' => $document->fresh()
                ]);
            }

            return response()->json(['error' => 'Documento não encontrado na Autentique'], 404);

        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar documento', [
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Erro ao sincronizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reenviar convites de assinatura
     */
    public function resendSignatures(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'public_ids' => 'required|array',
                'public_ids.*' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Dados inválidos: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            $publicIds = $request->input('public_ids');
            
            $result = $this->autentiqueService->resendSignatures($publicIds);
            
            Log::info('Convites reenviados', [
                'public_ids' => $publicIds,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Convites reenviados com sucesso!',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao reenviar convites', [
                'error' => $e->getMessage(),
                'public_ids' => $request->input('public_ids', [])
            ]);

            return response()->json(['error' => 'Erro ao reenviar convites: ' . $e->getMessage()], 500);
        }
    }
}