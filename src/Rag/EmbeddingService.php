<?php
namespace Rag;

/**
 * Servicio para generar embeddings usando OpenRouter API
 * Modelo por defecto: qwen/qwen3-embedding-8b (4096 dimensiones)
 */
class EmbeddingService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct(string $apiKey, string $model = 'qwen/qwen3-embedding-8b')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Genera embedding para un texto
     * 
     * @param string $text Texto a vectorizar
     * @return array Vector de floats (4096 dimensiones para qwen3-embedding-8b)
     */
    public function embed(string $text): array
    {
        $response = $this->request('/embeddings', [
            'model' => $this->model,
            'input' => $text
        ]);

        return $response['data'][0]['embedding'] ?? [];
    }

    /**
     * Genera embeddings para múltiples textos en batch
     * 
     * @param array $texts Array de textos
     * @return array Array de vectores
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = $this->request('/embeddings', [
            'model' => $this->model,
            'input' => $texts
        ]);

        $embeddings = [];
        foreach ($response['data'] ?? [] as $item) {
            $embeddings[$item['index']] = $item['embedding'];
        }

        // Ordenar por índice
        ksort($embeddings);
        return array_values($embeddings);
    }

    /**
     * Realiza petición a OpenRouter API
     */
    private function request(string $path, array $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://ebonia.app',
                'X-Title: iaiaPRO RAG'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("OpenRouter request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $data['error']['message'] ?? $response;
            throw new \Exception("OpenRouter error ({$httpCode}): {$errorMsg}");
        }

        return $data;
    }
}
