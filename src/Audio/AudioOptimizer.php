<?php
namespace Audio;

class AudioOptimizer
{
    /**
     * Convierte un archivo de audio (normalmente WAV) a M4A usando ffmpeg
     * 
     * @param string $inputPath Ruta al archivo original
     * @param string $outputPath Ruta al archivo de destino (.m4a)
     * @param int $bitrate Bitrate en kbps (48k es ideal para voz)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function convertToM4a(string $inputPath, string $outputPath, int $bitrate = 48): array
    {
        // Comprobar si ffmpeg está disponible
        $ffmpegPath = self::getFfmpegPath();
        if (!$ffmpegPath) {
            return ['success' => false, 'error' => 'ffmpeg no está instalado o no es accesible en el servidor.'];
        }

        // Comando para convertir a m4a (usando el encoder aac nativo de ffmpeg)
        // -i: input, -c:a aac: codec audio, -b:a: bitrate, -y: overwrite
        $command = sprintf(
            '%s -i %s -c:a aac -b:a %dk -y %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($inputPath),
            $bitrate,
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return [
                'success' => false, 
                'error' => 'Error en la conversión: ' . implode("\n", $output)
            ];
        }

        return ['success' => true];
    }

    /**
     * Intenta localizar el binario de ffmpeg
     */
    private static function getFfmpegPath(): ?string
    {
        // En la mayoría de servidores Linux está en /usr/bin/ffmpeg
        $paths = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'];
        
        foreach ($paths as $path) {
            $command = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
                ? "where $path" 
                : "which $path";
                
            $execPath = shell_exec($command);
            if ($execPath) return trim($execPath);
        }

        return null;
    }
}
