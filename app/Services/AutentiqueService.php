<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AutentiqueService
{
    private $client;
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 60,
            'verify' => false
        ]);
        $this->apiUrl = env('AUTENTIQUE_API_URL', 'https://api.autentique.com.br/v2/graphql');
        $this->token = env('AUTENTIQUE_API_TOKEN');
    }

    /**
     * Cria um documento simples baseado no exemplo oficial da Autentique
     */
    public function createSimpleDocument($documentData, $signers, $filePath, $sandbox = false)
    {
        if (!$this->token) {
            throw new \Exception('Token da Autentique não configurado no .env');
        }

        if (!file_exists($filePath)) {
            throw new \Exception('Arquivo PDF não encontrado: ' . $filePath);
        }

        // Query exata do exemplo oficial da documentação
        $query = 'mutation CreateDocumentMutation($document: DocumentInput!, $signers: [SignerInput!]!, $file: Upload!) {
            createDocument(document: $document, signers: $signers, file: $file' . ($sandbox ? ', sandbox: true' : '') . ') {
                id 
                name 
                refusable 
                sortable 
                created_at 
                signatures { 
                    public_id 
                    name 
                    email 
                    created_at 
                    action { name } 
                    link { short_link } 
                    user { id name email }
                }
            }
        }';

        $operations = json_encode([
            'query' => $query,
            'variables' => [
                'document' => $documentData,
                'signers' => $signers,
                'file' => null
            ]
        ]);

        $map = json_encode(['file' => ['variables.file']]);

        // Configuração cURL com Content-Type correto para PDF
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'operations' => $operations,
                'map' => $map,
                'file' => new \CURLFile($filePath, 'application/pdf', 'document.pdf') // Nome fixo .pdf
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            Log::error('Erro cURL', ['error' => $error]);
            throw new \Exception('Erro de conexão: ' . $error);
        }

        Log::info('Resposta Autentique via cURL', [
            'http_code' => $httpCode,
            'response_size' => strlen($response),
            'response_preview' => substr($response, 0, 200)
        ]);

        $body = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Erro ao decodificar JSON', [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500)
            ]);
            throw new \Exception('Resposta inválida da API: ' . json_last_error_msg());
        }

        if (isset($body['errors']) && !empty($body['errors'])) {
            Log::error('Erros da API Autentique:', $body['errors']);
            $firstError = $body['errors'][0];
            $errorMessage = $firstError['message'] ?? 'Erro desconhecido';
            
            // Log detalhado do erro de validação
            if (isset($firstError['extensions']['validation'])) {
                Log::error('Detalhes da validação:', $firstError['extensions']['validation']);
            }
            
            throw new \Exception('Erro da API: ' . $errorMessage);
        }

        return $body['data'] ?? $body;
    }

    // Manter todos os outros métodos iguais...
    public function createDocument($documentData, $signers, $filePath, $sandbox = false)
    {
        if (!$this->token) {
            throw new \Exception('Token da Autentique não configurado no .env');
        }

        if (!file_exists($filePath)) {
            throw new \Exception('Arquivo PDF não encontrado: ' . $filePath);
        }

        $mutation = '
            mutation CreateDocumentMutation(
                $document: DocumentInput!,
                $signers: [SignerInput!]!,
                $file: Upload!
            ) {
                createDocument(
                    document: $document,
                    signers: $signers,
                    file: $file,
                    sandbox: ' . ($sandbox ? 'true' : 'false') . '
                ) {
                    id
                    name
                    refusable
                    sortable
                    sandbox
                    created_at
                    signatures {
                        public_id
                        name
                        email
                        created_at
                        action { name }
                        link { short_link }
                        user { id name email }
                    }
                }
            }
        ';

        $variables = [
            'document' => $documentData,
            'signers' => $signers,
            'file' => null
        ];

        return $this->sendMultipartRequest($mutation, $variables, $filePath);
    }

    public function getDocument($documentId)
    {
        $query = '
            query GetDocument($id: UUID!) {
                document(id: $id) {
                    id
                    name
                    refusable
                    sortable
                    sandbox
                    created_at
                    signatures_count
                    signed_count
                    rejected_count
                    signatures {
                        public_id
                        name
                        email
                        created_at
                        action { name }
                        link { short_link }
                        user { id name email }
                        viewed { 
                            created_at 
                            ip 
                            geolocation { 
                                country 
                                state 
                                city 
                                latitude 
                                longitude 
                            }
                        }
                        signed { 
                            created_at 
                            ip 
                        }
                        rejected { 
                            created_at 
                            reason 
                        }
                    }
                    files {
                        original
                        signed
                        pades
                    }
                }
            }
        ';

        return $this->sendGraphQLRequest($query, ['id' => $documentId]);
    }

    public function listDocuments($limit = 60, $page = 1, $showSandbox = false)
    {
        $query = '
            query ListDocuments($limit: Int!, $page: Int!, $showSandbox: Boolean!) {
                documents(limit: $limit, page: $page, showSandbox: $showSandbox) {
                    total
                    data {
                        id
                        name
                        created_at
                        signatures {
                            public_id
                            name
                            email
                            created_at
                            action { name }
                            link { short_link }
                            user { id name email }
                            viewed { created_at }
                            signed { created_at }
                            rejected { created_at }
                        }
                        files { original signed }
                    }
                }
            }
        ';

        return $this->sendGraphQLRequest($query, [
            'limit' => $limit,
            'page' => $page,
            'showSandbox' => $showSandbox
        ]);
    }

    public function resendSignatures($publicIds)
    {
        $mutation = '
            mutation ResendSignatures($public_ids: [UUID!]!) {
                resendSignatures(public_ids: $public_ids)
            }
        ';

        return $this->sendGraphQLRequest($mutation, ['public_ids' => $publicIds]);
    }

    public function testConnection()
    {
        try {
            $query = '
                query {
                    documents(limit: 1, page: 1) {
                        total
                    }
                }
            ';

            $result = $this->sendGraphQLRequest($query);
            
            return [
                'success' => true,
                'message' => 'Token válido e funcionando!',
                'data' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function sendGraphQLRequest($query, $variables = [])
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel-AutentiqueIntegration/2.0'
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $bodyContents = $response->getBody()->getContents();

            Log::info('Autentique API Response', [
                'status_code' => $statusCode,
                'query' => substr($query, 0, 200),
                'variables' => $variables,
                'response_size' => strlen($bodyContents)
            ]);

            if (str_starts_with(trim($bodyContents), '<')) {
                Log::error('Autentique retornou HTML', [
                    'response' => substr($bodyContents, 0, 500)
                ]);
                throw new \Exception('API retornou HTML. Verifique se o token está correto e se a URL está correta.');
            }

            $body = json_decode($bodyContents, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Erro ao decodificar JSON', [
                    'error' => json_last_error_msg(),
                    'response' => substr($bodyContents, 0, 500)
                ]);
                throw new \Exception('Resposta inválida da API: ' . json_last_error_msg());
            }
            
            if (isset($body['errors']) && !empty($body['errors'])) {
                Log::error('Erros na API Autentique:', $body['errors']);
                
                $firstError = $body['errors'][0];
                $errorMessage = $firstError['message'] ?? 'Erro desconhecido';
                
                if (strpos($errorMessage, 'Internal server error') !== false) {
                    throw new \Exception('Erro interno da API. Verifique se o documento PDF é válido e se sua conta tem créditos disponíveis.');
                }
                
                if (strpos($errorMessage, 'Unauthorized') !== false) {
                    throw new \Exception('Token de autenticação inválido ou expirado.');
                }
                
                throw new \Exception('Erro da API: ' . $errorMessage);
            }

            return $body['data'] ?? $body;
            
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $statusCode = $response->getStatusCode();
                $bodyContents = $response->getBody()->getContents();
                
                Log::error('Erro HTTP na Autentique API:', [
                    'status_code' => $statusCode,
                    'response' => $bodyContents,
                    'url' => $this->apiUrl
                ]);
                
                if ($statusCode === 429) {
                    throw new \Exception('Rate limit excedido. Aguarde um momento antes de tentar novamente.');
                }
                
                throw new \Exception("Erro HTTP {$statusCode}: Verifique sua conexão e token");
            } else {
                Log::error('Erro de conexão: ' . $e->getMessage());
                throw new \Exception('Erro de conexão com a API da Autentique');
            }
        }
    }

    private function sendMultipartRequest($mutation, $variables, $filePath)
    {
        try {
            $multipart = [
                [
                    'name' => 'operations',
                    'contents' => json_encode([
                        'query' => $mutation,
                        'variables' => $variables
                    ])
                ],
                [
                    'name' => 'map',
                    'contents' => json_encode(['file' => ['variables.file']])
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                    'headers' => ['Content-Type' => 'application/pdf']
                ]
            ];

            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel-AutentiqueIntegration/2.0'
                ],
                'multipart' => $multipart
            ]);

            $statusCode = $response->getStatusCode();
            $bodyContents = $response->getBody()->getContents();

            Log::info('Autentique Multipart Response', [
                'status_code' => $statusCode,
                'file' => basename($filePath),
                'file_size' => filesize($filePath),
                'response_size' => strlen($bodyContents)
            ]);

            if (str_starts_with(trim($bodyContents), '<')) {
                Log::error('HTML retornado em multipart', [
                    'response' => substr($bodyContents, 0, 500)
                ]);
                throw new \Exception('API retornou HTML. Verifique se o token está correto.');
            }

            $body = json_decode($bodyContents, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Erro JSON em multipart', [
                    'error' => json_last_error_msg(),
                    'response' => substr($bodyContents, 0, 500)
                ]);
                throw new \Exception('Resposta inválida: ' . json_last_error_msg());
            }
            
            if (isset($body['errors']) && !empty($body['errors'])) {
                Log::error('Erros em multipart:', $body['errors']);
                
                $firstError = $body['errors'][0];
                $errorMessage = $firstError['message'] ?? 'Erro desconhecido';
                
                if (strpos($errorMessage, 'Internal server error') !== false) {
                    throw new \Exception('Erro interno: Verifique se o PDF é válido e se sua conta tem créditos disponíveis.');
                }
                
                throw new \Exception('Erro ao criar documento: ' . $errorMessage);
            }

            return $body['data'] ?? $body;
            
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $statusCode = $response->getStatusCode();
                $bodyContents = $response->getBody()->getContents();
                
                Log::error('Erro HTTP em multipart:', [
                    'status_code' => $statusCode,
                    'response' => substr($bodyContents, 0, 500)
                ]);
                
                throw new \Exception("Erro HTTP {$statusCode} ao enviar documento");
            } else {
                Log::error('Erro de conexão em multipart: ' . $e->getMessage());
                throw new \Exception('Erro de conexão ao enviar documento');
            }
        }
    }
}