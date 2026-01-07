<?php
namespace Chat;

class ChatService {
    private LlmProvider $provider;

    public function __construct(?LlmProvider $provider = null)
    {
        // Por defecto usamos la factoría, actualmente sólo Gemini
        $this->provider = $provider ?? LlmProviderFactory::create();
    }

    public function reply(string $userMessage): array
    {
        $answer = $this->provider->generate([
            [ 'role' => 'user', 'content' => $userMessage ],
        ]);
        return [ 'role' => 'assistant', 'content' => $answer ];
    }

    /**
     * @param array<int, array{role:string, content:string, file?:array}> $history
     * @param array|null $modalities Modalidades de salida (ej: ['image', 'text'])
     * @param bool $webSearch Activar búsqueda web
     */
    public function replyWithHistory(array $history, ?array $modalities = null, bool $webSearch = false): array
    {
        $answer = $this->provider->generate($history, $modalities, $webSearch);
        return [ 'role' => 'assistant', 'content' => $answer ];
    }

    /**
     * Obtiene las imágenes generadas en la última respuesta
     */
    public function getLastImages(): ?array
    {
        if (method_exists($this->provider, 'getLastImages')) {
            return $this->provider->getLastImages();
        }
        return null;
    }

    /**
     * Obtiene las anotaciones/citas web de la última respuesta
     */
    public function getLastAnnotations(): ?array
    {
        if (method_exists($this->provider, 'getLastAnnotations')) {
            return $this->provider->getLastAnnotations();
        }
        return null;
    }
}
