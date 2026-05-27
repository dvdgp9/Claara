<?php
namespace Chat;

/**
 * Builds the assistant context injected into LLMs.
 * 
 * Reads all markdown files from docs/context/ and combines them into a
 * single system prompt. The context is provider-agnostic.
 */
class ContextBuilder
{
    private string $contextDir;

    public function __construct(?string $contextDir = null)
    {
        $this->contextDir = $contextDir ?? dirname(dirname(__DIR__)) . '/docs/context';
    }

    /**
     * Builds the complete system prompt.
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

        // Add dynamic temporal context.
        $content = trim($content);
        $dateStr = date('l, F j, Y');
        $timeStr = date('H:i');

        $temporalContext = "\n\n---\n\n## Temporal Context\nCurrent date: " . $dateStr . "\nCurrent time: " . $timeStr;
        
        return $content . $temporalContext;
    }

    /**
     * Gets all .md context files sorted alphabetically.
     * Prioritizes instruction files first.
     * 
     * @return array<string>
     */
    private function getMarkdownFiles(): array
    {
        $files = glob($this->contextDir . '/*.md');
        if ($files === false) {
            return [];
        }

        // Sort alphabetically first.
        sort($files);
        
        // Priority files that should appear first.
        $priorityFiles = [
            $this->contextDir . '/system_prompt.md',
        ];
        
        // Move priority files to the beginning.
        foreach (array_reverse($priorityFiles) as $priorityFile) {
            if (in_array($priorityFile, $files)) {
                $files = array_values(array_diff($files, [$priorityFile]));
                array_unshift($files, $priorityFile);
            }
        }

        return $files;
    }

    /**
     * Default prompt if no context files exist.
     */
    private function getDefaultPrompt(): string
    {
        return "You are Claara, a friendly and professional AI assistant for everyday work.";
    }
}
