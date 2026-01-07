<?php
namespace Chat;

class OpenRouterProvider implements LlmProvider
{
    private OpenRouterClient $client;

    public function __construct(?OpenRouterClient $client = null, ?ContextBuilder $contextBuilder = null)
    {
        // Construir el contexto corporativo
        $contextBuilder = $contextBuilder ?? new ContextBuilder();
        $systemPrompt = $contextBuilder->buildSystemPrompt();

        // Si no se pasó un cliente, crearlo con el contexto
        if ($client === null) {
            $this->client = new OpenRouterClient(null, null, $systemPrompt);
        } else {
            $this->client = $client;
        }
    }

    /**
     * @param array<int, array{role:string, content:string, file?:array}> $messages
     * @param array|null $modalities Modalidades de salida (ej: ['image', 'text'])
     * @param bool $webSearch Activar búsqueda web
     */
    public function generate(array $messages, ?array $modalities = null, bool $webSearch = false): string
    {
        return $this->client->generateWithMessages($messages, $modalities, $webSearch);
    }

    /**
     * Obtiene el modelo usado por el cliente
     */
    public function getModel(): string
    {
        return $this->client->getModel();
    }

    /**
     * Obtiene las imágenes generadas en la última respuesta
     */
    public function getLastImages(): ?array
    {
        return $this->client->getLastImages();
    }

    /**
     * Obtiene las anotaciones/citas web de la última respuesta
     */
    public function getLastAnnotations(): ?array
    {
        return $this->client->getLastAnnotations();
    }
}
