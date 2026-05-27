<?php
namespace Rag;

/**
 * Cliente HTTP para Qdrant Vector Database
 * Documentación: https://qdrant.tech/documentation/
 */
class QdrantClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $host = 'localhost', int $port = 6333, int $timeout = 30)
    {
        $this->baseUrl = "http://{$host}:{$port}";
        $this->timeout = $timeout;
    }

    /**
     * Verifica si Qdrant está disponible
     */
    public function health(): bool
    {
        try {
            $response = $this->request('GET', '/');
            return isset($response['title']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Crea una colección para almacenar vectores
     */
    public function createCollection(string $name, int $vectorSize = 1536, string $distance = 'Cosine'): array
    {
        return $this->request('PUT', "/collections/{$name}", [
            'vectors' => [
                'size' => $vectorSize,
                'distance' => $distance
            ]
        ]);
    }

    /**
     * Verifica si una colección existe
     */
    public function collectionExists(string $name): bool
    {
        try {
            $this->request('GET', "/collections/{$name}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Elimina una colección
     */
    public function deleteCollection(string $name): array
    {
        return $this->request('DELETE', "/collections/{$name}");
    }

    /**
     * Inserta puntos (vectores con payload) en una colección
     * 
     * @param string $collection Nombre de la colección
     * @param array $points Array de puntos: [['id' => int, 'vector' => float[], 'payload' => array], ...]
     */
    public function upsertPoints(string $collection, array $points): array
    {
        return $this->request('PUT', "/collections/{$collection}/points", [
            'points' => $points
        ]);
    }

    /**
     * Busca los puntos más similares a un vector
     * 
     * @param string $collection Nombre de la colección
     * @param array $vector Vector de búsqueda
     * @param int $limit Número máximo de resultados
     * @param array $filter Filtros opcionales por payload
     * @return array Puntos encontrados con score de similitud
     */
    public function search(string $collection, array $vector, int $limit = 5, ?array $filter = null): array
    {
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true
        ];

        if ($filter !== null) {
            $body['filter'] = $filter;
        }

        $response = $this->request('POST', "/collections/{$collection}/points/search", $body);
        return $response['result'] ?? [];
    }

    /**
     * Obtiene información de una colección
     */
    public function getCollectionInfo(string $name): array
    {
        return $this->request('GET', "/collections/{$name}");
    }

    /**
     * Cuenta los puntos en una colección
     */
    public function countPoints(string $collection): int
    {
        $response = $this->request('POST', "/collections/{$collection}/points/count", [
            'exact' => true
        ]);
        return $response['result']['count'] ?? 0;
    }

    /**
     * Cuenta los puntos que coinciden con un filtro
     */
    public function countPointsByFilter(string $collection, array $filter): int
    {
        $response = $this->request('POST', "/collections/{$collection}/points/count", [
            'filter' => $filter,
            'exact' => true
        ]);
        return $response['result']['count'] ?? 0;
    }

    /**
     * Elimina puntos por filtro de payload
     * 
     * @param string $collection Nombre de la colección
     * @param array $filter Filtro en formato Qdrant (must, should, must_not)
     */
    public function deletePointsByFilter(string $collection, array $filter): array
    {
        return $this->request('POST', "/collections/{$collection}/points/delete", [
            'filter' => $filter
        ]);
    }

    /**
     * Obtiene el siguiente ID disponible para insertar puntos
     */
    public function getNextPointId(string $collection): int
    {
        // Qdrant no tiene auto-increment, usamos el count + 1 como aproximación
        // En producción, se podría usar un campo de metadatos o UUID
        return $this->countPoints($collection) + 1;
    }

    /**
     * Realiza una petición HTTP a Qdrant
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init();

        $url = $this->baseUrl . $path;
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        if ($body !== null) {
            $payload = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($payload === false) {
                throw new \Exception('No se pudo codificar la petición JSON para Qdrant: ' . json_last_error_msg());
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Qdrant request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $data['status']['error'] ?? $response;
            throw new \Exception("Qdrant error ({$httpCode}): {$errorMsg}");
        }

        return $data;
    }
}
