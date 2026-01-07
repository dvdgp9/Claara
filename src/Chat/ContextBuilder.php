<?php
namespace Chat;

/**
 * Construye el contexto corporativo para inyectar en los LLM.
 * 
 * Lee todos los archivos markdown de docs/context/ y los combina
 * en un único prompt de sistema. Este contexto es el mismo para
 * todos los proveedores (Gemini, OpenAI, etc.), cada uno lo usa
 * en su formato nativo.
 */
class ContextBuilder
{
    private string $contextDir;

    public function __construct(?string $contextDir = null)
    {
        $this->contextDir = $contextDir ?? dirname(dirname(__DIR__)) . '/docs/context';
    }

    /**
     * Construye el prompt de sistema completo con todo el contexto corporativo.
     */
    public function buildSystemPrompt(): string
    {
        if (!is_dir($this->contextDir)) {
            $content = $this->getDefaultPrompt();
        } else {
            $content = '';
            $files = $this->getMarkdownFiles();

            if (empty($files)) {
                $content = $this->getDefaultPrompt();
            } else {
                foreach ($files as $file) {
                    $fileContent = file_get_contents($file);
                    if ($fileContent !== false) {
                        $content .= $fileContent . "\n\n---\n\n";
                    }
                }
            }
        }

        // Añadir contexto temporal dinámico
        $content = trim($content);
        $dateStr = date('l, d de F de Y');
        $timeStr = date('H:i');
        
        // Traducción simple de días y meses al español si es necesario
        $days = ['Monday'=>'Lunes', 'Tuesday'=>'Martes', 'Wednesday'=>'Miércoles', 'Thursday'=>'Jueves', 'Friday'=>'Viernes', 'Saturday'=>'Sábado', 'Sunday'=>'Domingo'];
        $months = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
        
        foreach($days as $en => $es) $dateStr = str_replace($en, $es, $dateStr);
        foreach($months as $en => $es) $dateStr = str_replace($en, $es, $dateStr);

        $temporalContext = "\n\n---\n\n## Contexto Temporal\nFecha actual: " . $dateStr . "\nHora actual: " . $timeStr;
        
        return $content . $temporalContext;
    }

    /**
     * Obtiene todos los archivos .md del directorio de contexto, ordenados alfabéticamente.
     * Prioriza archivos de instrucciones al inicio.
     * 
     * @return array<string>
     */
    private function getMarkdownFiles(): array
    {
        $files = glob($this->contextDir . '/*.md');
        if ($files === false) {
            return [];
        }

        // Ordenar alfabéticamente primero
        sort($files);
        
        // Archivos prioritarios que deben ir al inicio
        $priorityFiles = [
            $this->contextDir . '/system_prompt.md',
        ];
        
        // Mover archivos prioritarios al inicio
        foreach (array_reverse($priorityFiles) as $priorityFile) {
            if (in_array($priorityFile, $files)) {
                $files = array_values(array_diff($files, [$priorityFile]));
                array_unshift($files, $priorityFile);
            }
        }

        return $files;
    }

    /**
     * Prompt por defecto si no hay archivos de contexto.
     */
    private function getDefaultPrompt(): string
    {
        return "Eres Ebonia, un asistente de IA corporativa profesional y útil.";
    }
}
