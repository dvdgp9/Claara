<?php

namespace Utils;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\SimpleType\Jc;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generador de documentos PDF y DOCX
 * Reutilizable para SOPs, chat exports, etc.
 */
class DocumentGenerator
{
    private string $outputDir;
    
    public function __construct(?string $outputDir = null)
    {
        $this->outputDir = $outputDir ?? dirname(__DIR__, 2) . '/storage/documents';
        
        if (!is_dir($this->outputDir)) {
            @mkdir($this->outputDir, 0775, true);
        }
    }
    
    /**
     * Genera un documento PDF a partir de contenido Markdown
     * 
     * @param string $markdown Contenido en formato Markdown
     * @param string $title Título del documento
     * @param array $options Opciones adicionales (paper_size, orientation, etc.)
     * @return array ['success' => bool, 'path' => string, 'filename' => string, 'url' => string, 'error' => string|null]
     */
    public function generatePdf(string $markdown, string $title, array $options = []): array
    {
        try {
            $html = $this->markdownToHtml($markdown, $title);
            
            $dompdfOptions = new Options();
            $dompdfOptions->set('isHtml5ParserEnabled', true);
            $dompdfOptions->set('isRemoteEnabled', false);
            $dompdfOptions->set('defaultFont', 'DejaVu Sans');
            
            $dompdf = new Dompdf($dompdfOptions);
            $dompdf->loadHtml($html);
            $dompdf->setPaper($options['paper_size'] ?? 'A4', $options['orientation'] ?? 'portrait');
            $dompdf->render();
            
            $filename = $this->generateFilename($title, 'pdf');
            $filepath = $this->outputDir . '/' . $filename;
            
            file_put_contents($filepath, $dompdf->output());
            
            return [
                'success' => true,
                'path' => $filepath,
                'filename' => $filename,
                'url' => '/api/files/document.php?file=' . urlencode($filename)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error generando PDF: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera un documento DOCX a partir de contenido Markdown
     * 
     * @param string $markdown Contenido en formato Markdown
     * @param string $title Título del documento
     * @param array $options Opciones adicionales
     * @return array ['success' => bool, 'path' => string, 'filename' => string, 'url' => string, 'error' => string|null]
     */
    public function generateDocx(string $markdown, string $title, array $options = []): array
    {
        try {
            $phpWord = new PhpWord();
            
            // Estilos predefinidos
            $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 20, 'color' => '1a1a1a'], ['spaceBefore' => 240, 'spaceAfter' => 120]);
            $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 16, 'color' => '333333'], ['spaceBefore' => 200, 'spaceAfter' => 100]);
            $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 14, 'color' => '444444'], ['spaceBefore' => 160, 'spaceAfter' => 80]);
            
            $section = $phpWord->addSection([
                'marginTop' => 1440,    // 1 inch
                'marginBottom' => 1440,
                'marginLeft' => 1440,
                'marginRight' => 1440,
            ]);
            
            // Parsear markdown y añadir contenido (el título ya viene en el markdown)
            $this->parseMarkdownToWord($section, $markdown);
            
            $filename = $this->generateFilename($title, 'docx');
            $filepath = $this->outputDir . '/' . $filename;
            
            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filepath);
            
            return [
                'success' => true,
                'path' => $filepath,
                'filename' => $filename,
                'url' => '/api/files/document.php?file=' . urlencode($filename)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error generando DOCX: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Convierte Markdown a HTML con estilos para PDF
     */
    private function markdownToHtml(string $markdown, string $title): string
    {
        $content = $this->parseMarkdownBasic($markdown);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }
        h1 {
            font-size: 20pt;
            color: #1a1a1a;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 8px;
            margin-top: 24px;
            margin-bottom: 16px;
        }
        h2 {
            font-size: 16pt;
            color: #333;
            margin-top: 20px;
            margin-bottom: 12px;
        }
        h3 {
            font-size: 13pt;
            color: #444;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        p {
            margin-bottom: 12px;
        }
        ul, ol {
            margin-bottom: 12px;
            padding-left: 24px;
        }
        li {
            margin-bottom: 6px;
        }
        code {
            background-color: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 10pt;
        }
        pre {
            background-color: #f3f4f6;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 10pt;
        }
        pre code {
            background: none;
            padding: 0;
        }
        blockquote {
            border-left: 4px solid #3b82f6;
            margin: 16px 0;
            padding: 8px 16px;
            background-color: #f8fafc;
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .checkbox {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #64748b;
            border-radius: 3px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .checkbox.checked {
            background-color: #3b82f6;
        }
        .step-number {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: #3b82f6;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 8px;
            font-size: 12pt;
        }
        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .header h1 {
            border: none;
            margin: 0;
            padding: 0;
        }
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    {$content}
    <div class="footer">
        Generado por Ebonia
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Parsea Markdown básico a HTML
     */
    private function parseMarkdownBasic(string $markdown): string
    {
        $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
        
        // Code blocks (antes de otros para preservar contenido)
        $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $html);
        
        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // Checkboxes
        $html = preg_replace('/^- \[x\] (.+)$/m', '<p><span class="checkbox checked"></span>$1</p>', $html);
        $html = preg_replace('/^- \[ \] (.+)$/m', '<p><span class="checkbox"></span>$1</p>', $html);
        
        // Lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^(\d+)\. (.+)$/m', '<li>$2</li>', $html);
        
        // Wrap consecutive li in ul/ol
        $html = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ul>$0</ul>', $html);
        
        // Blockquotes
        $html = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $html);
        
        // Horizontal rules
        $html = preg_replace('/^---+$/m', '<hr>', $html);
        
        // Paragraphs (líneas que no son HTML)
        $lines = explode("\n", $html);
        $result = [];
        $inParagraph = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                if ($inParagraph) {
                    $result[] = '</p>';
                    $inParagraph = false;
                }
                continue;
            }
            
            // Si es un tag HTML, no envolver en p
            if (preg_match('/^<(h[1-6]|ul|ol|li|pre|blockquote|hr|p|div)/', $trimmed)) {
                if ($inParagraph) {
                    $result[] = '</p>';
                    $inParagraph = false;
                }
                $result[] = $line;
            } else {
                if (!$inParagraph) {
                    $result[] = '<p>' . $line;
                    $inParagraph = true;
                } else {
                    $result[] = ' ' . $line;
                }
            }
        }
        
        if ($inParagraph) {
            $result[] = '</p>';
        }
        
        return implode("\n", $result);
    }
    
    /**
     * Parsea Markdown y lo añade a una sección de PhpWord
     */
    private function parseMarkdownToWord($section, string $markdown): void
    {
        $lines = explode("\n", $markdown);
        $inCodeBlock = false;
        $codeBuffer = [];
        $listItems = [];
        $listType = null;
        
        foreach ($lines as $line) {
            // Code blocks
            if (preg_match('/^```/', $line)) {
                if ($inCodeBlock) {
                    // Cerrar code block
                    $section->addText(implode("\n", $codeBuffer), ['name' => 'Courier New', 'size' => 9], ['shading' => ['fill' => 'f3f4f6']]);
                    $codeBuffer = [];
                    $inCodeBlock = false;
                } else {
                    $inCodeBlock = true;
                }
                continue;
            }
            
            if ($inCodeBlock) {
                $codeBuffer[] = $line;
                continue;
            }
            
            $trimmed = trim($line);
            
            // Headers
            if (preg_match('/^### (.+)$/', $trimmed, $m)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $section->addTitle($m[1], 3);
                continue;
            }
            if (preg_match('/^## (.+)$/', $trimmed, $m)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $section->addTitle($m[1], 2);
                continue;
            }
            if (preg_match('/^# (.+)$/', $trimmed, $m)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $section->addTitle($m[1], 1);
                continue;
            }
            
            // Checkboxes
            if (preg_match('/^- \[(x| )\] (.+)$/', $trimmed, $m)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $checked = $m[1] === 'x';
                $text = $m[2];
                $run = $section->addTextRun();
                $run->addText($checked ? '☑ ' : '☐ ', ['size' => 11]);
                $this->addFormattedText($run, $text);
                continue;
            }
            
            // Unordered list
            if (preg_match('/^- (.+)$/', $trimmed, $m)) {
                if ($listType !== 'ul') {
                    $this->flushList($section, $listItems, $listType);
                    $listItems = [];
                    $listType = 'ul';
                }
                $listItems[] = $m[1];
                continue;
            }
            
            // Ordered list
            if (preg_match('/^(\d+)\. (.+)$/', $trimmed, $m)) {
                if ($listType !== 'ol') {
                    $this->flushList($section, $listItems, $listType);
                    $listItems = [];
                    $listType = 'ol';
                }
                $listItems[] = $m[2];
                continue;
            }
            
            // Horizontal rule
            if (preg_match('/^---+$/', $trimmed)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $section->addTextBreak();
                continue;
            }
            
            // Empty line
            if (empty($trimmed)) {
                $this->flushList($section, $listItems, $listType);
                $listItems = [];
                $listType = null;
                continue;
            }
            
            // Regular paragraph
            $this->flushList($section, $listItems, $listType);
            $listItems = [];
            $listType = null;
            
            $run = $section->addTextRun();
            $this->addFormattedText($run, $trimmed);
        }
        
        // Flush remaining list
        $this->flushList($section, $listItems, $listType);
    }
    
    /**
     * Añade texto con formato (bold, italic, code) a un TextRun
     */
    private function addFormattedText($run, string $text): void
    {
        // Regex para detectar **bold**, *italic*, `code`
        $pattern = '/(\*\*(.+?)\*\*|\*(.+?)\*|`([^`]+)`)/';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $i = 0;
        while ($i < count($parts)) {
            $part = $parts[$i];
            
            if (empty($part)) {
                $i++;
                continue;
            }
            
            // Check if it's a pattern match
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $m)) {
                $run->addText($m[1], ['bold' => true]);
                $i += 2; // Skip the captured group
            } elseif (preg_match('/^\*([^*]+)\*$/', $part, $m)) {
                $run->addText($m[1], ['italic' => true]);
                $i += 2;
            } elseif (preg_match('/^`([^`]+)`$/', $part, $m)) {
                $run->addText($m[1], ['name' => 'Courier New', 'size' => 9, 'shading' => ['fill' => 'f3f4f6']]);
                $i += 2;
            } else {
                $run->addText($part);
                $i++;
            }
        }
    }
    
    /**
     * Escribe los items de lista acumulados
     */
    private function flushList($section, array $items, ?string $type): void
    {
        if (empty($items)) return;
        
        $listStyle = $type === 'ol' ? 'multilevel' : 'bullet';
        
        foreach ($items as $index => $item) {
            $run = $section->addTextRun();
            if ($type === 'ol') {
                $run->addText(($index + 1) . '. ', ['bold' => true]);
            } else {
                $run->addText('• ', []);
            }
            $this->addFormattedText($run, $item);
        }
    }
    
    /**
     * Genera un nombre de archivo simplificado (slug) a partir del título
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[áàäâ]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöô]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/ñ/u', 'n', $text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        $text = trim($text, '_');
        
        return substr($text, 0, 50) ?: 'document';
    }
    
    /**
     * Genera un nombre de archivo simple sin timestamps ni hashes
     */
    private function generateFilename(string $title, string $extension): string
    {
        $slug = $this->slugify($title);
        
        return "{$slug}.{$extension}";
    }
    
    /**
     * Elimina archivos antiguos del directorio de documentos
     */
    public function cleanupOld(int $hoursOld = 24): int
    {
        $count = 0;
        $threshold = time() - ($hoursOld * 3600);
        
        foreach (glob($this->outputDir . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Obtiene la ruta completa de un archivo
     */
    public function getFilePath(string $filename): ?string
    {
        $path = $this->outputDir . '/' . basename($filename);
        return file_exists($path) ? $path : null;
    }
}
