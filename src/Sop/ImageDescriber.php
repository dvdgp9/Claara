<?php

namespace Sop;

use App\Env;

/**
 * Descriptor de imágenes usando Gemini Vision via OpenRouter
 * Extrae información estructural de capturas de pantalla, diagramas, etc.
 */
class ImageDescriber
{
    private string $apiKey;
    private string $model = 'google/gemini-3-flash-preview';
    private string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
    
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (Env::get('OPENROUTER_API_KEY') ?? '');
    }
    
    /**
     * Describe una imagen extrayendo información relevante para SOPs
     * 
     * @param string $base64Data Imagen en base64
     * @param string $mimeType Tipo MIME de la imagen
     * @param string $context Contexto adicional sobre qué buscar
     * @return array ['success' => bool, 'description' => string, 'error' => string|null]
     */
    public function describe(string $base64Data, string $mimeType, string $context = ''): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Falta OPENROUTER_API_KEY'];
        }
        
        // Validar tipo de imagen
        $validTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        
        if (!in_array($mimeType, $validTypes)) {
            return ['success' => false, 'error' => 'Tipo de imagen no soportado: ' . $mimeType];
        }
        
        $contextPrompt = $context ? "\n\nContexto adicional: {$context}" : '';
        
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mimeType . ';base64,' . $base64Data
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => "Analiza esta imagen para documentar un procedimiento operativo (SOP). Extrae la siguiente información:

1. **Tipo de imagen**: ¿Es una captura de pantalla, diagrama de flujo, formulario, interfaz de usuario, documento, foto de equipo/instalación, etc.?

2. **Texto visible**: Transcribe TODO el texto legible que aparezca (menús, botones, etiquetas, títulos, instrucciones).

3. **Elementos de interfaz** (si aplica): Describe botones, campos, menús, iconos y su ubicación/función.

4. **Secuencia/flujo** (si aplica): Si hay pasos numerados, flechas, o un flujo de proceso, descríbelo en orden.

5. **Información relevante para el proceso**: ¿Qué acción o paso del proceso representa esta imagen?

Responde de forma estructurada y detallada. El objetivo es poder reconstruir las instrucciones del proceso sin ver la imagen.{$contextPrompt}"
                        ]
                    ]
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 4096
        ];
        
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . (Env::get('APP_URL') ?? 'https://ebonia.es'),
                'X-Title: iaiaPRO SOP Generator'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
        }
        
        if (!$response) {
            return ['success' => false, 'error' => 'No se recibió respuesta de OpenRouter'];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Error desconocido';
            return ['success' => false, 'error' => 'Error de API: ' . $errorMsg];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Error HTTP {$httpCode}"];
        }
        
        $description = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($description)) {
            return ['success' => false, 'error' => 'Descripción vacía'];
        }
        
        return [
            'success' => true,
            'description' => trim($description)
        ];
    }
    
    /**
     * Describe múltiples imágenes y combina las descripciones
     * 
     * @param array $images Array de ['base64' => string, 'mime_type' => string]
     * @return array ['success' => bool, 'descriptions' => array, 'combined' => string, 'error' => string|null]
     */
    public function describeMultiple(array $images): array
    {
        $descriptions = [];
        $errors = [];
        
        foreach ($images as $index => $image) {
            $result = $this->describe(
                $image['base64'],
                $image['mime_type'],
                "Esta es la imagen " . ($index + 1) . " de " . count($images) . " del proceso."
            );
            
            if ($result['success']) {
                $descriptions[] = [
                    'index' => $index + 1,
                    'description' => $result['description']
                ];
            } else {
                $errors[] = "Imagen " . ($index + 1) . ": " . $result['error'];
            }
        }
        
        if (empty($descriptions)) {
            return [
                'success' => false,
                'error' => 'No se pudo procesar ninguna imagen: ' . implode('; ', $errors)
            ];
        }
        
        // Combinar descripciones
        $combined = "";
        foreach ($descriptions as $desc) {
            $combined .= "### Imagen {$desc['index']}\n\n{$desc['description']}\n\n---\n\n";
        }
        
        return [
            'success' => true,
            'descriptions' => $descriptions,
            'combined' => trim($combined),
            'errors' => $errors
        ];
    }
}
