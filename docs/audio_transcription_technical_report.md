# Informe tecnico: transcripcion de audio largo con segmentos, jobs y hablantes

## Objetivo

Implementar un gesto de transcripcion de audio capaz de procesar grabaciones largas, por ejemplo reuniones de 40-45 minutos, evitando estos fallos:

- timeouts HTTP 504 por requests demasiado largos;
- errores 500 por memoria, base64 o restricciones de servidor;
- transcripciones cortadas por limite de salida;
- artefactos repetitivos como `nonono`;
- segmentos que fallan con `Transcripcion vacia`;
- salida sin estructura por hablantes;
- falta de feedback visual mientras el proceso tarda varios minutos.

La implementacion final combina subida multipart, procesamiento en background, segmentacion con `ffmpeg`, transcripcion por proveedor configurable, salida parcial por polling y un prompt estricto para hablantes.

## Archivos principales

- `public/gestos/transcriptor-audio.php`: UI del gesto, subida de audio, polling y render de parciales.
- `public/api/gestures/transcribe.php`: endpoint de entrada. Valida audio y crea el job asincrono.
- `public/api/jobs/process.php`: procesador de jobs. Ejecuta `audio-transcribe`.
- `public/api/jobs/status.php`: endpoint de estado usado por polling.
- `src/Sop/AudioTranscriber.php`: nucleo de transcripcion, segmentacion, proveedores y prompts.
- `src/Jobs/BackgroundJobsRepo.php`: persistencia y actualizacion de progreso/salida parcial.
- `docs/gemini_audio_transcription.md`: nota tecnica corta del proveedor/modelo.

## Variables de entorno

Variables recomendadas:

```env
OPENROUTER_API_KEY=...
GEMINI_API_KEY=...

# auto | gemini | openrouter
AUDIO_TRANSCRIBE_PROVIDER=auto

# Usado si provider=openrouter
OPENROUTER_TRANSCRIBE_MODEL=google/gemini-3-flash-preview

# Usado si provider=gemini/direct/google
GEMINI_TRANSCRIBE_MODEL=gemini-3-flash-preview

# Opcional si PHP no encuentra los binarios
FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe

# Opcional, solo para Gemini directo paralelo
GEMINI_TRANSCRIBE_CONCURRENCY=5
```

Decision importante de esta app: aunque OpenRouter esta soportado, el modo `auto` actual prefiere Gemini directo si existe `GEMINI_API_KEY`, porque la File API de Google evita enviar el audio como base64 dentro de JSON y ha resultado mas rapida/robusta para audio largo. Si en otra app quieres usar OpenRouter si o si, configura:

```env
AUDIO_TRANSCRIBE_PROVIDER=openrouter
OPENROUTER_TRANSCRIBE_MODEL=google/gemini-3-flash-preview
```

## Flujo completo

1. El usuario sube un archivo de audio desde `public/gestos/transcriptor-audio.php`.
2. El frontend envia `multipart/form-data` a `/api/gestures/transcribe.php`:

```js
const formData = new FormData();
formData.append('audio_file', currentFile, currentFile.name);
formData.append('async', '1');

fetch('/api/gestures/transcribe.php', {
  method: 'POST',
  headers: { 'X-CSRF-Token': csrf },
  body: formData
});
```

3. El endpoint valida CSRF, MIME, tamano maximo y guarda el audio en `storage/transcribe-jobs`.
4. Crea un job `audio-transcribe` en `background_jobs`.
5. El frontend dispara `/api/jobs/process.php` y empieza polling a `/api/jobs/status.php?id=...`.
6. El procesador lee el archivo temporal, instancia `AudioTranscriber` y llama a `transcribeBytes()`.
7. Si el audio dura 10 minutos o mas, se segmenta automaticamente.
8. Cada segmento se transcribe y se acumula texto parcial.
9. El repo actualiza `progress_text` y `output_data.partial_transcription`.
10. El frontend muestra progreso y texto parcial.
11. Al terminar, se guarda una ejecucion en `gesture_executions`.

## Por que multipart y no base64 desde navegador

El primer enfoque usaba JSON con audio base64. Eso es mala idea para audios largos:

- base64 aumenta el tamano aproximadamente un 33%;
- obliga a tener el archivo completo duplicado en memoria en navegador y PHP;
- puede fallar antes de llegar al modelo con 500 por memoria o `post_max_size`;
- dificulta detectar errores reales.

La solucion fue subir como `multipart/form-data` con campo `audio_file`. El endpoint conserva compatibilidad legacy con JSON/base64, pero el flujo principal debe usar multipart.

## Jobs asincronos

Para audios largos no hay que mantener viva la request original hasta que termine la transcripcion. El endpoint debe devolver rapido:

