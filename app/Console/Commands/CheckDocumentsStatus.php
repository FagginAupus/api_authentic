<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Services\AutentiqueService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckDocumentsStatus extends Command
{
    protected $signature = 'documents:check-status {--force : Force check all documents}';
    protected $description = 'Verifica status de documentos pendentes na Autentique e envia notificações';

    private $autentiqueService;

    public function __construct(AutentiqueService $autentiqueService)
    {
        parent::__construct();
        $this->autentiqueService = $autentiqueService;
    }

    public function handle()
    {
        $this->info('🔄 Iniciando verificação de status dos documentos...');
        
        $force = $this->option('force');
        
        // Busca documentos que precisam ser verificados
        $query = Document::query();
        
        if (!$force) {
            // Apenas documentos pendentes ou parciais
            $query->whereIn('status', [Document::STATUS_PENDING, Document::STATUS_PARTIAL]);
        }
        
        // Documentos que não foram verificados nos últimos 5 minutos
        $query->where(function($q) {
            $q->whereNull('last_checked_at')
              ->orWhere('last_checked_at', '<', now()->subMinutes(5));
        });

        $documents = $query->get();
        
        if ($documents->isEmpty()) {
            $this->info('✅ Nenhum documento precisando verificação.');
            return;
        }

        $this->info("📄 Verificando {$documents->count()} documento(s)...");
        
        $updatedCount = 0;
        $signedCount = 0;
        $errorCount = 0;

        foreach ($documents as $document) {
            try {
                $this->line("Verificando: {$document->name} (ID: {$document->autentique_id})");
                
                $oldStatus = $document->status;
                $updated = $this->checkSingleDocument($document);
                
                if ($updated) {
                    $updatedCount++;
                    
                    // Se documento foi totalmente assinado
                    if ($oldStatus !== Document::STATUS_SIGNED && $document->status === Document::STATUS_SIGNED) {
                        $this->info("🎉 DOCUMENTO ASSINADO: {$document->name}");
                        $this->sendSignedNotification($document);
                        $signedCount++;
                    }
                    
                    $this->info("✅ Status atualizado: {$oldStatus} → {$document->status}");
                } else {
                    $this->line("→ Sem mudanças");
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("❌ Erro ao verificar {$document->name}: {$e->getMessage()}");
                
                Log::error('Erro no polling de documento', [
                    'document_id' => $document->autentique_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("📊 RESUMO:");
        $this->info("   Verificados: {$documents->count()}");
        $this->info("   Atualizados: {$updatedCount}");
        $this->info("   Assinados: {$signedCount}");
        
        if ($errorCount > 0) {
            $this->warn("   Erros: {$errorCount}");
        }

        Log::info('Polling executado', [
            'documents_checked' => $documents->count(),
            'updated' => $updatedCount,
            'signed' => $signedCount,
            'errors' => $errorCount
        ]);
    }

    private function checkSingleDocument(Document $document): bool
    {
        try {
            // Busca dados atualizados na Autentique
            $autentiqueData = $this->autentiqueService->getDocument($document->autentique_id);
            
            if (!isset($autentiqueData['document'])) {
                throw new \Exception('Documento não encontrado na Autentique');
            }

            $apiDocument = $autentiqueData['document'];
            
            // Conta assinaturas
            $signedCount = 0;
            $rejectedCount = 0;
            $totalSigners = 0;

            if (isset($apiDocument['signatures'])) {
                $totalSigners = count($apiDocument['signatures']);
                
                foreach ($apiDocument['signatures'] as $signature) {
                    if (isset($signature['signed']) && !empty($signature['signed'])) {
                        $signedCount++;
                    }
                    if (isset($signature['rejected']) && !empty($signature['rejected'])) {
                        $rejectedCount++;
                    }
                }
            }

            // Verifica se houve mudanças
            $hasChanges = (
                $document->signed_count !== $signedCount ||
                $document->rejected_count !== $rejectedCount ||
                $document->total_signers !== $totalSigners
            );

            // Atualiza dados
            $document->signed_count = $signedCount;
            $document->rejected_count = $rejectedCount;
            $document->total_signers = $totalSigners;
            $document->autentique_response = $apiDocument;
            $document->last_checked_at = now();
            
            // Atualiza status
            $document->updateStatus();
            $document->save();

            return $hasChanges;
            
        } catch (\Exception $e) {
            // Atualiza timestamp mesmo com erro para não ficar tentando constantemente
            $document->last_checked_at = now();
            $document->save();
            
            throw $e;
        }
    }

    private function sendSignedNotification(Document $document)
    {
        try {
            $this->info("📧 Enviando notificação por email...");
            
            $to = 'smart@aupusenergia.com.br';
            $subject = "🎉 Documento Totalmente Assinado - {$document->name}";
            
            $associado = $document->document_data['nome_associado'] ?? 'Cliente';
            $cpf = $document->document_data['cpf_cnpj'] ?? 'N/A';
            $signers = $document->getSignerEmails();
            $phones = $document->getSignerPhones();
            
            $message = "
Olá!

O documento '{$document->name}' foi totalmente assinado! 🎉

DETALHES DO DOCUMENTO:
• Nome: {$document->name}
• Associado: {$associado}
• CPF/CNPJ: {$cpf}
• ID Autentique: {$document->autentique_id}
• Assinado em: " . now()->format('d/m/Y H:i:s') . "

SIGNATÁRIOS:
• Total: {$document->total_signers}
• Assinaram: {$document->signed_count}
• Emails: " . implode(', ', $signers) . "
• Telefones: " . implode(', ', $phones) . "

STATUS:
• Progresso: {$document->signing_progress}%
• Status: {$document->status_label}
• Ambiente: " . ($document->is_sandbox ? 'Sandbox' : 'Produção') . "

O documento assinado está disponível na Autentique.

---
Sistema de Assinatura Digital
Aupus Energia
            ";

            // Usa mail() simples por enquanto
            $headers = [
                'From: sistema@aupusenergia.com.br',
                'Reply-To: smart@aupusenergia.com.br',
                'Content-Type: text/plain; charset=UTF-8'
            ];

            if (mail($to, $subject, trim($message), implode("\r\n", $headers))) {
                $this->info("✅ Email enviado com sucesso para {$to}");
                
                Log::info('Email de notificação enviado', [
                    'document_id' => $document->autentique_id,
                    'document_name' => $document->name,
                    'to' => $to,
                    'associado' => $associado
                ]);
            } else {
                throw new \Exception('Falha ao enviar email');
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar email: {$e->getMessage()}");
            
            Log::error('Erro ao enviar email de notificação', [
                'document_id' => $document->autentique_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}