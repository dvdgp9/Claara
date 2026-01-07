<?php

declare(strict_types=1);

namespace Utils;

/**
 * Utilidad para leer archivos CSV y Excel y convertirlos a texto tabular.
 * Para Excel, usa PhpSpreadsheet si está disponible, sino intenta leer como CSV.
 */
class SpreadsheetReader
{
    private const MAX_ROWS = 1000;
    private const MAX_COLS = 100;
    private const MAX_CELL_LENGTH = 500;
    private const MAX_SHEETS = 5;

    /**
     * Lee un archivo de hoja de cálculo desde datos binarios y devuelve texto tabular.
     * 
     * @param string $binaryData Contenido binario del archivo
     * @param string $mimeType Tipo MIME del archivo
     * @param string $fileName Nombre original del archivo (para contexto)
     * @return string Contenido en formato texto tabular (Markdown)
     */
    public static function readToText(string $binaryData, string $mimeType, string $fileName = 'archivo'): string
    {
        $rows = self::parseToArray($binaryData, $mimeType);
        
        if (empty($rows)) {
            return "[No se pudo leer el contenido del archivo: $fileName]";
        }

        return self::formatAsMarkdownTable($rows, $fileName);
    }

    /**
     * Parsea el archivo a un array de filas.
     */
    private static function parseToArray(string $binaryData, string $mimeType): array
    {
        switch ($mimeType) {
            case 'text/csv':
                return self::parseCsv($binaryData);
            
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return self::parseExcel($binaryData, $mimeType);
            
            default:
                return [];
        }
    }

    /**
     * Parsea contenido CSV.
     */
    private static function parseCsv(string $data): array
    {
        $rows = [];
        $lines = explode("\n", $data);
        $rowCount = 0;

        foreach ($lines as $line) {
            if ($rowCount >= self::MAX_ROWS) break;
            
            $line = trim($line);
            if ($line === '') continue;

            // Detectar delimitador (coma, punto y coma, tabulador)
            $delimiter = self::detectCsvDelimiter($line);
            $cells = str_getcsv($line, $delimiter);
            
            // Limitar columnas y longitud de celdas
            $cells = array_slice($cells, 0, self::MAX_COLS);
            $cells = array_map(fn($c) => mb_substr(trim($c), 0, self::MAX_CELL_LENGTH), $cells);
            
            $rows[] = $cells;
            $rowCount++;
        }

        return $rows;
    }

    /**
     * Detecta el delimitador más probable de un CSV.
     */
    private static function detectCsvDelimiter(string $line): string
    {
        $delimiters = [';' => 0, ',' => 0, "\t" => 0];
        
        foreach (array_keys($delimiters) as $d) {
            $delimiters[$d] = substr_count($line, $d);
        }

        arsort($delimiters);
        $best = array_key_first($delimiters);
        
        return $delimiters[$best] > 0 ? $best : ',';
    }

    /**
     * Parsea contenido Excel usando PhpSpreadsheet si está disponible.
     */
    private static function parseExcel(string $data, string $mimeType): array
    {
        // Intentar usar PhpSpreadsheet si está disponible
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            return self::parseExcelWithPhpSpreadsheet($data, $mimeType);
        }

        // Fallback: para XLSX, intentar extraer como ZIP y leer el XML interno
        if ($mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            return self::parseXlsxBasic($data);
        }