```json
{
  "success": true,
  "async": true,
  "job_id": 123,
  "message": "Transcripcion encolada"
}
```

El frontend:

- guarda `audio_transcriber_job_id` en `sessionStorage`;
- llama a `/api/jobs/process.php` para despertar el worker;
- consulta `/api/jobs/status.php` cada pocos segundos;
- reintenta despertar el worker cada 30 segundos mientras el job esta activo;
- corta tras un timeout largo, por ejemplo 75 minutos.

El repo necesita poder actualizar salida parcial durante `processing`:

```php
public function updateProcessingSnapshot(int $id, string $progressText, array $outputData): bool
{
    $stmt = $this->db->prepare("
        UPDATE background_jobs
        SET progress_text = :progress_text, output_data = :output_data
        WHERE id = :id
    ");

    return $stmt->execute([
        'id' => $id,
        'progress_text' => $progressText,
        'output_data' => json_encode($outputData),
    ]);
}
```

## Segmentacion de audio

La regla actual:

- detectar duracion con `ffprobe`;
- si dura 10 minutos o mas, segmentar;
- segmento base: 180 segundos;
- segmento minimo para fallback: 45 segundos;
- salida normalizada: M4A/AAC mono, 16 kHz, 48 kbps.

Comando conceptual:

```bash
ffmpeg -hide_banner -loglevel error -y \
  -i input.ext \
  -vn -ac 1 -ar 16000 -c:a aac -b:a 48k \
  -f segment -segment_time 180 -reset_timestamps 1 \
  segment_%03d.m4a
```

Motivos:

- reduce riesgo de `MAX_TOKENS`;
- evita requests de 45 minutos;
- permite progreso parcial;
- permite reintentar solo el segmento problematico;
- reduce coste de fallos.

## `ffmpeg`, `ffprobe` y `open_basedir`

En hosting Plesk/PHP con `open_basedir`, no basta con que exista `/usr/bin/ffmpeg`. PHP debe poder acceder a esa ruta.

Errores encontrados:

```text
is_executable(): open_basedir restriction in effect
realpath(): open_basedir restriction in effect
```

Lecciones:

- no llamar `realpath()` ni `is_executable()` sobre rutas fuera de `open_basedir`;
- comprobar primero si la ruta esta permitida;
- permitir configurar `FFMPEG_PATH` y `FFPROBE_PATH`;
- si `/usr/bin` esta fuera de `open_basedir`, hay que ampliar `open_basedir` o poner los binarios en una ruta permitida.

## Proveedores de modelo

### Gemini directo

Usa:

- `GEMINI_API_KEY`;
- File API de Google para subir audio;
- `generateContent` con `fileData`.

Ventajas:

- no manda audio como base64 dentro del JSON;
- mejor para audios largos;
- permite paralelizar segmentos con `curl_multi`;
- menos problemas de payload ignorado.

Payload conceptual:

```json
{
  "contents": [
    {
      "parts": [
        {
          "fileData": {
            "mimeType": "audio/mp4",
            "fileUri": "files/..."
          }
        },
        {
          "text": "Transcribe este audio..."
        }
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.1,
    "maxOutputTokens": 65536
  }
}
```

### OpenRouter

Usa:

- `OPENROUTER_API_KEY`;
- endpoint `https://openrouter.ai/api/v1/chat/completions`;
- modelo con prefijo de proveedor, por ejemplo `google/gemini-3-flash-preview`.

El audio se envia base64 dentro del JSON. Punto critico: en PHP/cURL raw debe usarse `input_audio`, no `inputAudio`.

Correcto:

```json
{
  "model": "google/gemini-3-flash-preview",
  "messages": [
    {
      "role": "user",
      "content": [
        { "type": "text", "text": "Transcribe este audio..." },
        {
          "type": "input_audio",
          "input_audio": {
            "data": "BASE64...",
            "format": "m4a"
          }
        }
      ]
    }
  ],
  "temperature": 0.1,
  "max_tokens": 65536,
  "stream": false
}
```

Incorrecto en REST raw:

```json
{
  "type": "input_audio",
  "inputAudio": { "data": "BASE64...", "format": "m4a" }
}
```

Ese error produce llamadas reales al modelo, pero el audio se ignora. Sintoma: OpenRouter muestra pocos tokens de input, por ejemplo ~500, y Gemini responde "proporciona el archivo de audio".

## Normalizacion de IDs de modelo

OpenRouter y Google directo no usan el mismo ID:

- OpenRouter: `google/gemini-3-flash-preview`
- Google directo: `gemini-3-flash-preview`

El transcriptor debe normalizar:

- si proveedor es `gemini` y el modelo empieza por `google/`, quitar el prefijo;
- si proveedor es `openrouter` y el modelo no contiene `/`, anteponer `google/`.

