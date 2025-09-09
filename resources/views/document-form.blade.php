<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Sistema de Assinatura Digital</title>
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
            max-width: 1000px;
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
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .status-bar {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-ok { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .required {
            color: #ef4444;
        }
        
        input, select, textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input:invalid {
            border-color: #ef4444;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .signer-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }
        
        .signer-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .signer-card h4 {
            margin-bottom: 15px;
            color: #1f2937;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            margin-top: 30px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .result {
            display: none;
            margin-top: 30px;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid;
        }
        
        .result.success {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .result.error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .document-info {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .signers-list {
            margin-top: 15px;
        }
        
        .signer-item {
            background: rgba(255,255,255,0.5);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .signature-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            font-size: 12px;
            padding: 4px 8px;
            background: rgba(37, 99, 235, 0.1);
            border-radius: 4px;
        }
        
        .signature-link:hover {
            background: rgba(37, 99, 235, 0.2);
        }
        
        .test-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .sandbox-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .small {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .test-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sistema de Assinatura Digital</h1>
            <p>Integração completa com API da Autentique - WhatsApp, Email e SMS</p>
        </div>
        
        <div class="content">
            <!-- Status Bar -->
            <div class="status-bar" id="statusBar">
                <div class="status-icon status-warning" id="statusIcon">⚠</div>
                <div id="statusText">Verificando conexão com a API...</div>
            </div>
            
            <!-- Test Buttons -->
            <div class="test-buttons">
                <a href="/dashboard" class="btn btn-primary">
                    📋 Dashboard de Documentos
                </a>
                <button type="button" class="btn btn-secondary" onclick="testAPI()">
                    🔍 Testar API
                </button>
                <button type="button" class="btn btn-secondary" onclick="testSandbox()">
                    🧪 Testar Sandbox
                </button>
                <button type="button" class="btn btn-secondary" onclick="checkPDFTemplate()">
                    📄 Verificar PDF Template
                </button>
            </div>
            
            <!-- Sandbox Notice -->
            <div class="sandbox-notice">
                <strong>Modo de Desenvolvimento:</strong> Os documentos serão criados em modo sandbox por padrão (não consomem créditos e são temporários).
            </div>
            
            <form id="documentForm">
                <!-- Document Data Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        📄 Dados do Documento
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome_associado">
                                Nome do Associado <span class="required">*</span>
                            </label>
                            <input type="text" id="nome_associado" name="nome_associado" 
                                   value="João da Silva Santos" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF/CNPJ</label>
                            <input type="text" id="cpf_cnpj" name="cpf_cnpj" value="123.456.789-10">
                            <div class="small">Será usado para validação de assinatura se fornecido</div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="endereco">
                                Endereço <span class="required">*</span>
                            </label>
                            <input type="text" id="endereco" name="endereco" 
                                   value="Rua das Palmeiras, 123 - Jardim das Flores" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="forma_pagamento">Forma de Pagamento</label>
                            <select id="forma_pagamento" name="forma_pagamento">
                                <option value="BOLETO" selected>Boleto</option>
                                <option value="PIX">PIX</option>
                                <option value="CARTAO">Cartão</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="economia">Economia Esperada</label>
                            <input type="text" id="economia" name="economia" value="20%">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_unidade">
                                Número da Unidade <span class="required">*</span>
                            </label>
                            <input type="text" id="numero_unidade" name="numero_unidade" 
                                   value="UC555666777" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="logradouro">Logradouro Completo</label>
                            <textarea id="logradouro" name="logradouro">Rua das Palmeiras, 123
Jardim das Flores
São Paulo - SP
CEP: 05678-901</textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Signers Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        ✍️ Signatários
                    </h3>
                    
                    <div class="signer-card">
                        <h4>📧 Assinatura por Email</h4>
                        <div class="form-group">
                            <label for="signatario_email">Email do Signatário</label>
                            <input type="email" id="signatario_email" name="signatario_email" 
                                   placeholder="cliente@empresa.com">
                            <div class="small">Se preenchido, o link de assinatura será enviado por email</div>
                        </div>
                    </div>
                    
                    <div class="signer-card">
                        <h4>📱 Assinatura por WhatsApp</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="signatario_whatsapp">WhatsApp do Signatário</label>
                                <input type="tel" id="signatario_whatsapp" name="signatario_whatsapp" 
                                       placeholder="+5511999999999">
                                <div class="small">Formato: +55 11 99999-9999</div>
                            </div>
                            <div class="form-group">
                                <label for="nome_signatario_whatsapp">Nome (Obrigatório para WhatsApp)</label>
                                <input type="text" id="nome_signatario_whatsapp" name="nome_signatario_whatsapp" 
                                       placeholder="Nome completo do signatário">
                            </div>
                        </div>
                    </div>
                    
                    <div class="signer-card">
                        <h4>📞 Assinatura por SMS</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="signatario_sms">Telefone do Signatário</label>
                                <input type="tel" id="signatario_sms" name="signatario_sms" 
                                       placeholder="+5511999999999">
                                <div class="small">O link será enviado por SMS</div>
                            </div>
                            <div class="form-group">
                                <label for="nome_signatario_sms">Nome do Signatário</label>
                                <input type="text" id="nome_signatario_sms" name="nome_signatario_sms" 
                                       placeholder="Nome completo do signatário">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Options -->
                <div class="form-section">
                    <h3 class="section-title">
                        ⚙️ Opções do Documento
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="refusable" checked> 
                                Permitir recusa de assinatura
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="sortable"> 
                                Assinatura sequencial obrigatória
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="scrolling_required" checked> 
                                Exigir visualização completa
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="sandbox" checked> 
                                Modo Sandbox (recomendado para testes)
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary submit-btn" id="submitBtn">
                    🚀 Gerar e Enviar Documento para Assinatura
                </button>
            </form>
            
            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <h3>Processando documento...</h3>
                <p>Gerando PDF, criando documento na Autentique e enviando convites...</p>
            </div>
            
            <!-- Result -->
            <div class="result" id="result"></div>
        </div>
    </div>

    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <script>
        const { PDFDocument } = PDFLib;

        // Status check on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAPIStatus();
        });

        async function checkAPIStatus() {
            try {
                const response = await fetch('/test');
                const result = await response.json();
                
                const statusIcon = document.getElementById('statusIcon');
                const statusText = document.getElementById('statusText');
                const statusBar = document.getElementById('statusBar');
                
                if (result.status === 'OK') {
                    statusIcon.className = 'status-icon status-ok';
                    statusIcon.textContent = '✓';
                    statusText.innerHTML = `
                        <strong>API Conectada!</strong> 
                        Token válido • ${result.account_info?.user_email || 'Usuário autenticado'}
                        ${result.sandbox_mode ? ' • Modo Sandbox Ativo' : ''}
                    `;
                } else {
                    statusIcon.className = 'status-icon status-error';
                    statusIcon.textContent = '✗';
                    statusText.innerHTML = `<strong>Erro na API:</strong> ${result.error || 'Token inválido'}`;
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }

        async function testAPI() {
            try {
                const response = await fetch('/test');
                const result = await response.json();
                
                alert(`Teste da API:\n\nStatus: ${result.status}\nToken: ${result.token_configured ? 'Configurado' : 'Não configurado'}\n\n${JSON.stringify(result, null, 2)}`);
            } catch (error) {
                alert('Erro ao testar API: ' + error.message);
            }
        }

        async function testSandbox() {
            try {
                const response = await fetch('/test-sandbox');
                const result = await response.json();
                
                if (result.success) {
                    alert(`Documento de teste criado com sucesso!\n\nID: ${result.document.id}\nNome: ${result.document.name}\nSandbox: ${result.document.sandbox}`);
                } else {
                    alert('Erro no teste sandbox: ' + result.error);
                }
            } catch (error) {
                alert('Erro ao testar sandbox: ' + error.message);
            }
        }

        async function checkPDFTemplate() {
            try {
                const response = await fetch('/PROCURACAO_E_TERMO_DE_ADESAO.pdf');
                if (response.ok) {
                    alert('✅ PDF template encontrado e acessível!');
                } else {
                    alert('❌ PDF template não encontrado. Certifique-se de que o arquivo PROCURACAO_E_TERMO_DE_ADESAO.pdf está na pasta public/');
                }
            } catch (error) {
                alert('Erro ao verificar PDF: ' + error.message);
            }
        }

        async function preencherPDFParaEnvio(dados) {
            try {
                const response = await fetch('/PROCURACAO_E_TERMO_DE_ADESAO.pdf');
                if (!response.ok) {
                    throw new Error('PDF template não encontrado. Verifique se está na pasta public/');
                }
                const pdfBytes = await response.arrayBuffer();
                
                const pdfDoc = await PDFDocument.load(pdfBytes);
                const form = pdfDoc.getForm();
                
                const mapeamento = {
                    "text_1semi": dados.nomeAssociado,
                    "text_2jyxc": dados.endereco,
                    "text_3qmpl": dados.formaPagamento,
                    "text_4nirf": dados.cpf,
                    "text_5igbr": dados.representanteLegal || '',
                    "textarea_6pyef": dados.numeroUnidade,
                    "textarea_7wrsb": dados.logradouro,
                    "text_15goku": dados.dia,
                    "text_16bzyc": dados.mes,
                    "text_13gmsz": dados.economia
                };
                
                Object.keys(mapeamento).forEach(nomeCampo => {
                    try {
                        const field = form.getField(nomeCampo);
                        const valor = mapeamento[nomeCampo];
                        
                        if (valor) {
                            field.setText(valor.toString());
                            
                            if (nomeCampo === "textarea_6pyef") {
                                field.setFontSize(13);
                            }
                        }
                    } catch (error) {
                        console.log(`Campo ${nomeCampo} não encontrado: ${error.message}`);
                    }
                });
                
                const pdfBytesPreenchido = await pdfDoc.save();
                return pdfBytesPreenchido;
                
            } catch (error) {
                console.error('Erro ao preencher PDF:', error.message);
                throw error;
            }
        }

        async function enviarDocumentoParaAssinatura(dadosAssociado, signatarios) {
            try {
                const pdfBytes = await preencherPDFParaEnvio(dadosAssociado);
                const pdfBlob = new Blob([pdfBytes], { type: 'application/pdf' });
                
                const formData = new FormData();
                formData.append('pdf_file', pdfBlob, 'procuracao_preenchida.pdf');
                formData.append('document_data', JSON.stringify({
                    nome_associado: dadosAssociado.nomeAssociado,
                    endereco: dadosAssociado.endereco,
                    forma_pagamento: dadosAssociado.formaPagamento,
                    cpf_cnpj: dadosAssociado.cpf,
                    numero_unidade: dadosAssociado.numeroUnidade,
                    logradouro: dadosAssociado.logradouro,
                    economia: dadosAssociado.economia,
                    document_name: `Procuração e Termo de Adesão - ${dadosAssociado.nomeAssociado}`
                }));
                formData.append('signers', JSON.stringify(signatarios));
                
                const response = await fetch('/api/documents/create-with-pdf', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.error || 'Erro ao enviar documento');
                }
                
                return result;
                
            } catch (error) {
                console.error('Erro ao enviar documento:', error);
                throw error;
            }
        }

        document.getElementById('documentForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            
            const loading = document.getElementById('loading');
            const result = document.getElementById('result');
            const submitBtn = document.getElementById('submitBtn');
            
            loading.style.display = 'block';
            result.style.display = 'none';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(event.target);
                
                const dadosAssociado = {
                    nomeAssociado: formData.get('nome_associado'),
                    endereco: formData.get('endereco'),
                    formaPagamento: formData.get('forma_pagamento'),
                    cpf: formData.get('cpf_cnpj'),
                    representanteLegal: '',
                    numeroUnidade: formData.get('numero_unidade'),
                    logradouro: formData.get('logradouro'),
                    dia: new Date().getDate().toString().padStart(2, '0'),
                    mes: (new Date().getMonth() + 1).toString().padStart(2, '0'),
                    economia: formData.get('economia') || '20%'
                };
                
                const signatarios = [];
                
                // Email do signatário
                const emailSignatario = formData.get('signatario_email');
                if (emailSignatario && emailSignatario.trim() !== '') {
                    signatarios.push({
                        email: emailSignatario.trim(),
                        action: 'SIGN'
                    });
                    console.log('✅ Signatário EMAIL adicionado:', emailSignatario);
                }
                
                // WhatsApp do signatário
                const whatsappSignatario = formData.get('signatario_whatsapp');
                if (whatsappSignatario && whatsappSignatario.trim() !== '') {
                    const nomeWhatsApp = formData.get('nome_signatario_whatsapp');
                    if (!nomeWhatsApp || nomeWhatsApp.trim() === '') {
                        throw new Error('Nome é obrigatório para envio via WhatsApp');
                    }
                    
                    signatarios.push({
                        phone: whatsappSignatario.trim(),
                        delivery_method: 'DELIVERY_METHOD_WHATSAPP',
                        action: 'SIGN',
                        name: nomeWhatsApp.trim()
                    });
                    console.log('✅ Signatário WHATSAPP adicionado:', whatsappSignatario);
                }
                
                // SMS do signatário
                const smsSignatario = formData.get('signatario_sms');
                if (smsSignatario && smsSignatario.trim() !== '') {
                    const nomeSMS = formData.get('nome_signatario_sms');
                    if (!nomeSMS || nomeSMS.trim() === '') {
                        throw new Error('Nome é obrigatório para envio via SMS');
                    }
                    
                    signatarios.push({
                        phone: smsSignatario.trim(),
                        delivery_method: 'DELIVERY_METHOD_SMS',
                        action: 'SIGN',
                        name: nomeSMS.trim()
                    });
                    console.log('✅ Signatário SMS adicionado:', smsSignatario);
                }
                
                if (signatarios.length === 0) {
                    throw new Error('Informe pelo menos um signatário (email, WhatsApp ou SMS)');
                }
                
                console.log('📝 TOTAL DE SIGNATÁRIOS:', signatarios.length);
                console.log('📋 SIGNATÁRIOS ENVIADOS:', signatarios);
                
                const resultado = await enviarDocumentoParaAssinatura(dadosAssociado, signatarios);
                
                result.className = 'result success';
                result.innerHTML = `
                    <h3>✅ Documento criado com sucesso!</h3>
                    <div class="document-info">
                        <h4>Informações do Documento</h4>
                        <p><strong>ID:</strong> ${resultado.document.id}</p>
                        <p><strong>Nome:</strong> ${resultado.document.name}</p>
                        <p><strong>Criado em:</strong> ${new Date(resultado.document.created_at).toLocaleString('pt-BR')}</p>
                        <p><strong>Modo Sandbox:</strong> ${resultado.sandbox ? 'Sim' : 'Não'}</p>
                        <p><strong>Total de Signatários:</strong> ${resultado.summary.total_signers}</p>
                        
                        <div class="signers-list">
                            <h5>Signatários:</h5>
                            ${resultado.signers_info.map(signer => `
                                <div class="signer-item">
                                    <div>
                                        <strong>${signer.name || signer.email}</strong><br>
                                        <small>${signer.action} • ${signer.email ? 'Email' : 'Telefone'}</small>
                                    </div>
                                    ${signer.signature_link ? 
                                        `<a href="${signer.signature_link}" target="_blank" class="signature-link">
                                            Abrir Link de Assinatura
                                        </a>` : 
                                        '<small>Convite enviado</small>'
                                    }
                                </div>
                            `).join('')}
                        </div>
                        
                        ${resultado.sandbox ? 
                            '<div class="sandbox-notice">Este documento foi criado em modo sandbox e não consome créditos.</div>' : 
                            ''
                        }
                    </div>
                `;
                result.style.display = 'block';
                
                // Scroll para o resultado
                result.scrollIntoView({ behavior: 'smooth' });
                
            } catch (error) {
                result.className = 'result error';
                result.innerHTML = `
                    <h3>❌ Erro ao criar documento</h3>
                    <p><strong>Detalhes:</strong> ${error.message}</p>
                    <details>
                        <summary>Informações técnicas</summary>
                        <ul>
                            <li>Verifique se o token da Autentique está configurado no .env</li>
                            <li>Certifique-se de que o arquivo PDF template existe</li>
                            <li>Verifique sua conexão com a internet</li>
                            <li>Consulte os logs do servidor para mais detalhes</li>
                        </ul>
                    </details>
                `;
                result.style.display = 'block';
                result.scrollIntoView({ behavior: 'smooth' });
            } finally {
                loading.style.display = 'none';
                submitBtn.disabled = false;
            }
        });

        // Auto-fill nome do WhatsApp/SMS baseado no nome do associado
        document.getElementById('nome_associado').addEventListener('input', function() {
            const nome = this.value;
            if (nome) {
                const nomeWhatsApp = document.getElementById('nome_signatario_whatsapp');
                const nomeSMS = document.getElementById('nome_signatario_sms');
                
                if (!nomeWhatsApp.value) nomeWhatsApp.value = nome;
                if (!nomeSMS.value) nomeSMS.value = nome;
            }
        });
    </script>
</body>
</html>