        // XLS antiguo sin PhpSpreadsheet: no podemos leerlo
        return [['[Formato XLS requiere librería PhpSpreadsheet para lectura]']];
    }

    /**
     * Parsea Excel con PhpSpreadsheet (MÉTODO OPTIMIZADO).
     */
    private static function parseExcelWithPhpSpreadsheet(string $data, string $mimeType): array
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempFile, $data);

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
            $allRows = [];
            $sheetCount = 0;

            // Procesar todas las hojas del documento
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                if ($sheetCount >= self::MAX_SHEETS) break;
                
                $sheetName = $sheet->getTitle();
                $sheetRows = [];
                $rowCount = 0;

                // Obtener el rango real de datos (sin filas vacías al final)
                $highestRow = $sheet->getHighestDataRow();
                $highestCol = $sheet->getHighestDataColumn();
                $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
                $highestColIndex = min($highestColIndex, self::MAX_COLS);

                for ($rowIndex = 1; $rowIndex <= min($highestRow, self::MAX_ROWS); $rowIndex++) {
                    $cells = [];
                    $hasContent = false;

                    for ($colIndex = 1; $colIndex <= $highestColIndex; $colIndex++) {
                        // PhpSpreadsheet 5.x: usar getCell con array [col, row]
                        $cell = $sheet->getCell([$colIndex, $rowIndex]);
                        
                        // Obtener valor calculado (importante para fórmulas)
                        $value = '';
                        try {
                            $value = $cell->getCalculatedValue();
                        } catch (\Exception $e) {
                            // Si hay error en la fórmula, usar el valor raw
                            $value = $cell->getValue();
                        }

                        // Formatear según tipo de dato
                        if ($value instanceof \DateTimeInterface) {
                            // Formatear fechas
                            $value = $value->format('Y-m-d H:i:s');
                        } elseif (is_numeric($value)) {
                            // Números: mantener precisión sin notación científica
                            $value = is_float($value) ? rtrim(rtrim(sprintf('%.10f', $value), '0'), '.') : (string)$value;
                        } else {
                            $value = (string)($value ?? '');
                        }

                        $value = trim($value);
                        if ($value !== '') {
                            $hasContent = true;
                        }

                        $cells[] = mb_substr($value, 0, self::MAX_CELL_LENGTH);
                    }

                    // Solo añadir filas con contenido
                    if ($hasContent) {
                        $sheetRows[] = $cells;
                        $rowCount++;
                    }
                }

                // Si el Excel tiene múltiples hojas, agregar separador
                if ($sheetCount > 0 && !empty($sheetRows)) {
                    $allRows[] = []; // Fila vacía como separador
                    $allRows[] = ["=== HOJA: $sheetName ==="]; // Indicador de hoja
                }

                $allRows = array_merge($allRows, $sheetRows);
                $sheetCount++;
            }

            @unlink($tempFile);
            return $allRows;

        } catch (\Exception $e) {
            return [['[Error al leer Excel: ' . $e->getMessage() . ']']];
        }
    }

    /**
     * Parseo básico de XLSX sin dependencias externas.
     * XLSX es un ZIP con XMLs internos.
     */
    private static function parseXlsxBasic(string $data): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tempFile, $data);

        $rows = [];

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                throw new \Exception('No se pudo abrir el archivo XLSX');
            }

            // Leer shared strings (textos compartidos)
            $sharedStrings = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml) {
                $ssDoc = new \SimpleXMLElement($ssXml);
                foreach ($ssDoc->si as $si) {
                    $sharedStrings[] = (string)($si->t ?? $si->r->t ?? '');
                }
            }

            // Leer la primera hoja (sheet1.xml)
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (!$sheetXml) {
                throw new \Exception('No se encontró la hoja de cálculo');
            }

            $sheetDoc = new \SimpleXMLElement($sheetXml);
            $rowCount = 0;

            foreach ($sheetDoc->sheetData->row as $row) {
                if ($rowCount >= self::MAX_ROWS) break;

                $cells = [];
                $colCount = 0;

                foreach ($row->c as $cell) {
                    if ($colCount >= self::MAX_COLS) break;

                    $value = '';
                    $type = (string)$cell['t'];

                    if ($type === 's') {
                        // String compartido
                        $idx = (int)$cell->v;
                        $value = $sharedStrings[$idx] ?? '';
                    } else {
                        $value = (string)($cell->v ?? '');
                    }

                    $cells[] = mb_substr(trim($value), 0, self::MAX_CELL_LENGTH);
                    $colCount++;
                }

                if (array_filter($cells, fn($c) => $c !== '')) {
                    $rows[] = $cells;
                    $rowCount++;
                }
            }

            $zip->close();

        } catch (\Exception $e) {
            $rows = [['[Error al leer XLSX: ' . $e->getMessage() . ']']];
        }

        @unlink($tempFile);
        return $rows;
    }

    /**
     * Formatea las filas como tabla Markdown.
     */
    private static function formatAsMarkdownTable(array $rows, string $fileName): string
    {
        if (empty($rows)) {
            return '';
        }

        $output = "**Contenido del archivo: $fileName**\n\n";

        // Detectar si la primera fila parece una cabecera
        $header = $rows[0];
        $colCount = count($header);
        
        // Si parece cabecera (no numérica, texto descriptivo), usarla
        $isHeader = false;
        foreach ($header as $cell) {
            if (!empty($cell) && !is_numeric($cell)) {
                $isHeader = true;
                break;
            }
        }

        if ($isHeader) {
            array_shift($rows);
        } else {
            // Crear cabecera genérica
            $header = array_map(fn($i) => "Col" . ($i + 1), array_keys($header));
        }

        // Escapar pipes en celdas
        $escapePipe = fn($s) => str_replace('|', '\\|', $s);

        $output .= '| ' . implode(' | ', array_map($escapePipe, $header)) . " |\n";
        $output .= '|' . str_repeat(' --- |', $colCount) . "\n";

        foreach ($rows as $row) {
            // Asegurar mismo número de columnas
            while (count($row) < $colCount) {
                $row[] = '';
            }
            $row = array_slice($row, 0, $colCount);
            $output .= '| ' . implode(' | ', array_map($escapePipe, $row)) . " |\n";
        }

        $rowCountInfo = count($rows);
        if ($rowCountInfo >= self::MAX_ROWS - 1) {
            $output .= "\n*[Tabla truncada a " . self::MAX_ROWS . " filas. Si necesitas ver más datos, considera filtrar el archivo original.]*\n";
        }

        // Añadir resumen de estadísticas
        $totalRows = count($rows) + 1; // +1 por la cabecera
        $totalCols = $colCount;
        $output .= "\n---\n*Dimensiones: $totalRows filas × $totalCols columnas*\n";

        return $output;
    }

    /**
     * Verifica si un MIME type es de hoja de cálculo.
     */
    public static function isSpreadsheet(string $mimeType): bool
    {
        return in_array($mimeType, [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }
}
