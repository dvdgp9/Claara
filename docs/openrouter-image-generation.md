# OpenRouter — Image Generation (Gemini 3 image / Nano Banana)

Fuente: https://openrouter.ai/docs/guides/overview/multimodal/image-generation
Consultado: 2026-06-09

## Endpoint
`POST /api/v1/chat/completions` (mismo que chat), con `modalities`.

## Request

```json
{
  "model": "google/gemini-3.1-flash-image-preview",
  "messages": [
    { "role": "user", "content": "Your image prompt here" }
  ],
  "modalities": ["image", "text"],
  "image_config": {
    "aspect_ratio": "16:9",
    "image_size": "2K"
  }
}
```

- **modalities**: modelos duales (Gemini) → `["image","text"]`; solo-imagen (Flux) → `["image"]`.
- **image_config** (NO se enviaba en nuestro cliente; hay que añadirlo):
  - **aspect_ratio**: `1:1` (1024×1024, default), `2:3` (832×1248), `3:2` (1248×832), `4:3` (1184×864), `3:4` (864×1184), `4:5` (896×1152), `5:4` (1152×896), `9:16` (768×1344), `16:9` (1344×768), `21:9` (1536×672). Extremos solo Gemini 3.1 Flash: `1:4, 4:1, 1:8, 8:1`.
  - **image_size**: `0.5K` (solo Gemini), `1K` (default), `2K`, `4K`.

## Imágenes de entrada (edición / referencia)
Se pasan dentro de `messages[].content` como bloques:
```json
{ "type": "image_url", "image_url": { "url": "data:image/png;base64,..." } }
```

## Response
```json
{
  "choices": [{
    "message": {
      "role": "assistant",
      "content": "Text response",
      "images": [
        { "type": "image_url", "image_url": { "url": "data:image/png;base64,..." } }
      ]
    }
  }]
}
```

## Modelos relevantes
- `google/gemini-3.1-flash-image-preview` — Nano Banana 2. Rápido/barato. **El que usamos hoy.**
- `google/gemini-3-pro-image-preview` — Nano Banana Pro. Más fidelidad, soporta 2K/4K, edición localizada, controles de luz/foco/cámara. Más caro/lento. Candidato para un modo "alta calidad".

## Notas de implementación para Claara
- `src/Chat/OpenRouterClient::generateWithMessages()` hoy solo añade `modalities` y `temperature`. Añadir soporte para `image_config` (aspect_ratio, image_size).
- El parseo de `getLastImages()` ya soporta el formato `images[].image_url.url`.
