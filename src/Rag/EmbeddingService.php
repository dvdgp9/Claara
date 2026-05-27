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
            'input' => $this->normalizeText($text)
        ]);

        $embedding = $response['data'][0]['embedding'] ?? [];
        if (empty($embedding)) {
            throw new \Exception('OpenRouter no devolvió embedding para el texto solicitado');
        }

        return $embedding;
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

        $texts = array_values(array_map([$this, 'normalizeText'], $texts));

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
        $embeddings = array_values($embeddings);

        if (count($embeddings) !== count($texts)) {
            throw new \Exception('OpenRouter devolvió ' . count($embeddings) . ' embeddings para ' . count($texts) . ' textos');
        }

        return $embeddings;
    }

    /**
     * Normaliza texto extraído de PDFs antes de enviarlo como JSON.
     */
    private function normalizeText(string $text): string
    {
        $text = str_replace("\0", '', $text);

        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if (is_string($converted)) {
                $text = $converted;
            }
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (is_string($clean)) {
            $text = $clean;
        }

        $text = preg_replace('/[^\P{C}\t\n\r]+/u', '', $text);
        if ($text === null) {
            $text = '';
        }

        return trim($text);
    }

    /**
     * Realiza petición a OpenRouter API
     */
    private function request(string $path, array $body): array
    {
        $payload = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($payload === false) {
            throw new \Exception('No se pudo codificar la petición JSON para OpenRouter: ' . json_last_error_msg());
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://claara.tech',
                'X-Title: Claara RAG'
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