Esto evita configurar accidentalmente un ID de OpenRouter en una URL de Google o al reves.

## Prompt de transcripcion

El prompt principal debe exigir:

- transcripcion literal, completa y cronologica;
- no resumir;
- no rellenar silencios;
- usar `[inaudible]` solo cuando una parte no se entiende;
- si el segmento entero no contiene habla, responder exactamente `[sin habla]`;
- no repetir artefactos;
- conservar palabras en otros idiomas;
- salida siempre estructurada por hablantes.

Parte critica para hablantes:

```text
Formato obligatorio de hablantes:
- Escribe SIEMPRE la transcripcion en turnos de hablante.
- Cada intervencion debe empezar en una linea nueva con una etiqueta seguida de dos puntos.
- Si conoces el nombre de la persona, usa ese nombre como etiqueta.
- Si deduces un rol por el contexto, usa ese rol como etiqueta.
- Si no conoces nombre ni rol, usa "Persona 1:", "Persona 2:", "Persona 3:", etc.
- Aunque solo haya una persona, etiqueta igualmente cada intervencion como "Persona 1:".
- No devuelvas parrafos, frases ni fragmentos sin etiqueta de hablante.
- No mezcles intervenciones de personas distintas en el mismo parrafo.
```

No usar una regla como "si solo hay un hablante, omite etiqueta" si el producto necesita resultado estructurado.

## Manejo de segmentos vacios

No se debe tratar cualquier respuesta vacia como fallo fatal de todo el job.

Estrategia:

1. Si el modelo devuelve texto vacio, marcar `code=empty_transcription`.
2. Reintentar ese segmento con un prompt ASR mas simple.
3. Si sigue vacio, subdividir el segmento.
4. Si al llegar a 45 segundos sigue vacio, devolver exito con `text=''` y `empty_segment=true`.
5. Omitirlo del texto final.
6. Si todos los segmentos quedan vacios, entonces si fallar con "Transcripcion vacia tras dividir el audio en segmentos".

El prompt no debe decir "si hay silencio, no escribas nada" para el segmento completo, porque eso hace imposible distinguir silencio real de fallo. Debe decir:

```text
Si el audio o segmento completo no contiene habla humana entendible, responde exactamente [sin habla].
```

## Manejo de `MAX_TOKENS`

Si el modelo devuelve `finishReason = MAX_TOKENS` o `finish_reason = length/max_tokens`:

- no guardar el texto como valido;
- marcar `code=max_tokens`;
- dividir el segmento en partes mas pequenas;
- reintentar hasta el minimo configurado.

Esto evita guardar transcripciones incompletas.

## Manejo de repeticiones artificiales

Se detectaron artefactos tipo `nonono` o frases repetidas muchas veces.

Estrategia:

- detectar repeticion artificial;
- reintentar con prompt ASR simple;
- si persiste pero hay contenido recuperable, sanitizar repeticiones;
- marcar `finish_reason=SANITIZED` si se guarda texto limpiado;
- si no hay texto util, fallar ese segmento.

## Progreso parcial

Durante el procesamiento, el callback `onProgress` recibe:

```php
[
  'segments_done' => 3,
  'segments_total' => 15,
  'segment_seconds' => 180,
  'segmented' => true,
  'output_chars' => 12345,
  'phase' => 'transcribing',
  'current_segment' => 4
]
```

El job guarda:

```php
[
  'is_partial' => true,
  'partial_transcription' => $partialText,
  'phase' => 'transcribing',
  'segments_done' => $done,
  'segments_total' => $total,
  'metadata' => [...]
]
```

El frontend muestra:

- texto de estado;
- barra `done / total fragmentos`;
- transcripcion parcial mientras sigue trabajando;
- recuperacion de job si recargas la pagina, usando `sessionStorage`.

## Paralelizacion

La implementacion actual paraleliza segmentos solo cuando el proveedor es Gemini directo:

```php
$useParallel = $this->provider === 'gemini' && $totalSegments > 1;
```

Motivo:

- Gemini directo usa File API y cada segmento requiere `start_upload`, `upload_bytes`, `generate`;
- `curl_multi` permite mantener una ventana de N segmentos activos;
- mejora mucho el tiempo total en audios de 45 minutos.

Config:

```env
GEMINI_TRANSCRIBE_CONCURRENCY=5
```

Limites recomendados:

- minimo 1;
- maximo 10;
- default 5.

Si falla un segmento en modo paralelo, se reintenta secuencialmente con la logica completa de fallback.

## Debug y observabilidad

Se escribe log en:

```text
storage/transcribe-debug.log
```

Debe incluir:

- timestamp;
- PID;
- proveedor;
- tamano y MIME de audio;
- inicio/fin de segmento;
- duracion de `generateContent`;
- errores de API/red;
- codigos como `empty_transcription`, `max_tokens`, `repetition`.

