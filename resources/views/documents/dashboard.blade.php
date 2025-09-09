<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard de Documentos - {{ config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
        }
        
        .content {
            padding: 30px 40px;
        }
        
        .filters {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 14px;
        }
        
        .stat-pending .stat-value { color: #f59e0b; }
        .stat-signed .stat-value { color: #10b981; }
        .stat-rejected .stat-value { color: #ef4444; }
        .stat-partial .stat-value { color: #3b82f6; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f9fafb;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        
        .table tbody tr:hover {
            background: #f9fafb;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-signed { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fecaca; color: #991b1b; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        
        .progress-bar {
            width: 100px;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #10b981;
            transition: width 0.3s ease;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .sandbox-indicator {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .refresh-indicator {
            width: 12px;
            height: 12px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .document-info {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Dashboard de Documentos</h1>
                <p>Gerencie todos os documentos enviados para assinatura</p>
            </div>
            <div class="auto-refresh">
                <div class="refresh-indicator" id="refreshIndicator" style="display: none;"></div>
                <span id="refreshStatus">Atualizando...</span>
                <a href="/" class="btn btn-secondary">← Voltar</a>
            </div>
        </div>
        
        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" {{ $status == 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pendente</option>
                        <option value="signed" {{ $status == 'signed' ? 'selected' : '' }}>Assinado</option>
                        <option value="rejected" {{ $status == 'rejected' ? 'selected' : '' }}>Recusado</option>
                        <option value="partial" {{ $status == 'partial' ? 'selected' : '' }}>Parcial</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Ambiente</label>
                    <select name="sandbox" onchange="this.form.submit()">
                        <option value="all" {{ $sandbox == 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="yes" {{ $sandbox == 'yes' ? 'selected' : '' }}>Sandbox</option>
                        <option value="no" {{ $sandbox == 'no' ? 'selected' : '' }}>Produção</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🔄 Atualizar</button>
            </form>
            
            <!-- Estatísticas -->
            <div class="stats">
                <div class="stat-card stat-pending">
                    <div class="stat-value">{{ $documents->where('status', 'pending')->count() }}</div>
                    <div class="stat-label">Pendente</div>
                </div>
                <div class="stat-card stat-signed">
                    <div class="stat-value">{{ $documents->where('status', 'signed')->count() }}</div>
                    <div class="stat-label">Assinado</div>
                </div>
                <div class="stat-card stat-rejected">
                    <div class="stat-value">{{ $documents->where('status', 'rejected')->count() }}</div>
                    <div class="stat-label">Recusado</div>
                </div>
                <div class="stat-card stat-partial">
                    <div class="stat-value">{{ $documents->where('status', 'partial')->count() }}</div>
                    <div class="stat-label">Parcial</div>
                </div>
            </div>
            
            <!-- Tabela de Documentos -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Status</th>
                            <th>Progresso</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                        <tr>
                            <td>
                                <div style="font-weight: 600; margin-bottom: 4px;">
                                    {{ $document->name }}
                                </div>
                                <div class="document-info">
                                    ID: {{ $document->autentique_id }}
                                    @if($document->is_sandbox)
                                        <span class="sandbox-indicator">SANDBOX</span>
                                    @endif
                                </div>
                                <div class="document-info">
                                    Associado: {{ $document->document_data['nome_associado'] ?? 'N/A' }}
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $document->status }}">
                                    {{ $document->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: {{ $document->signing_progress }}%"></div>
                                </div>
                                <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                                    {{ $document->signed_count }}/{{ $document->total_signers }} assinados
                                </div>
                            </td>
                            <td>
                                <div>{{ $document->created_at->format('d/m/Y') }}</div>
                                <div style="font-size: 11px; color: #6b7280;">
                                    {{ $document->created_at->format('H:i') }}
                                </div>
                            </td>
                            <td>
                                <button onclick="syncDocument({{ $document->id }})" 
                                        class="btn btn-sm btn-primary" 
                                        id="sync-btn-{{ $document->id }}">
                                    🔄 Sync
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #6b7280;">
                                📄 Nenhum documento encontrado
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação -->
            @if($documents->hasPages())
            <div class="pagination">
                {{ $documents->appends(request()->query())->links() }}
            </div>
            @endif
        </div>
    </div>

    <script>
        // Auto-refresh a cada 30 segundos
        let autoRefreshInterval;
        let refreshCounter = 30;

        function startAutoRefresh() {
            const indicator = document.getElementById('refreshIndicator');
            const status = document.getElementById('refreshStatus');
            
            autoRefreshInterval = setInterval(() => {
                refreshCounter--;
                status.textContent = `Próxima atualização em ${refreshCounter}s`;
                
                if (refreshCounter <= 0) {
                    indicator.style.display = 'block';
                    status.textContent = 'Atualizando...';
                    window.location.reload();
                }
            }, 1000);
        }

        // Sincronizar documento específico
        async function syncDocument(documentId) {
            const btn = document.getElementById(`sync-btn-${documentId}`);
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '⏳ Sincronizando...';
            btn.disabled = true;
            
            try {
                const response = await fetch(`/api/documents/${documentId}/sync`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    btn.innerHTML = '✅ Atualizado';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    btn.innerHTML = '❌ Erro';
                    console.error('Erro:', result.error);
                }
            } catch (error) {
                btn.innerHTML = '❌ Erro';
                console.error('Erro ao sincronizar:', error);
            } finally {
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            }
        }

        // Inicia auto-refresh quando página carrega
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
        });

        // Para o auto-refresh quando usuário sair da página
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
    </script>
</body>
</html>