Tambien se guardan metadatos en el resultado:

```json
{
  "model": "gemini-3-flash-preview",
  "provider": "gemini",
  "finish_reason": "STOP",
  "audio_size_mb": 12.4,
  "output_chars": 35000,
  "segmented": true,
  "segment_count": 15,
  "segment_seconds": 180
}
```

## Errores encontrados y solucion

### 503 en `transcriptor-audio.php`

Causa: service worker respondia "Offline" e interceptaba navegaciones/API.

Solucion: no interceptar `.php`, `/api/` ni navegaciones dinamicas.

### 500 rapido con audios largos

Causa: JSON/base64, memoria o limites de upload.

Solucion: multipart, mensajes de error para `post_max_size`, lectura directa de `$_FILES`.

### 504 gateway timeout

Causa: request HTTP esperando toda la transcripcion.

Solucion: job asincrono y polling.

### `MAX_TOKENS`

Causa: audio largo o segmento con mucha habla.

Solucion: segmentacion base 3 minutos y subdivision hasta 45 segundos.

### `open_basedir restriction`

Causa: PHP no podia acceder a `/usr/bin/ffmpeg` o `/usr/bin/ffprobe`.

Solucion: validar rutas sin `realpath()`/`is_executable()` fuera de `open_basedir`, configurar rutas permitidas.

### `Transcripcion vacia`

Causa: segmentos con silencio, prompts que permitian "no escribir nada", o respuestas vacias del modelo.

Solucion: marcador `[sin habla]`, prompt fallback ASR, subdivision y omision de tramos realmente vacios.

### OpenRouter no recibia audio

Causa: se envio `inputAudio` en REST raw.

Solucion: usar `input_audio`.

### Hablantes no estructurados

Causa: prompt permitia omitir etiqueta si solo habia un hablante.

Solucion: exigir etiqueta siempre.

## Checklist para implementar en otra app

1. Crear tabla o mecanismo de jobs background con `pending`, `processing`, `completed`, `failed`.
2. Crear endpoint de subida multipart con CSRF/autenticacion.
3. Guardar audio temporal fuera de `public`.
4. Crear worker/procesador de jobs.
5. Instalar o habilitar `ffmpeg` y `ffprobe`.
6. Comprobar `open_basedir` si es hosting Plesk/cPanel.
7. Implementar `AudioTranscriber` con:
   - proveedor configurable;
   - validacion MIME/tamano;
   - deteccion de duracion;
   - segmentacion;
   - prompts;
   - fallbacks por `MAX_TOKENS`, vacio y repeticion.
8. Implementar polling en frontend.
9. Renderizar parcial mientras procesa.
10. Guardar resultado final con metadatos.
11. Loguear proveedor, modelo, segmentos y errores.
12. Probar con:
   - audio corto;
   - audio de 45 minutos;
   - audio con silencios largos;
   - audio con dos o mas hablantes;
   - archivo grande cerca del limite;
   - servidor con `open_basedir`.

## Pruebas manuales recomendadas

### Audio corto

Esperado:

- no se segmenta;
- devuelve resultado rapido;
- contiene etiquetas de hablante.

### Audio largo de 40-45 minutos

Esperado:

- se encola job;
- se divide en unos 15 segmentos si se usan segmentos de 180 segundos;
- aparece barra de progreso;
- aparece texto parcial;
- termina sin 504;
- resultado final contiene metadatos `segmented=true`.

### Audio con silencio

Esperado:

- no falla por `Transcripcion vacia`;
- omite segmentos sin habla;
- si todo esta vacio, falla claramente.

### OpenRouter

Esperado:

- en dashboard debe verse input mucho mayor que solo el prompt;
- si el input ronda 400-600 tokens y la respuesta pide adjuntar audio, el payload de audio se esta ignorando;
- revisar que el JSON use `input_audio`.

### Gemini directo

Esperado:

- se ven uploads a File API;
- los segmentos pueden procesarse en paralelo;
- el log muestra inicio/fin de fragmentos.

## Decision final recomendada

Para otra app, mi recomendacion practica seria:

1. Usar Gemini directo con File API para audios largos si tienes `GEMINI_API_KEY`.
2. Mantener OpenRouter como proveedor configurable o fallback.
3. No procesar audios largos en una sola request HTTP.
4. No subir audio largo como base64 desde navegador.
5. Segmentar siempre a partir de 10 minutos.
6. Mostrar progreso parcial desde el primer segmento.
7. Guardar proveedor/modelo en metadatos para diagnosticar costes y calidad.

OpenRouter es util porque centraliza modelos y facturacion, pero para audio largo la File API directa de Google evita payloads enormes y reduce una clase completa de errores.
